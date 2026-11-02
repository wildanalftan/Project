import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  TrendingUp, TrendingDown, ShoppingCart, Package,
  AlertTriangle, DollarSign, BarChart3, RefreshCw
} from 'lucide-react'
import {
  AreaChart, Area, BarChart, Bar, XAxis, YAxis, CartesianGrid,
  Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend
} from 'recharts'
import api from '../lib/api'
import { fmt, fmtShort, pct, fmtDate, stockStatus } from '../lib/fmt'
import { CardSkeleton, EmptyState } from '../components/ui/States'

const PERIODS = [
  { id: 'today',  label: 'Hari Ini' },
  { id: 'week',   label: '7 Hari' },
  { id: 'month',  label: 'Bulan Ini' },
  { id: 'year',   label: 'Tahun Ini' },
]

const COLORS = ['#9C4A21', '#1D6B55', '#9A6B06', '#1F4571']

function RupiahTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null
  return (
    <div className="card px-3 py-2 text-xs shadow-modal">
      <p className="font-semibold text-ink mb-1">{label}</p>
      {payload.map((p, i) => (
        <p key={i} style={{ color: p.color }}>
          {p.name}: {fmt(p.value)}
        </p>
      ))}
    </div>
  )
}

export default function DashboardPage() {
  const [period, setPeriod] = useState('today')

  const { data, isLoading, refetch, isFetching } = useQuery({
    queryKey: ['dashboard', period],
    queryFn: () => api.get(`/dashboard?period=${period}`).then(r => r.data.data),
    refetchInterval: period === 'today' ? 1000 * 60 : false, // auto-refresh every 60s for today
  })

  const statCards = [
    {
      label: 'Omzet',
      value: fmtShort(data?.revenue ?? 0),
      full: fmt(data?.revenue ?? 0),
      icon: DollarSign,
      trend: data?.revenue_growth,
      color: 'text-kopi',
      bg: 'bg-kopi-10',
    },
    {
      label: 'Laba Kotor',
      value: fmtShort(data?.gross_profit ?? 0),
      full: fmt(data?.gross_profit ?? 0),
      icon: TrendingUp,
      sub: pct(data?.gross_margin),
      color: 'text-laba',
      bg: 'bg-laba-10',
    },
    {
      label: 'Transaksi',
      value: (data?.transaction_count ?? 0).toLocaleString('id-ID'),
      icon: ShoppingCart,
      sub: `Rata-rata ${fmt(data?.avg_transaction ?? 0)}`,
      color: 'text-offline',
      bg: 'bg-offline-10',
    },
    {
      label: 'Produk Kritis',
      value: (data?.low_stock_count ?? 0).toString(),
      icon: AlertTriangle,
      sub: `${data?.out_of_stock_count ?? 0} habis`,
      color: 'text-perhatian',
      bg: 'bg-perhatian-10',
      alert: (data?.low_stock_count ?? 0) > 0,
    },
  ]

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <h1 className="page-title">Dashboard</h1>
        <div className="flex items-center gap-3">
          <button
            onClick={() => refetch()}
            disabled={isFetching}
            className="btn btn-ghost btn-sm gap-1.5"
          >
            <RefreshCw size={14} className={isFetching ? 'animate-spin' : ''} />
          </button>
          <div className="flex gap-1 p-1 bg-surface rounded-control border border-line">
            {PERIODS.map((p) => (
              <button
                key={p.id}
                onClick={() => setPeriod(p.id)}
                className={`px-4 py-1.5 rounded-[8px] text-sm font-semibold transition-all ${
                  period === p.id ? 'bg-kopi text-white shadow-btn' : 'text-ink-70 hover:text-ink'
                }`}
              >
                {p.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {isLoading
          ? Array.from({ length: 4 }).map((_, i) => <CardSkeleton key={i} />)
          : statCards.map((s) => (
              <div key={s.label} className={`stat-card ${s.alert ? 'ring-2 ring-perhatian/30' : ''}`}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="stat-card-label">{s.label}</p>
                    <p className="stat-card-value mt-2" title={s.full}>{s.value}</p>
                    {s.sub && <p className="text-ink-45 text-xs mt-1">{s.sub}</p>}
                  </div>
                  <div className={`w-10 h-10 rounded-control flex items-center justify-center ${s.bg}`}>
                    <s.icon size={18} className={s.color} />
                  </div>
                </div>
                {s.trend !== undefined && (
                  <div className={`flex items-center gap-1 text-xs font-semibold ${s.trend >= 0 ? 'text-laba' : 'text-kritis'}`}>
                    {s.trend >= 0 ? <TrendingUp size={13} /> : <TrendingDown size={13} />}
                    {Math.abs(s.trend).toFixed(1)}% vs periode sebelumnya
                  </div>
                )}
              </div>
            ))
        }
      </div>

      {/* Charts */}
      <div className="grid grid-cols-3 gap-6">
        {/* Revenue chart */}
        <div className="card p-5 col-span-2">
          <h2 className="font-display font-bold text-ink mb-4" style={{ fontSize: 17 }}>
            Omzet vs HPP
          </h2>
          <ResponsiveContainer width="100%" height={220}>
            {isLoading ? (
              <div className="skeleton h-full rounded" />
            ) : (
              <AreaChart data={data?.chart_data ?? []}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E6E0D8" />
                <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#7A6F66' }} />
                <YAxis tickFormatter={(v) => fmtShort(v)} tick={{ fontSize: 11, fill: '#7A6F66' }} />
                <Tooltip content={<RupiahTooltip />} />
                <Area type="monotone" dataKey="revenue" name="Omzet" stroke="#9C4A21" fill="#F7E9DF" strokeWidth={2} />
                <Area type="monotone" dataKey="cogs" name="HPP" stroke="#A32B2B" fill="#FDEAEA" strokeWidth={2} />
              </AreaChart>
            )}
          </ResponsiveContainer>
        </div>

        {/* Payment method pie */}
        <div className="card p-5">
          <h2 className="font-display font-bold text-ink mb-4" style={{ fontSize: 17 }}>
            Metode Bayar
          </h2>
          {isLoading ? (
            <div className="skeleton h-48 rounded" />
          ) : (
            <ResponsiveContainer width="100%" height={190}>
              <PieChart>
                <Pie
                  data={data?.payment_breakdown ?? []}
                  dataKey="total"
                  nameKey="method"
                  cx="50%"
                  cy="50%"
                  outerRadius={70}
                  label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  labelLine={false}
                >
                  {(data?.payment_breakdown ?? []).map((_, i) => (
                    <Cell key={i} fill={COLORS[i % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip formatter={(v) => fmt(v)} />
              </PieChart>
            </ResponsiveContainer>
          )}
        </div>
      </div>

      {/* Top products + Low stock */}
      <div className="grid grid-cols-2 gap-6">
        {/* Top products */}
        <div className="card p-5">
          <h2 className="font-display font-bold text-ink mb-4" style={{ fontSize: 17 }}>
            Produk Terlaris
          </h2>
          {isLoading ? (
            <div className="skeleton h-48 rounded" />
          ) : (data?.top_products ?? []).length === 0 ? (
            <EmptyState icon={BarChart3} title="Belum ada data" />
          ) : (
            <div className="flex flex-col gap-2">
              {(data?.top_products ?? []).slice(0, 6).map((p, i) => (
                <div key={i} className="flex items-center gap-3">
                  <span className="w-6 text-center text-ink-45 font-bold text-xs">{i + 1}</span>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-ink truncate">{p.product_name}</p>
                    <p className="text-xs text-ink-45">{p.qty_sold} terjual</p>
                  </div>
                  <span className="tabular text-sm font-semibold text-ink">{fmtShort(p.revenue)}</span>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Low stock */}
        <div className="card p-5">
          <h2 className="font-display font-bold text-ink mb-4" style={{ fontSize: 17 }}>
            Stok Kritis
          </h2>
          {isLoading ? (
            <div className="skeleton h-48 rounded" />
          ) : (data?.low_stock_products ?? []).length === 0 ? (
            <EmptyState icon={Package} title="Semua stok aman" />
          ) : (
            <div className="flex flex-col gap-2">
              {(data?.low_stock_products ?? []).slice(0, 6).map((p) => {
                const { label, cls } = stockStatus(p)
                return (
                  <div key={p.id} className="flex items-center gap-3">
                    <span className={`badge ${cls}`}>{label}</span>
                    <p className="flex-1 text-sm font-medium text-ink truncate">{p.name}</p>
                    <span className="tabular text-sm font-semibold text-ink-70">
                      {parseFloat(p.stock_qty)}{' '}{p.unit}
                    </span>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Calculator, TrendingUp, TrendingDown, Info } from 'lucide-react'
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell
} from 'recharts'
import api from '../lib/api'
import { fmt, fmtShort, pct, fmtDate, stockStatus } from '../lib/fmt'
import { CardSkeleton, TableSkeleton, EmptyState } from '../components/ui/States'

export default function HppPage() {
  const today = new Date()
  const [from, setFrom] = useState(today.toISOString().slice(0, 10).replace(/\d{2}$/, '01'))
  const [to,   setTo]   = useState(today.toISOString().slice(0, 10))

  const { data, isLoading } = useQuery({
    queryKey: ['hpp', from, to],
    queryFn:  () => api.get(`/hpp?from=${from}&to=${to}`).then(r => r.data.data),
    enabled: !!from && !!to,
  })

  const { data: products = [], isLoadingProducts } = useQuery({
    queryKey: ['products'],
    queryFn: () => api.get('/products').then(r => r.data.data ?? []),
  })

  const productsCost = products.map((p) => ({
    ...p,
    margin: p.selling_price && p.current_cost
      ? ((p.selling_price - p.current_cost) / p.selling_price) * 100
      : 0,
  })).sort((a, b) => b.margin - a.margin)

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <h1 className="page-title">HPP & Margin</h1>
        <div className="flex items-center gap-3">
          <input type="date" className="form-input" style={{ width: 160 }} value={from} onChange={(e) => setFrom(e.target.value)} />
          <span className="text-ink-45">s/d</span>
          <input type="date" className="form-input" style={{ width: 160 }} value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </div>

      {/* Period summary cards */}
      <div className="grid grid-cols-4 gap-4">
        {isLoading ? (
          Array.from({ length: 4 }).map((_, i) => <CardSkeleton key={i} />)
        ) : (
          <>
            <div className="stat-card">
              <p className="stat-card-label">Total Omzet</p>
              <p className="stat-card-value text-kopi mt-2">{fmtShort(data?.revenue ?? 0)}</p>
              <p className="text-ink-45 text-xs mt-1">{fmt(data?.revenue ?? 0)}</p>
            </div>
            <div className="stat-card">
              <p className="stat-card-label">Total HPP</p>
              <p className="stat-card-value text-kritis mt-2">{fmtShort(data?.total_cogs ?? 0)}</p>
              <p className="text-ink-45 text-xs mt-1">{fmt(data?.total_cogs ?? 0)}</p>
            </div>
            <div className="stat-card">
              <p className="stat-card-label">Laba Kotor</p>
              <p className="stat-card-value text-laba mt-2">{fmtShort(data?.gross_profit ?? 0)}</p>
              <p className="text-ink-45 text-xs mt-1">{fmt(data?.gross_profit ?? 0)}</p>
            </div>
            <div className="stat-card">
              <p className="stat-card-label">Margin Kotor</p>
              <p className="stat-card-value mt-2">{pct(data?.gross_margin)}</p>
              <div className={`flex items-center gap-1 text-xs mt-1 font-semibold ${
                (data?.gross_margin ?? 0) >= 40 ? 'text-laba' : 'text-perhatian'
              }`}>
                {(data?.gross_margin ?? 0) >= 40 ? <TrendingUp size={12} /> : <TrendingDown size={12} />}
                Target ≥ 40%
              </div>
            </div>
          </>
        )}
      </div>

      {/* HPP by product for period */}
      {!isLoading && data?.by_product?.length > 0 && (
        <div className="card p-5">
          <h2 className="font-display font-bold text-ink mb-4" style={{ fontSize: 17 }}>
            Kontribusi HPP per Produk
          </h2>
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={data.by_product.slice(0, 10)} layout="vertical">
              <CartesianGrid strokeDasharray="3 3" stroke="#E6E0D8" horizontal={false} />
              <XAxis type="number" tickFormatter={(v) => fmtShort(v)} tick={{ fontSize: 11, fill: '#7A6F66' }} />
              <YAxis dataKey="product_name" type="category" width={130} tick={{ fontSize: 11, fill: '#7A6F66' }} />
              <Tooltip formatter={(v) => fmt(v)} />
              <Bar dataKey="total_cogs" name="Total HPP" fill="#9C4A21" radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Current product HPP table */}
      <div className="card p-5">
        <div className="flex items-center justify-between mb-4">
          <h2 className="font-display font-bold text-ink" style={{ fontSize: 17 }}>
            HPP & Margin Produk Aktif
          </h2>
          <div className="flex items-center gap-1.5 text-ink-45 text-xs">
            <Info size={14} /> HPP menggunakan metode rata-rata tertimbang
          </div>
        </div>
        <div className="table-wrapper border-0">
          <table className="table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Produk</th>
                <th className="text-right">Harga Jual</th>
                <th className="text-right">HPP / unit</th>
                <th className="text-right">Laba / unit</th>
                <th className="text-right">Margin</th>
                <th className="text-right">Stok</th>
                <th className="text-right">Nilai Persediaan</th>
              </tr>
            </thead>
            <tbody>
              {isLoadingProducts ? (
                <TableSkeleton rows={6} cols={8} />
              ) : productsCost.length === 0 ? (
                <tr><td colSpan={8}><EmptyState icon={Calculator} title="Belum ada produk" /></td></tr>
              ) : (
                productsCost.map((p) => {
                  const { label, cls } = stockStatus(p)
                  const profit = p.selling_price - p.current_cost
                  const inventoryValue = parseFloat(p.stock_qty) * p.current_cost
                  return (
                    <tr key={p.id}>
                      <td><span className="text-ink-45 text-xs font-mono">{p.sku}</span></td>
                      <td>
                        <p className="font-medium text-ink">{p.name}</p>
                        <span className={`badge ${cls} mt-0.5`}>{label}</span>
                      </td>
                      <td className="text-right tabular">{fmt(p.selling_price)}</td>
                      <td className="text-right tabular text-ink-70">{fmt(p.current_cost)}</td>
                      <td className={`text-right tabular font-semibold ${profit >= 0 ? 'text-laba' : 'text-kritis'}`}>
                        {fmt(profit)}
                      </td>
                      <td className={`text-right tabular font-semibold ${p.margin >= 40 ? 'text-laba' : p.margin >= 20 ? 'text-perhatian' : 'text-kritis'}`}>
                        {pct(p.margin)}
                      </td>
                      <td className="text-right tabular text-ink-70">
                        {parseFloat(p.stock_qty)} {p.unit}
                      </td>
                      <td className="text-right tabular font-semibold">{fmt(inventoryValue)}</td>
                    </tr>
                  )
                })
              )}
            </tbody>
            {productsCost.length > 0 && (
              <tfoot>
                <tr className="bg-ground font-bold">
                  <td colSpan={7} className="px-4 py-3 text-right text-ink">Total Nilai Persediaan</td>
                  <td className="px-4 py-3 text-right tabular text-ink font-display" style={{ fontSize: 15 }}>
                    {fmt(productsCost.reduce((s, p) => s + parseFloat(p.stock_qty) * p.current_cost, 0))}
                  </td>
                </tr>
              </tfoot>
            )}
          </table>
        </div>
      </div>
    </div>
  )
}

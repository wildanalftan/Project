import React, { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { Download, Plus, TrendingUp, TrendingDown, BarChart3 } from 'lucide-react'
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend
} from 'recharts'
import api from '../lib/api'
import { fmt, fmtShort, pct } from '../lib/fmt'
import Modal from '../components/ui/Modal'
import { useToast } from '../components/ui/Toast'
import { CardSkeleton } from '../components/ui/States'

export default function LaporanPage() {
  const toast = useToast()
  const now   = new Date()
  const [year,  setYear]  = useState(now.getFullYear())
  const [month, setMonth] = useState(now.getMonth() + 1)
  const [expenseModalOpen, setExpenseModalOpen] = useState(false)

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['profit-loss', year, month],
    queryFn: () => api.get(`/reports/profit-loss?year=${year}&month=${month}`).then(r => r.data.data),
  })

  const handleExport = () => {
    const url = `/api/reports/profit-loss/export?year=${year}&month=${month}&format=csv`
    const token = localStorage.getItem('khi_token')
    // Create link with auth header via fetch
    fetch(url, { headers: { Authorization: `Bearer ${token}` } })
      .then(res => res.blob())
      .then(blob => {
        const a = document.createElement('a')
        a.href = URL.createObjectURL(blob)
        a.download = `laporan-laba-rugi-${year}-${String(month).padStart(2, '0')}.csv`
        a.click()
      })
      .catch(() => toast('Gagal mengunduh laporan', 'error'))
  }

  const months = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
  ]

  // Build chart data from 12-month summary if available
  const chartData = data?.monthly_trend ?? []

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <h1 className="page-title">Laporan Keuangan</h1>
        <div className="flex items-center gap-3">
          <select
            className="form-select"
            value={month}
            onChange={(e) => setMonth(parseInt(e.target.value))}
            style={{ width: 160 }}
          >
            {months.map((m, i) => <option key={i + 1} value={i + 1}>{m}</option>)}
          </select>
          <select
            className="form-select"
            value={year}
            onChange={(e) => setYear(parseInt(e.target.value))}
            style={{ width: 110 }}
          >
            {[now.getFullYear() - 1, now.getFullYear()].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
          <button onClick={() => setExpenseModalOpen(true)} className="btn btn-secondary gap-2">
            <Plus size={16} /> Beban
          </button>
          <button onClick={handleExport} className="btn btn-primary gap-2">
            <Download size={16} /> Ekspor CSV
          </button>
        </div>
      </div>

      {/* P&L Summary */}
      <div className="grid grid-cols-3 gap-6">
        {/* Left: Key metrics */}
        <div className="col-span-1 flex flex-col gap-4">
          {isLoading ? (
            Array.from({ length: 3 }).map((_, i) => <CardSkeleton key={i} />)
          ) : (
            <>
              <div className="stat-card bg-kopi text-white">
                <p className="stat-card-label text-white/60">Omzet Bruto</p>
                <p className="stat-card-value text-white mt-2">{fmtShort(data?.revenue ?? 0)}</p>
                <p className="text-white/60 text-xs mt-1">{fmt(data?.revenue ?? 0)}</p>
              </div>
              <div className={`stat-card ${(data?.net_profit ?? 0) >= 0 ? '' : 'ring-2 ring-kritis/30'}`}>
                <p className="stat-card-label">Laba Bersih</p>
                <p className={`stat-card-value mt-2 ${(data?.net_profit ?? 0) >= 0 ? 'text-laba' : 'text-kritis'}`}>
                  {fmtShort(data?.net_profit ?? 0)}
                </p>
                <div className={`flex items-center gap-1 text-xs font-semibold mt-1 ${(data?.net_margin ?? 0) >= 20 ? 'text-laba' : 'text-kritis'}`}>
                  {(data?.net_margin ?? 0) >= 20 ? <TrendingUp size={12} /> : <TrendingDown size={12} />}
                  Margin bersih {pct(data?.net_margin)}
                </div>
              </div>
              <div className="stat-card">
                <p className="stat-card-label">Nilai Persediaan</p>
                <p className="stat-card-value mt-2">{fmtShort(data?.inventory_value ?? 0)}</p>
                <p className="text-ink-45 text-xs mt-1">Per akhir periode</p>
              </div>
            </>
          )}
        </div>

        {/* Right: P&L breakdown */}
        <div className="col-span-2 card p-6">
          <h2 className="font-display font-bold text-ink mb-4" style={{ fontSize: 17 }}>
            Laporan Laba Rugi — {months[month - 1]} {year}
          </h2>
          {isLoading ? (
            <div className="flex flex-col gap-3">
              {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="skeleton h-4 rounded" style={{ width: `${60 + Math.random() * 40}%` }} />
              ))}
            </div>
          ) : (
            <div className="flex flex-col gap-0">
              <PLRow label="Omzet Bruto" value={data?.revenue} highlight />
              <PLRow label="Total HPP" value={-(data?.total_cogs ?? 0)} indent />
              <PLRow label="Laba Kotor" value={data?.gross_profit} total sub={`Margin ${pct(data?.gross_margin)}`} />

              <div className="h-px bg-line my-3" />
              <p className="text-ink-45 font-bold uppercase text-xs tracking-widest mb-2">Beban Operasional</p>
              {(data?.expenses ?? []).map((exp) => (
                <PLRow key={exp.category} label={exp.category} value={-exp.amount} indent />
              ))}
              <PLRow label="Total Beban" value={-(data?.total_expenses ?? 0)} total />

              <div className="h-px bg-line my-3" />
              <PLRow
                label="Laba Bersih"
                value={data?.net_profit}
                total
                highlight
                sub={`Margin Bersih ${pct(data?.net_margin)}`}
                positive={(data?.net_profit ?? 0) >= 0}
              />
            </div>
          )}
        </div>
      </div>

      {/* Monthly trend chart */}
      {!isLoading && chartData.length > 0 && (
        <div className="card p-5">
          <h2 className="font-display font-bold text-ink mb-4" style={{ fontSize: 17 }}>
            Tren 12 Bulan
          </h2>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#E6E0D8" />
              <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#7A6F66' }} />
              <YAxis tickFormatter={(v) => fmtShort(v)} tick={{ fontSize: 11, fill: '#7A6F66' }} />
              <Tooltip formatter={(v, n) => [fmt(v), n]} />
              <Legend />
              <Bar dataKey="revenue" name="Omzet" fill="#9C4A21" radius={[4, 4, 0, 0]} />
              <Bar dataKey="net_profit" name="Laba Bersih" fill="#1D6B55" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Expense modal */}
      <ExpenseModal
        open={expenseModalOpen}
        onClose={() => setExpenseModalOpen(false)}
        year={year}
        month={month}
        onSuccess={() => { setExpenseModalOpen(false); refetch() }}
      />
    </div>
  )
}

function PLRow({ label, value, indent, total, highlight, sub, positive }) {
  const isNeg = (value ?? 0) < 0
  const color = highlight
    ? (positive !== undefined ? (positive ? 'text-laba' : 'text-kritis') : 'text-ink')
    : isNeg ? 'text-kritis' : 'text-ink'

  return (
    <div className={`flex items-center justify-between py-1.5 ${total ? 'border-t border-line mt-1 pt-2' : ''}`}>
      <div className={indent ? 'pl-4' : ''}>
        <p className={`text-sm ${total ? 'font-bold text-ink' : 'text-ink-70'}`}>{label}</p>
        {sub && <p className="text-ink-45 text-xs">{sub}</p>}
      </div>
      <p className={`tabular text-sm ${total ? 'font-bold text-base' : ''} ${color}`}>
        {fmt(value ?? 0)}
      </p>
    </div>
  )
}

function ExpenseModal({ open, onClose, year, month, onSuccess }) {
  const toast = useToast()
  const [form, setForm] = useState({ category: '', amount: '', note: '' })

  const categories = [
    'Gaji dan Upah', 'Sewa Tempat', 'Listrik, Air, Internet',
    'Hosting dan Domain', 'Perlengkapan Operasional', 'Bahan Habis Pakai', 'Lain-lain'
  ]

  const mutation = useMutation({
    mutationFn: (data) => api.post('/reports/expenses', data),
    onSuccess: () => { toast('Beban dicatat', 'success'); onSuccess() },
    onError: () => toast('Gagal menyimpan beban', 'error'),
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    const period = `${year}-${String(month).padStart(2, '0')}-01`
    mutation.mutate({ period, category: form.category, amount: parseInt(form.amount), note: form.note || null })
  }

  return (
    <Modal open={open} onClose={onClose} title="Tambah Beban Operasional" size="sm">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div>
          <label className="form-label">Kategori *</label>
          <select
            className="form-select"
            value={form.category}
            onChange={(e) => setForm({ ...form, category: e.target.value })}
            required
          >
            <option value="">Pilih kategori</option>
            {categories.map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </div>
        <div>
          <label className="form-label">Jumlah (Rp) *</label>
          <input
            type="number"
            className="form-input"
            value={form.amount}
            onChange={(e) => setForm({ ...form, amount: e.target.value })}
            required min="1"
            placeholder="0"
          />
        </div>
        <div>
          <label className="form-label">Catatan</label>
          <input
            type="text"
            className="form-input"
            value={form.note}
            onChange={(e) => setForm({ ...form, note: e.target.value })}
            placeholder="Opsional"
          />
        </div>
        <div className="flex gap-3 pt-2">
          <button type="button" onClick={onClose} className="btn btn-secondary flex-1">Batal</button>
          <button type="submit" disabled={mutation.isPending} className="btn btn-primary flex-1">
            {mutation.isPending ? 'Menyimpan…' : 'Simpan'}
          </button>
        </div>
      </form>
    </Modal>
  )
}

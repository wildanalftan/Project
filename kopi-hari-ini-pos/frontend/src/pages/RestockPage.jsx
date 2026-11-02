import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Info, Truck } from 'lucide-react'
import api from '../lib/api'
import { fmt, fmtQty, fmtDate } from '../lib/fmt'
import { useToast } from '../components/ui/Toast'
import Modal from '../components/ui/Modal'
import { TableSkeleton, EmptyState } from '../components/ui/States'

export default function RestockPage() {
  const qc    = useQueryClient()
  const toast = useToast()

  const [modalOpen, setModalOpen] = useState(false)

  const { data: restocks = [], isLoading } = useQuery({
    queryKey: ['restocks'],
    queryFn: () => api.get('/restocks').then(r => r.data.data ?? []),
  })

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      <div className="page-header">
        <h1 className="page-title">Restock Bahan</h1>
        <button onClick={() => setModalOpen(true)} className="btn btn-primary gap-2">
          <Plus size={16} /> Tambah Restock
        </button>
      </div>

      {/* HPP info banner */}
      <div className="flex items-start gap-3 p-4 bg-offline-10 border border-offline/30 rounded-card">
        <Info size={18} className="text-offline mt-0.5 flex-shrink-0" />
        <div className="text-sm text-ink-70">
          <p className="font-semibold text-ink">HPP dihitung otomatis saat restock</p>
          <p>Metode rata-rata tertimbang: HPP_baru = (Stok_lama × HPP_lama + Qty_masuk × Harga_satuan) ÷ (Stok_lama + Qty_masuk)</p>
        </div>
      </div>

      <div className="table-wrapper">
        <table className="table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Produk</th>
              <th className="text-right">Qty</th>
              <th className="text-right">Harga Satuan</th>
              <th className="text-right">HPP Sebelum</th>
              <th className="text-right">HPP Sesudah</th>
              <th>Supplier</th>
              <th>Oleh</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <TableSkeleton rows={8} cols={8} />
            ) : restocks.length === 0 ? (
              <tr><td colSpan={8}><EmptyState icon={Truck} title="Belum ada data restock" /></td></tr>
            ) : (
              restocks.map((r) => (
                <tr key={r.id}>
                  <td className="text-ink-45 text-sm">{fmtDate(r.received_at)}</td>
                  <td>
                    <p className="font-medium text-ink">{r.product_name}</p>
                  </td>
                  <td className="text-right tabular font-semibold">{fmtQty(r.qty, '')}</td>
                  <td className="text-right tabular">{fmt(r.unit_cost)}</td>
                  <td className="text-right tabular text-ink-45">{fmt(r.cost_before)}</td>
                  <td className="text-right tabular font-semibold text-kopi">{fmt(r.cost_after)}</td>
                  <td className="text-ink-70">{r.supplier ?? '—'}</td>
                  <td className="text-ink-45 text-sm">{r.user_name}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <RestockFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSuccess={() => {
          setModalOpen(false)
          qc.invalidateQueries(['restocks'])
          qc.invalidateQueries(['products'])
        }}
      />
    </div>
  )
}

function RestockFormModal({ open, onClose, onSuccess }) {
  const toast = useToast()
  const [form, setForm] = useState({ product_id: '', qty: '', unit_cost: '', supplier: '', note: '' })
  const [preview, setPreview] = useState(null)
  const [previewLoading, setPreviewLoading] = useState(false)

  const { data: products = [] } = useQuery({
    queryKey: ['products-list'],
    queryFn: () => api.get('/products').then(r => r.data.data ?? []),
    enabled: open,
  })

  const fetchPreview = async () => {
    if (!form.product_id || !form.qty || !form.unit_cost) return
    setPreviewLoading(true)
    try {
      const res = await api.post('/restocks/preview', {
        product_id: parseInt(form.product_id),
        qty:        parseFloat(form.qty),
        unit_cost:  parseInt(form.unit_cost),
      })
      setPreview(res.data.data)
    } catch {}
    finally { setPreviewLoading(false) }
  }

  const mutation = useMutation({
    mutationFn: (data) => api.post('/restocks', data),
    onSuccess: () => { toast('Restock berhasil dicatat', 'success'); onSuccess() },
    onError: (err) => toast(err?.response?.data?.message ?? 'Gagal menyimpan', 'error'),
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    mutation.mutate({
      product_id: parseInt(form.product_id),
      qty:        parseFloat(form.qty),
      unit_cost:  parseInt(form.unit_cost),
      supplier:   form.supplier || null,
      note:       form.note || null,
    })
  }

  return (
    <Modal open={open} onClose={onClose} title="Tambah Restock" size="md">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div>
          <label className="form-label">Produk *</label>
          <select
            className="form-select"
            value={form.product_id}
            onChange={(e) => { setForm({ ...form, product_id: e.target.value }); setPreview(null) }}
            required
          >
            <option value="">Pilih produk</option>
            {products.map((p) => (
              <option key={p.id} value={p.id}>{p.name} — Stok: {parseFloat(p.stock_qty)} {p.unit}</option>
            ))}
          </select>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="form-label">Qty Masuk *</label>
            <input
              type="number"
              className="form-input"
              placeholder="0"
              value={form.qty}
              onChange={(e) => { setForm({ ...form, qty: e.target.value }); setPreview(null) }}
              required min="0.001" step="0.001"
            />
          </div>
          <div>
            <label className="form-label">Harga Satuan (Rp) *</label>
            <input
              type="number"
              className="form-input"
              placeholder="0"
              value={form.unit_cost}
              onChange={(e) => { setForm({ ...form, unit_cost: e.target.value }); setPreview(null) }}
              required min="1"
            />
          </div>
        </div>

        {/* Preview HPP */}
        <button
          type="button"
          onClick={fetchPreview}
          disabled={!form.product_id || !form.qty || !form.unit_cost || previewLoading}
          className="btn btn-secondary btn-sm gap-2"
        >
          {previewLoading ? 'Menghitung…' : 'Hitung Perubahan HPP'}
        </button>

        {preview && (
          <div className="bg-kopi-10 border border-kopi/20 rounded-control p-4 flex flex-col gap-2 text-sm animate-fade-in">
            <p className="font-semibold text-kopi">Prakiraan Perubahan HPP</p>
            <div className="grid grid-cols-3 gap-2 text-center">
              <div>
                <p className="text-ink-45 text-xs">HPP Sekarang</p>
                <p className="font-bold tabular">{fmt(preview.cost_before)}</p>
              </div>
              <div className="flex items-center justify-center text-kopi">→</div>
              <div>
                <p className="text-ink-45 text-xs">HPP Baru</p>
                <p className="font-bold tabular text-kopi">{fmt(preview.cost_after)}</p>
              </div>
            </div>
            <p className="text-ink-45 text-xs text-center">
              Stok baru: {preview.qty_after} {preview.unit}
            </p>
          </div>
        )}

        <div>
          <label className="form-label">Supplier</label>
          <input
            type="text"
            className="form-input"
            placeholder="Nama supplier (opsional)"
            value={form.supplier}
            onChange={(e) => setForm({ ...form, supplier: e.target.value })}
          />
        </div>

        <div>
          <label className="form-label">Catatan</label>
          <input
            type="text"
            className="form-input"
            placeholder="Catatan tambahan (opsional)"
            value={form.note}
            onChange={(e) => setForm({ ...form, note: e.target.value })}
          />
        </div>

        <div className="flex gap-3 pt-2">
          <button type="button" onClick={onClose} className="btn btn-secondary flex-1">Batal</button>
          <button type="submit" disabled={mutation.isPending} className="btn btn-primary flex-1">
            {mutation.isPending ? 'Menyimpan…' : 'Simpan Restock'}
          </button>
        </div>
      </form>
    </Modal>
  )
}

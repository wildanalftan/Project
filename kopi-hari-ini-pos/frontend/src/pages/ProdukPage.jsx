import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Edit2, Power, Package, Upload } from 'lucide-react'
import api from '../lib/api'
import { fmt, fmtQty, pct, stockStatus } from '../lib/fmt'
import { useToast } from '../components/ui/Toast'
import Modal from '../components/ui/Modal'
import { TableSkeleton, EmptyState } from '../components/ui/States'

export default function ProdukPage() {
  const qc    = useQueryClient()
  const toast = useToast()

  const [search, setSearch]       = useState('')
  const [catFilter, setCatFilter] = useState('all')
  const [stockFilter, setStockFilter] = useState('all')
  const [modalOpen, setModalOpen] = useState(false)
  const [editProduct, setEditProduct] = useState(null)

  const { data: products = [], isLoading } = useQuery({
    queryKey: ['products'],
    queryFn: () => api.get('/products').then(r => r.data.data ?? []),
  })

  const { data: categories = [] } = useQuery({
    queryKey: ['categories'],
    queryFn: () => api.get('/categories').then(r => r.data.data ?? []),
    staleTime: Infinity,
  })

  const filtered = products.filter((p) => {
    if (catFilter !== 'all' && p.category_id !== catFilter) return false
    if (stockFilter === 'habis' && parseFloat(p.stock_qty) > 0) return false
    if (stockFilter === 'menipis' && (parseFloat(p.stock_qty) <= 0 || parseFloat(p.stock_qty) >= parseFloat(p.low_stock_threshold))) return false
    if (search && !p.name.toLowerCase().includes(search.toLowerCase()) && !p.sku.includes(search)) return false
    return true
  })

  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }) => api.put(`/products/${id}`, { is_active }),
    onSuccess: () => { qc.invalidateQueries(['products']); toast('Status diperbarui', 'success') },
    onError: () => toast('Gagal memperbarui', 'error'),
  })

  const openNew  = () => { setEditProduct(null); setModalOpen(true) }
  const openEdit = (p) => { setEditProduct(p); setModalOpen(true) }

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <h1 className="page-title">Produk</h1>
        <button onClick={openNew} className="btn btn-primary gap-2">
          <Plus size={16} /> Tambah Produk
        </button>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap">
        <div className="relative flex-1 min-w-48">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink-45" />
          <input
            type="search"
            className="form-input pl-9"
            placeholder="Cari nama atau SKU…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <select
          className="form-select"
          value={catFilter}
          onChange={(e) => setCatFilter(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
          style={{ width: 180 }}
        >
          <option value="all">Semua Kategori</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <select
          className="form-select"
          value={stockFilter}
          onChange={(e) => setStockFilter(e.target.value)}
          style={{ width: 160 }}
        >
          <option value="all">Semua Stok</option>
          <option value="menipis">Menipis</option>
          <option value="habis">Habis</option>
        </select>
      </div>

      {/* Summary pills */}
      <div className="flex gap-3 flex-wrap">
        {['all', 'menipis', 'habis'].map((f) => {
          const counts = {
            all:     products.length,
            menipis: products.filter(p => parseFloat(p.stock_qty) > 0 && parseFloat(p.stock_qty) < parseFloat(p.low_stock_threshold)).length,
            habis:   products.filter(p => parseFloat(p.stock_qty) <= 0).length,
          }
          const labels = { all: `Semua (${counts.all})`, menipis: `Menipis (${counts.menipis})`, habis: `Habis (${counts.habis})` }
          return (
            <button key={f} className={`chip ${stockFilter === f ? 'active' : ''}`} onClick={() => setStockFilter(f)}>
              {labels[f]}
            </button>
          )
        })}
      </div>

      {/* Table */}
      <div className="table-wrapper">
        <table className="table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Nama Produk</th>
              <th>Kategori</th>
              <th className="text-right">Harga Jual</th>
              <th className="text-right">HPP</th>
              <th className="text-right">Margin</th>
              <th className="text-right">Stok</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <TableSkeleton rows={8} cols={9} />
            ) : filtered.length === 0 ? (
              <tr>
                <td colSpan={9}>
                  <EmptyState icon={Package} title="Produk tidak ditemukan" message="Coba ubah filter pencarian" />
                </td>
              </tr>
            ) : (
              filtered.map((p) => {
                const { label, cls } = stockStatus(p)
                const margin = pct(p.selling_price && p.current_cost ? ((p.selling_price - p.current_cost) / p.selling_price) * 100 : null)
                return (
                  <tr key={p.id}>
                    <td><span className="text-ink-45 text-xs font-mono">{p.sku}</span></td>
                    <td>
                      <p className="font-medium text-ink">{p.name}</p>
                      {!p.is_active && <span className="badge badge-draft">Nonaktif</span>}
                    </td>
                    <td className="text-ink-70">{p.category?.name ?? '—'}</td>
                    <td className="text-right tabular font-semibold">{fmt(p.selling_price)}</td>
                    <td className="text-right tabular text-ink-70">{fmt(p.current_cost)}</td>
                    <td className="text-right tabular text-laba font-semibold">{margin}</td>
                    <td className="text-right tabular">{fmtQty(p.stock_qty, p.unit)}</td>
                    <td><span className={`badge ${cls}`}>{label}</span></td>
                    <td>
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => openEdit(p)}
                          className="btn btn-ghost btn-sm gap-1"
                        >
                          <Edit2 size={14} />
                        </button>
                        <button
                          onClick={() => toggleMutation.mutate({ id: p.id, is_active: !p.is_active })}
                          className={`btn btn-sm gap-1 ${p.is_active ? 'btn-ghost text-ink-45' : 'btn-secondary text-laba'}`}
                          title={p.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                        >
                          <Power size={14} />
                        </button>
                      </div>
                    </td>
                  </tr>
                )
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Product Form Modal */}
      <ProductFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        product={editProduct}
        categories={categories}
        onSuccess={() => {
          setModalOpen(false)
          qc.invalidateQueries(['products'])
        }}
      />
    </div>
  )
}

function ProductFormModal({ open, onClose, product, categories, onSuccess }) {
  const toast = useToast()
  const [form, setForm] = useState({
    category_id:         product?.category_id ?? '',
    sku:                 product?.sku ?? '',
    name:                product?.name ?? '',
    unit:                product?.unit ?? 'pcs',
    selling_price:       product?.selling_price ?? '',
    low_stock_threshold: product?.low_stock_threshold ?? 10,
    is_active:           product?.is_active ?? true,
  })

  const mutation = useMutation({
    mutationFn: (data) =>
      product
        ? api.put(`/products/${product.id}`, data)
        : api.post('/products', data),
    onSuccess: () => {
      toast(product ? 'Produk diperbarui' : 'Produk ditambahkan', 'success')
      onSuccess()
    },
    onError: (err) => {
      const msg = err?.response?.data?.message ?? 'Gagal menyimpan produk'
      toast(msg, 'error')
    },
  })

  // Reset form when product prop changes
  React.useEffect(() => {
    setForm({
      category_id:         product?.category_id ?? '',
      sku:                 product?.sku ?? '',
      name:                product?.name ?? '',
      unit:                product?.unit ?? 'pcs',
      selling_price:       product?.selling_price ?? '',
      low_stock_threshold: product?.low_stock_threshold ?? 10,
      is_active:           product?.is_active ?? true,
    })
  }, [product])

  const handleSubmit = (e) => {
    e.preventDefault()
    mutation.mutate({
      ...form,
      category_id:         parseInt(form.category_id),
      selling_price:       parseInt(form.selling_price),
      low_stock_threshold: parseFloat(form.low_stock_threshold),
    })
  }

  return (
    <Modal open={open} onClose={onClose} title={product ? 'Edit Produk' : 'Tambah Produk'}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="form-label">Kategori *</label>
            <select
              className="form-select"
              value={form.category_id}
              onChange={(e) => setForm({ ...form, category_id: e.target.value })}
              required
            >
              <option value="">Pilih kategori</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div>
            <label className="form-label">SKU *</label>
            <input
              type="text"
              className="form-input"
              value={form.sku}
              onChange={(e) => setForm({ ...form, sku: e.target.value })}
              required
              placeholder="KHI-0001"
            />
          </div>
        </div>

        <div>
          <label className="form-label">Nama Produk *</label>
          <input
            type="text"
            className="form-input"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
            placeholder="Kopi Susu Gula Aren"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="form-label">Satuan *</label>
            <select
              className="form-select"
              value={form.unit}
              onChange={(e) => setForm({ ...form, unit: e.target.value })}
            >
              <option value="pcs">pcs</option>
              <option value="kg">kg</option>
              <option value="L">L</option>
              <option value="gram">gram</option>
            </select>
          </div>
          <div>
            <label className="form-label">Stok Minimum</label>
            <input
              type="number"
              className="form-input"
              value={form.low_stock_threshold}
              onChange={(e) => setForm({ ...form, low_stock_threshold: e.target.value })}
              min="0"
            />
          </div>
        </div>

        <div>
          <label className="form-label">Harga Jual (Rp) *</label>
          <input
            type="number"
            className="form-input"
            value={form.selling_price}
            onChange={(e) => setForm({ ...form, selling_price: e.target.value })}
            required
            min="1"
            placeholder="22000"
          />
        </div>

        <div className="flex items-center gap-3 p-3 bg-ground rounded-control">
          <input
            type="checkbox"
            id="is_active"
            checked={form.is_active}
            onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
            className="w-4 h-4 accent-kopi"
          />
          <label htmlFor="is_active" className="text-sm font-medium text-ink cursor-pointer">
            Produk aktif (tampil di kasir)
          </label>
        </div>

        <div className="flex gap-3 pt-2">
          <button type="button" onClick={onClose} className="btn btn-secondary flex-1">Batal</button>
          <button type="submit" disabled={mutation.isPending} className="btn btn-primary flex-1">
            {mutation.isPending ? 'Menyimpan…' : product ? 'Simpan Perubahan' : 'Tambah Produk'}
          </button>
        </div>
      </form>
    </Modal>
  )
}

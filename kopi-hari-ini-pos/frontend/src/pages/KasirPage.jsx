import React, { useState, useCallback } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Search, Plus, Minus, Trash2, ShoppingCart, Printer, Wifi, WifiOff, RefreshCw } from 'lucide-react'
import api from '../lib/api'
import { fmt, fmtQty, stockStatus } from '../lib/fmt'
import { useNetworkStore } from '../store/network'
import { useToast } from '../components/ui/Toast'
import { cacheProducts, getCachedProducts, queueOfflineTransaction, getPendingTransactions, removePendingTransaction } from '../lib/db'
import Modal from '../components/ui/Modal'
import { v4 as uuidv4 } from 'uuid'

const PAYMENT_METHODS = [
  { id: 'tunai', label: 'Tunai' },
  { id: 'qris',  label: 'QRIS' },
  { id: 'kartu', label: 'Kartu' },
]

export default function KasirPage() {
  const qc        = useQueryClient()
  const toast     = useToast()
  const { isOnline, refreshPendingCount, pendingCount } = useNetworkStore()

  const [search, setSearch]           = useState('')
  const [activeCategory, setCategory] = useState('all')
  const [cart, setCart]               = useState([])
  const [payMethod, setPayMethod]     = useState('tunai')
  const [paidAmount, setPaidAmount]   = useState('')
  const [tableLabel, setTableLabel]   = useState('')
  const [receiptOpen, setReceiptOpen] = useState(false)
  const [lastTx, setLastTx]           = useState(null)

  // Fetch products
  const { data: products = [], isLoading } = useQuery({
    queryKey: ['pos-products'],
    queryFn: async () => {
      if (!isOnline) return getCachedProducts()
      const res = await api.get('/products/pos')
      const prods = res.data.data ?? []
      await cacheProducts(prods)
      return prods
    },
    staleTime: 1000 * 60 * 5,
  })

  // Fetch categories
  const { data: categories = [] } = useQuery({
    queryKey: ['categories'],
    queryFn: () => api.get('/categories').then(r => r.data.data ?? []),
    staleTime: Infinity,
  })

  // Filter products
  const filtered = products.filter((p) => {
    const matchCat    = activeCategory === 'all' || p.category_id === activeCategory
    const matchSearch = !search || p.name.toLowerCase().includes(search.toLowerCase()) || p.sku.includes(search)
    return matchCat && matchSearch && p.is_active
  })

  // Cart logic
  const addToCart = useCallback((product) => {
    if (parseFloat(product.stock_qty) <= 0) {
      toast('Stok produk ini habis', 'warning')
      return
    }
    setCart((prev) => {
      const existing = prev.find((i) => i.product.id === product.id)
      if (existing) {
        const newQty = existing.qty + 1
        if (newQty > parseFloat(product.stock_qty)) {
          toast('Stok tidak mencukupi', 'warning')
          return prev
        }
        return prev.map((i) => i.product.id === product.id ? { ...i, qty: newQty } : i)
      }
      return [...prev, { product, qty: 1 }]
    })
  }, [toast])

  const updateQty = (productId, delta) => {
    setCart((prev) =>
      prev
        .map((i) => i.product.id === productId ? { ...i, qty: i.qty + delta } : i)
        .filter((i) => i.qty > 0)
    )
  }

  const removeFromCart = (productId) => setCart((prev) => prev.filter((i) => i.product.id !== productId))
  const clearCart = () => { setCart([]); setPaidAmount(''); setTableLabel('') }

  // Totals
  const subtotal   = cart.reduce((s, i) => s + i.product.selling_price * i.qty, 0)
  const taxAmount  = Math.round(subtotal * 0.1)
  const total      = subtotal + taxAmount
  const change     = payMethod === 'tunai' ? Math.max(0, parseInt(paidAmount || 0) - total) : 0
  const paidEnough = payMethod !== 'tunai' || parseInt(paidAmount || 0) >= total

  // Checkout
  const checkoutMutation = useMutation({
    mutationFn: async (payload) => {
      if (!isOnline) {
        await queueOfflineTransaction({ ...payload, is_offline: true })
        await refreshPendingCount()
        return { offline: true }
      }
      const res = await api.post('/transactions', payload)
      return res.data.data
    },
    onSuccess: (data) => {
      if (data.offline) {
        toast('Transaksi disimpan offline. Akan disinkron saat online.', 'warning')
        setLastTx({ invoice_no: 'OFFLINE-' + Date.now(), total, items: [...cart] })
      } else {
        toast('Transaksi berhasil!', 'success')
        setLastTx(data)
      }
      setReceiptOpen(true)
      qc.invalidateQueries(['pos-products'])
      clearCart()
    },
    onError: (err) => {
      const msg = err?.response?.data?.message ?? 'Gagal memproses transaksi'
      toast(msg, 'error')
    },
  })

  const handleCheckout = () => {
    if (!cart.length) return
    if (!paidEnough) { toast('Uang diterima kurang dari total', 'warning'); return }
    checkoutMutation.mutate({
      client_uuid:    uuidv4(),
      payment_method: payMethod,
      paid_amount:    payMethod === 'tunai' ? parseInt(paidAmount) : total,
      table_label:    tableLabel || null,
      items: cart.map((i) => ({ product_id: i.product.id, qty: i.qty })),
    })
  }

  // Sync pending offline transactions
  const syncMutation = useMutation({
    mutationFn: async () => {
      const pending = await getPendingTransactions()
      if (!pending.length) return []
      const res = await api.post('/transactions/sync', { transactions: pending })
      for (const result of res.data.data ?? []) {
        if (result.status === 'accepted' || result.status === 'duplicate') {
          const tx = pending.find(p => p.client_uuid === result.client_uuid)
          if (tx) await removePendingTransaction(tx.id)
        }
      }
      await refreshPendingCount()
      return res.data.data
    },
    onSuccess: (results) => {
      const accepted = results.filter(r => r.status === 'accepted').length
      toast(`${accepted} transaksi offline berhasil disinkron`, 'success')
      qc.invalidateQueries(['pos-products'])
    },
    onError: () => toast('Gagal sinkronisasi', 'error'),
  })

  return (
    <div className="flex gap-6 h-full" style={{ minHeight: 'calc(100vh - 64px)' }}>
      {/* ─── LEFT: Product grid ─── */}
      <div className="flex-1 flex flex-col gap-4">
        <div className="page-header mb-0">
          <h1 className="page-title">Kasir</h1>
          <div className="flex items-center gap-3">
            {isOnline ? (
              <span className="conn-online"><Wifi size={14} /> Online</span>
            ) : (
              <span className="conn-offline"><WifiOff size={14} /> Offline</span>
            )}
            {isOnline && pendingCount > 0 && (
              <button
                onClick={() => syncMutation.mutate()}
                disabled={syncMutation.isPending}
                className="btn btn-secondary btn-sm gap-1.5"
              >
                <RefreshCw size={14} className={syncMutation.isPending ? 'animate-spin' : ''} />
                Sinkron ({pendingCount})
              </button>
            )}
          </div>
        </div>

        {/* Search + filters */}
        <div className="flex gap-3 flex-wrap">
          <div className="relative flex-1 min-w-48">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink-45" />
            <input
              type="search"
              className="form-input pl-9"
              placeholder="Cari produk atau SKU…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <div className="flex gap-2 flex-wrap">
            <button
              className={`chip ${activeCategory === 'all' ? 'active' : ''}`}
              onClick={() => setCategory('all')}
            >
              Semua
            </button>
            {categories.map((c) => (
              <button
                key={c.id}
                className={`chip ${activeCategory === c.id ? 'active' : ''}`}
                onClick={() => setCategory(c.id)}
              >
                {c.name}
              </button>
            ))}
          </div>
        </div>

        {/* Product grid */}
        <div className="grid gap-3" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))' }}>
          {isLoading ? (
            Array.from({ length: 12 }).map((_, i) => (
              <div key={i} className="card p-4 flex flex-col gap-2">
                <div className="skeleton h-3 w-3/4 rounded" />
                <div className="skeleton h-5 w-1/2 rounded" />
              </div>
            ))
          ) : filtered.length === 0 ? (
            <div className="col-span-full py-12 text-center text-ink-45">
              <ShoppingCart size={32} className="mx-auto mb-2 text-line" />
              <p className="text-sm">Produk tidak ditemukan</p>
            </div>
          ) : (
            filtered.map((p) => {
              const { label, cls } = stockStatus(p)
              const inCart = cart.find((i) => i.product.id === p.id)
              return (
                <button
                  key={p.id}
                  onClick={() => addToCart(p)}
                  disabled={parseFloat(p.stock_qty) <= 0}
                  className={`card-hover p-4 text-left flex flex-col gap-1.5 transition-all active:scale-95
                    ${parseFloat(p.stock_qty) <= 0 ? 'opacity-50 cursor-not-allowed' : ''}
                    ${inCart ? 'ring-2 ring-kopi' : ''}
                  `}
                >
                  <span className={`badge ${cls} self-start`}>{label}</span>
                  <p className="font-semibold text-ink leading-tight text-sm line-clamp-2">{p.name}</p>
                  <p className="font-display font-bold text-kopi tabular" style={{ fontSize: 15 }}>
                    {fmt(p.selling_price)}
                  </p>
                  {inCart && (
                    <span className="badge badge-locked self-end">×{inCart.qty}</span>
                  )}
                </button>
              )
            })
          )}
        </div>
      </div>

      {/* ─── RIGHT: Cart ─── */}
      <div className="w-80 flex flex-col gap-4 flex-shrink-0">
        <div className="card flex flex-col flex-1" style={{ minHeight: 500 }}>
          {/* Cart header */}
          <div className="px-5 py-4 border-b border-line flex items-center justify-between">
            <div className="flex items-center gap-2">
              <ShoppingCart size={18} className="text-kopi" />
              <span className="font-semibold text-ink">Keranjang</span>
              {cart.length > 0 && (
                <span className="badge badge-locked">{cart.length}</span>
              )}
            </div>
            {cart.length > 0 && (
              <button onClick={clearCart} className="text-ink-45 hover:text-kritis transition-colors">
                <Trash2 size={16} />
              </button>
            )}
          </div>

          {/* Cart items */}
          <div className="flex-1 overflow-y-auto p-4 flex flex-col gap-2">
            {cart.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-full text-center py-8 gap-2">
                <ShoppingCart size={32} className="text-line" />
                <p className="text-ink-45 text-sm">Pilih produk untuk ditambahkan</p>
              </div>
            ) : (
              cart.map((item) => (
                <div key={item.product.id} className="flex items-center gap-3 py-2 border-b border-line/50">
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-ink text-sm truncate">{item.product.name}</p>
                    <p className="text-ink-45 text-xs tabular">{fmt(item.product.selling_price)} × {item.qty}</p>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <button
                      onClick={() => updateQty(item.product.id, -1)}
                      className="w-7 h-7 rounded-full bg-ground flex items-center justify-center text-ink-70 hover:bg-line transition-colors"
                    >
                      <Minus size={13} />
                    </button>
                    <span className="w-6 text-center font-semibold text-sm tabular">{item.qty}</span>
                    <button
                      onClick={() => updateQty(item.product.id, 1)}
                      className="w-7 h-7 rounded-full bg-ground flex items-center justify-center text-ink-70 hover:bg-line transition-colors"
                    >
                      <Plus size={13} />
                    </button>
                    <button
                      onClick={() => removeFromCart(item.product.id)}
                      className="w-7 h-7 flex items-center justify-center text-ink-45 hover:text-kritis transition-colors ml-1"
                    >
                      <Trash2 size={13} />
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Cart summary + checkout */}
          {cart.length > 0 && (
            <div className="p-4 border-t border-line flex flex-col gap-3">
              {/* Table label */}
              <input
                type="text"
                className="form-input"
                placeholder="Label meja (opsional)"
                value={tableLabel}
                onChange={(e) => setTableLabel(e.target.value)}
                style={{ height: 38 }}
              />

              {/* Totals */}
              <div className="flex flex-col gap-1 text-sm">
                <div className="flex justify-between text-ink-70">
                  <span>Subtotal</span>
                  <span className="tabular">{fmt(subtotal)}</span>
                </div>
                <div className="flex justify-between text-ink-70">
                  <span>Pajak (10%)</span>
                  <span className="tabular">{fmt(taxAmount)}</span>
                </div>
                <div className="flex justify-between font-bold text-ink text-base border-t border-line pt-1 mt-1">
                  <span>Total</span>
                  <span className="tabular font-display">{fmt(total)}</span>
                </div>
              </div>

              {/* Payment method */}
              <div className="flex gap-2">
                {PAYMENT_METHODS.map((m) => (
                  <button
                    key={m.id}
                    onClick={() => setPayMethod(m.id)}
                    className={`flex-1 py-1.5 rounded-control border text-sm font-semibold transition-all ${
                      payMethod === m.id
                        ? 'bg-kopi text-white border-kopi'
                        : 'border-line text-ink-70 hover:border-kopi hover:text-kopi'
                    }`}
                  >
                    {m.label}
                  </button>
                ))}
              </div>

              {/* Paid amount (only for tunai) */}
              {payMethod === 'tunai' && (
                <div>
                  <input
                    type="number"
                    className="form-input"
                    placeholder="Uang diterima"
                    value={paidAmount}
                    onChange={(e) => setPaidAmount(e.target.value)}
                    style={{ height: 42 }}
                  />
                  {paidAmount && parseInt(paidAmount) >= total && (
                    <p className="text-laba text-sm font-semibold mt-1 tabular">
                      Kembalian: {fmt(change)}
                    </p>
                  )}
                </div>
              )}

              <button
                onClick={handleCheckout}
                disabled={checkoutMutation.isPending || !paidEnough}
                className="btn btn-primary btn-lg"
              >
                {checkoutMutation.isPending ? (
                  <>
                    <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                    Memproses…
                  </>
                ) : (
                  <>
                    <ShoppingCart size={16} />
                    Bayar {fmt(total)}
                  </>
                )}
              </button>
            </div>
          )}
        </div>
      </div>

      {/* ─── Receipt Modal ─── */}
      <Modal open={receiptOpen} onClose={() => setReceiptOpen(false)} title="Struk Pembayaran" size="sm">
        {lastTx && <ReceiptView tx={lastTx} cart={lastTx.items ?? []} onClose={() => setReceiptOpen(false)} />}
      </Modal>
    </div>
  )
}

function ReceiptView({ tx, cart, onClose }) {
  const handlePrint = () => window.print()

  return (
    <div id="receipt-print" className="flex flex-col gap-3">
      <div className="text-center border-b border-line pb-3">
        <p className="font-display font-bold text-ink">Kopi Hari Ini</p>
        <p className="text-ink-45 text-xs">{tx.invoice_no}</p>
        <p className="text-ink-45 text-xs">{new Date().toLocaleString('id-ID')}</p>
      </div>

      <div className="flex flex-col gap-1">
        {cart.map((item, i) => (
          <div key={i} className="flex justify-between text-sm">
            <span className="text-ink-70">{item.product?.name ?? item.product_name} ×{item.qty}</span>
            <span className="tabular text-ink">{fmt((item.product?.selling_price ?? item.unit_price) * item.qty)}</span>
          </div>
        ))}
      </div>

      <div className="border-t border-line pt-2 flex flex-col gap-1 text-sm">
        <div className="flex justify-between">
          <span className="text-ink-70">Pajak (10%)</span>
          <span className="tabular">{fmt(tx.tax_amount)}</span>
        </div>
        <div className="flex justify-between font-bold text-base">
          <span>Total</span>
          <span className="tabular font-display">{fmt(tx.total)}</span>
        </div>
        {tx.change_amount > 0 && (
          <div className="flex justify-between text-laba font-semibold">
            <span>Kembalian</span>
            <span className="tabular">{fmt(tx.change_amount)}</span>
          </div>
        )}
      </div>

      <p className="text-center text-ink-45 text-xs border-t border-line pt-3">
        Terima kasih telah berbelanja!
      </p>

      <div className="flex gap-3 mt-2">
        <button onClick={onClose} className="btn btn-secondary flex-1">Tutup</button>
        <button onClick={handlePrint} className="btn btn-primary flex-1 gap-2">
          <Printer size={16} /> Cetak
        </button>
      </div>
    </div>
  )
}

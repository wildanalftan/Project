import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Lock, ClipboardList, CheckCircle, Clock } from 'lucide-react'
import api from '../lib/api'
import { fmtDate, fmtQty } from '../lib/fmt'
import { useToast } from '../components/ui/Toast'
import Modal from '../components/ui/Modal'
import ConfirmDialog from '../components/ui/ConfirmDialog'
import { TableSkeleton, EmptyState } from '../components/ui/States'

export default function OpnamePage() {
  const qc    = useQueryClient()
  const toast = useToast()

  const [newModalOpen, setNewModalOpen]         = useState(false)
  const [detailSession, setDetailSession]       = useState(null)
  const [confirmLock, setConfirmLock]           = useState(null)

  const { data: sessions = [], isLoading } = useQuery({
    queryKey: ['opname'],
    queryFn: () => api.get('/opname').then(r => r.data.data ?? []),
  })

  const createMutation = useMutation({
    mutationFn: (data) => api.post('/opname', data),
    onSuccess: (res) => {
      qc.invalidateQueries(['opname'])
      setNewModalOpen(false)
      setDetailSession(res.data.data)
      toast('Sesi opname dibuat. Mulai hitung fisik sekarang.', 'success')
    },
    onError: () => toast('Gagal membuat sesi', 'error'),
  })

  const lockMutation = useMutation({
    mutationFn: (id) => api.post(`/opname/${id}/lock`),
    onSuccess: () => {
      qc.invalidateQueries(['opname'])
      setConfirmLock(null)
      setDetailSession(null)
      toast('Sesi dikunci. Stok telah disesuaikan.', 'success')
    },
    onError: () => toast('Gagal mengunci sesi', 'error'),
  })

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      <div className="page-header">
        <h1 className="page-title">Stok Opname</h1>
        <button onClick={() => setNewModalOpen(true)} className="btn btn-primary gap-2">
          <Plus size={16} /> Sesi Baru
        </button>
      </div>

      <div className="table-wrapper">
        <table className="table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Oleh</th>
              <th>Status</th>
              <th>Produk Dihitung</th>
              <th>Selisih</th>
              <th>Catatan</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <TableSkeleton rows={5} cols={7} />
            ) : sessions.length === 0 ? (
              <tr><td colSpan={7}><EmptyState icon={ClipboardList} title="Belum ada sesi opname" message="Buat sesi baru untuk memulai penghitungan stok fisik" /></td></tr>
            ) : (
              sessions.map((s) => (
                <tr key={s.id}>
                  <td className="text-ink-70">{fmtDate(s.session_date)}</td>
                  <td className="text-ink-70">{s.user_name}</td>
                  <td>
                    <span className={`badge ${s.status === 'locked' ? 'badge-locked' : 'badge-draft'}`}>
                      {s.status === 'locked' ? (
                        <><CheckCircle size={12} /> Dikunci</>
                      ) : (
                        <><Clock size={12} /> Draft</>
                      )}
                    </span>
                  </td>
                  <td className="tabular text-ink-70">{s.stats?.counted ?? '—'} / {s.stats?.total_products ?? '—'}</td>
                  <td>
                    {s.stats ? (
                      <span className="text-kritis tabular text-sm font-semibold">
                        -{s.stats.deficit_count} produk
                      </span>
                    ) : '—'}
                  </td>
                  <td className="text-ink-45 text-sm">{s.note ?? '—'}</td>
                  <td>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={async () => {
                          const res = await api.get(`/opname/${s.id}`)
                          setDetailSession(res.data.data)
                        }}
                        className="btn btn-secondary btn-sm"
                      >
                        Detail
                      </button>
                      {s.status === 'draft' && (
                        <button
                          onClick={() => setConfirmLock(s.id)}
                          className="btn btn-sm gap-1 text-kopi border border-kopi hover:bg-kopi-10"
                        >
                          <Lock size={13} /> Kunci
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* New session modal */}
      <Modal open={newModalOpen} onClose={() => setNewModalOpen(false)} title="Buat Sesi Opname Baru" size="sm">
        <div className="flex flex-col gap-4">
          <p className="text-ink-70 text-sm leading-relaxed">
            Sesi opname akan menyimpan snapshot stok sistem saat ini. Anda kemudian bisa mengisi stok fisik untuk setiap produk.
          </p>
          <div>
            <label className="form-label">Tanggal Penghitungan</label>
            <input
              type="date"
              className="form-input"
              defaultValue={new Date().toISOString().slice(0, 10)}
              id="opname-date"
            />
          </div>
          <div>
            <label className="form-label">Catatan (opsional)</label>
            <input type="text" className="form-input" placeholder="Catatan sesi" id="opname-note" />
          </div>
          <div className="flex gap-3 pt-2">
            <button onClick={() => setNewModalOpen(false)} className="btn btn-secondary flex-1">Batal</button>
            <button
              onClick={() => createMutation.mutate({
                session_date: document.getElementById('opname-date').value,
                note: document.getElementById('opname-note').value || null,
              })}
              disabled={createMutation.isPending}
              className="btn btn-primary flex-1"
            >
              {createMutation.isPending ? 'Membuat…' : 'Buat Sesi'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Detail / counting modal */}
      {detailSession && (
        <OpnameDetailModal
          session={detailSession}
          onClose={() => setDetailSession(null)}
          onLock={(id) => setConfirmLock(id)}
          onSaved={(updated) => setDetailSession(updated)}
        />
      )}

      {/* Confirm lock */}
      <ConfirmDialog
        open={!!confirmLock}
        onClose={() => setConfirmLock(null)}
        onConfirm={() => lockMutation.mutate(confirmLock)}
        loading={lockMutation.isPending}
        title="Kunci Sesi Opname?"
        message="Setelah dikunci, stok sistem akan disesuaikan dengan stok fisik. Tindakan ini tidak bisa dibatalkan."
        confirmLabel="Kunci & Sesuaikan Stok"
        danger
      />
    </div>
  )
}

function OpnameDetailModal({ session, onClose, onLock, onSaved }) {
  const toast = useToast()
  const [edits, setEdits] = useState({})
  const [saving, setSaving] = useState(false)

  const handleSave = async () => {
    setSaving(true)
    try {
      const items = (session.items ?? []).map((item) => ({
        product_id:  item.product_id,
        physical_qty: edits[item.product_id] !== undefined
          ? parseFloat(edits[item.product_id])
          : item.physical_qty,
        note: null,
      }))
      const res = await api.put(`/opname/${session.id}/items`, { items })
      onSaved(res.data.data)
      toast('Stok fisik disimpan', 'success')
    } catch {
      toast('Gagal menyimpan', 'error')
    } finally {
      setSaving(false)
    }
  }

  const isLocked = session.status === 'locked'

  return (
    <Modal
      open
      onClose={onClose}
      title={`Opname ${fmtDate(session.session_date)} — ${isLocked ? 'Dikunci' : 'Draft'}`}
      size="xl"
    >
      <div className="flex flex-col gap-4">
        {!isLocked && (
          <p className="text-ink-70 text-sm bg-perhatian-10 border border-perhatian/30 rounded-control px-4 py-3">
            Masukkan jumlah fisik yang dihitung untuk setiap produk. Biarkan kosong jika belum dihitung.
          </p>
        )}

        <div className="table-wrapper max-h-96 overflow-y-auto border-0">
          <table className="table">
            <thead className="sticky top-0">
              <tr>
                <th>Produk</th>
                <th>Satuan</th>
                <th className="text-right">Stok Sistem</th>
                <th className="text-right">Stok Fisik</th>
                <th className="text-right">Selisih</th>
              </tr>
            </thead>
            <tbody>
              {(session.items ?? []).map((item) => {
                const physVal = edits[item.product_id] !== undefined
                  ? edits[item.product_id]
                  : (item.physical_qty ?? '')
                const physical = physVal !== '' ? parseFloat(physVal) : null
                const diff = physical !== null ? physical - parseFloat(item.system_qty) : null

                return (
                  <tr key={item.product_id}>
                    <td>
                      <p className="font-medium text-ink">{item.product_name}</p>
                      <p className="text-ink-45 text-xs">{item.product_sku}</p>
                    </td>
                    <td className="text-ink-70">{item.unit}</td>
                    <td className="text-right tabular">{parseFloat(item.system_qty)}</td>
                    <td className="text-right">
                      {isLocked ? (
                        <span className="tabular">{item.physical_qty ?? '—'}</span>
                      ) : (
                        <input
                          type="number"
                          className="form-input text-right"
                          style={{ width: 100, height: 36 }}
                          placeholder="—"
                          value={physVal}
                          min="0"
                          step="0.001"
                          onChange={(e) => setEdits({ ...edits, [item.product_id]: e.target.value })}
                        />
                      )}
                    </td>
                    <td className="text-right">
                      {diff !== null ? (
                        <span className={`tabular font-semibold ${diff < 0 ? 'text-kritis' : diff > 0 ? 'text-laba' : 'text-ink-45'}`}>
                          {diff > 0 ? '+' : ''}{diff.toFixed(3).replace(/\.?0+$/, '')}
                        </span>
                      ) : '—'}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>

        <div className="flex gap-3 pt-2">
          <button onClick={onClose} className="btn btn-secondary flex-1">Tutup</button>
          {!isLocked && (
            <>
              <button onClick={handleSave} disabled={saving} className="btn btn-secondary flex-1">
                {saving ? 'Menyimpan…' : 'Simpan Draft'}
              </button>
              <button
                onClick={() => onLock(session.id)}
                className="btn flex-1 bg-kopi text-white hover:bg-kopi-dark gap-2"
              >
                <Lock size={15} /> Kunci Sesi
              </button>
            </>
          )}
        </div>
      </div>
    </Modal>
  )
}

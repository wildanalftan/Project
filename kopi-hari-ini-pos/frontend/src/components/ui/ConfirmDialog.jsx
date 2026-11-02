import React from 'react'
import { AlertTriangle } from 'lucide-react'
import Modal from './Modal'

/**
 * ConfirmDialog — modal konfirmasi sebelum tindakan berbahaya.
 * Usage:
 *   <ConfirmDialog
 *     open={open}
 *     onClose={() => setOpen(false)}
 *     onConfirm={handleDelete}
 *     title="Hapus Produk?"
 *     message="Tindakan ini tidak bisa dibatalkan."
 *     danger
 *   />
 */
export default function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title = 'Konfirmasi',
  message,
  confirmLabel = 'Ya, Lanjutkan',
  cancelLabel = 'Batal',
  danger = false,
  loading = false,
}) {
  return (
    <Modal open={open} onClose={onClose} size="sm">
      <div className="flex flex-col items-center text-center gap-4 py-2">
        <div className={`w-14 h-14 rounded-full flex items-center justify-center ${danger ? 'bg-kritis-10' : 'bg-perhatian-10'}`}>
          <AlertTriangle size={28} className={danger ? 'text-kritis' : 'text-perhatian'} />
        </div>
        <div>
          <h3 className="font-display font-bold text-ink mb-2" style={{ fontSize: 18 }}>{title}</h3>
          {message && <p className="text-ink-70 text-sm leading-relaxed">{message}</p>}
        </div>
        <div className="flex gap-3 w-full mt-2">
          <button onClick={onClose} className="btn btn-secondary flex-1">{cancelLabel}</button>
          <button
            onClick={onConfirm}
            disabled={loading}
            className={`btn flex-1 ${danger ? 'btn-danger' : 'btn-primary'}`}
          >
            {loading ? 'Memproses…' : confirmLabel}
          </button>
        </div>
      </div>
    </Modal>
  )
}

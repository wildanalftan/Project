import React from 'react'
import { X } from 'lucide-react'

export default function Modal({ open, onClose, title, children, size = 'md' }) {
  if (!open) return null

  const sizes = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    full: 'max-w-6xl',
  }

  return (
    <div
      className="modal-overlay"
      onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}
    >
      <div className={`modal-box w-full ${sizes[size] ?? sizes.md}`} role="dialog" aria-modal="true">
        {/* Header */}
        {(title || onClose) && (
          <div className="flex items-center justify-between px-6 py-4 border-b border-line">
            {title && (
              <h2 className="font-display font-bold text-ink" style={{ fontSize: 19 }}>
                {title}
              </h2>
            )}
            {onClose && (
              <button
                onClick={onClose}
                className="w-9 h-9 flex items-center justify-center rounded-control text-ink-45 hover:text-ink hover:bg-ground transition-colors"
                aria-label="Tutup"
              >
                <X size={18} />
              </button>
            )}
          </div>
        )}
        {/* Body */}
        <div className="px-6 py-5">{children}</div>
      </div>
    </div>
  )
}

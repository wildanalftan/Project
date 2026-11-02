import React from 'react'

/**
 * Skeleton loading placeholder
 */
export function Skeleton({ className = 'h-4 w-full', count = 1 }) {
  return (
    <>
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className={`skeleton ${className}`} />
      ))}
    </>
  )
}

/**
 * Table row skeletons
 */
export function TableSkeleton({ rows = 6, cols = 5 }) {
  return (
    <>
      {Array.from({ length: rows }).map((_, r) => (
        <tr key={r} className="border-b border-line/60">
          {Array.from({ length: cols }).map((_, c) => (
            <td key={c} className="px-4 py-3">
              <div className="skeleton h-4 rounded" style={{ width: `${60 + Math.random() * 40}%` }} />
            </td>
          ))}
        </tr>
      ))}
    </>
  )
}

/**
 * Card skeleton
 */
export function CardSkeleton({ className = '' }) {
  return (
    <div className={`card p-5 flex flex-col gap-3 ${className}`}>
      <div className="skeleton h-3 w-24 rounded" />
      <div className="skeleton h-7 w-32 rounded" />
      <div className="skeleton h-3 w-20 rounded" />
    </div>
  )
}

/**
 * Empty state
 */
export function EmptyState({ icon: Icon, title, message, action }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      {Icon && <Icon size={40} className="text-line" strokeWidth={1.5} />}
      <h3 className="font-display font-semibold text-ink" style={{ fontSize: 18 }}>{title}</h3>
      {message && <p className="text-ink-45 text-sm max-w-xs">{message}</p>}
      {action}
    </div>
  )
}

/**
 * Error state
 */
export function ErrorState({ message, onRetry }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      <div className="w-12 h-12 rounded-full bg-kritis-10 flex items-center justify-center">
        <span className="text-kritis text-2xl">!</span>
      </div>
      <p className="text-ink-70 text-sm">{message ?? 'Terjadi kesalahan'}</p>
      {onRetry && (
        <button className="btn btn-secondary btn-sm" onClick={onRetry}>
          Coba Lagi
        </button>
      )}
    </div>
  )
}

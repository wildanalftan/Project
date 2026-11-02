import React from 'react'

export default function LoadingScreen() {
  return (
    <div className="fixed inset-0 bg-ground flex items-center justify-center">
      <div className="flex flex-col items-center gap-4">
        <div className="w-12 h-12 rounded-full border-4 border-line border-t-kopi animate-spin" />
        <p className="text-ink-45 font-medium text-sm">Memuat…</p>
      </div>
    </div>
  )
}

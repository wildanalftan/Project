import React, { createContext, useContext, useState, useCallback } from 'react'
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-react'

const ToastContext = createContext(null)

export function useToast() {
  return useContext(ToastContext)
}

let nextId = 0

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])

  const toast = useCallback((message, type = 'info', duration = 3500) => {
    const id = ++nextId
    setToasts((prev) => [...prev, { id, message, type }])
    setTimeout(() => setToasts((prev) => prev.filter((t) => t.id !== id)), duration)
  }, [])

  const dismiss = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div
        aria-live="polite"
        className="fixed bottom-6 right-6 z-[100] flex flex-col gap-2 max-w-sm w-full pointer-events-none"
      >
        {toasts.map((t) => (
          <ToastItem key={t.id} toast={t} onDismiss={dismiss} />
        ))}
      </div>
    </ToastContext.Provider>
  )
}

function ToastItem({ toast, onDismiss }) {
  const config = {
    success: { icon: CheckCircle, cls: 'text-laba', bg: 'bg-laba-10 border-laba/30' },
    error:   { icon: XCircle,     cls: 'text-kritis', bg: 'bg-kritis-10 border-kritis/30' },
    warning: { icon: AlertTriangle, cls: 'text-perhatian', bg: 'bg-perhatian-10 border-perhatian/30' },
    info:    { icon: Info,         cls: 'text-offline', bg: 'bg-offline-10 border-offline/30' },
  }
  const { icon: Icon, cls, bg } = config[toast.type] ?? config.info

  return (
    <div
      className={`pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-card border shadow-card animate-slide-up ${bg}`}
    >
      <Icon size={18} className={`mt-0.5 flex-shrink-0 ${cls}`} />
      <p className="flex-1 text-ink text-sm font-medium leading-snug">{toast.message}</p>
      <button
        onClick={() => onDismiss(toast.id)}
        className={`flex-shrink-0 ${cls} hover:opacity-70 transition-opacity`}
      >
        <X size={16} />
      </button>
    </div>
  )
}

import { create } from 'zustand'
import { getPendingCount } from '../lib/db'

export const useNetworkStore = create((set, get) => ({
  isOnline:      navigator.onLine,
  pendingCount:  0,
  lastSyncAt:    null,

  // Called once in App.jsx
  init: () => {
    const setOnline  = () => set({ isOnline: true })
    const setOffline = () => set({ isOnline: false })
    window.addEventListener('online',  setOnline)
    window.addEventListener('offline', setOffline)

    // Refresh pending count
    get().refreshPendingCount()
  },

  refreshPendingCount: async () => {
    const count = await getPendingCount()
    set({ pendingCount: count })
  },

  setSynced: (ts) => set({ lastSyncAt: ts }),
}))

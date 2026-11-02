import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import api from '../lib/api'

export const useAuthStore = create(
  persist(
    (set, get) => ({
      token: null,
      user:  null,

      login: async (username, password) => {
        const res = await api.post('/auth/login', { username, password })
        const { token, user } = res.data.data
        localStorage.setItem('khi_token', token)
        set({ token, user })
        return user
      },

      logout: async () => {
        try {
          await api.post('/auth/logout')
        } catch {}
        localStorage.removeItem('khi_token')
        localStorage.removeItem('khi_user')
        set({ token: null, user: null })
      },

      isAuthenticated: () => !!get().token,
      hasRole: (...roles) => roles.includes(get().user?.role),
      can: (action) => {
        const role = get().user?.role
        const perms = {
          admin:   ['view_hpp', 'manage_stock', 'manage_users', 'checkout', 'view_report', 'manage_settings', 'opname', 'restock'],
          kasir:   ['checkout'],
          manajer: ['view_hpp', 'view_report'],
        }
        return perms[role]?.includes(action) ?? false
      },
    }),
    {
      name: 'khi-auth',
      partialize: (state) => ({ token: state.token, user: state.user }),
    }
  )
)

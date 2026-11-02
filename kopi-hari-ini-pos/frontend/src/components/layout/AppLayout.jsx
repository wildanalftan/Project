import React from 'react'
import { Outlet, NavLink, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, ShoppingCart, Package, Truck, Calculator,
  BarChart3, ClipboardList, Users, Settings, LogOut,
  Wifi, WifiOff, Coffee
} from 'lucide-react'
import { useAuthStore } from '../../store/auth'
import { useNetworkStore } from '../../store/network'
import { ToastProvider } from '../ui/Toast'

const navGroups = [
  {
    label: 'Operasional',
    items: [
      { to: '/kasir',     icon: ShoppingCart,    label: 'Kasir',       roles: ['admin', 'kasir'] },
      { to: '/dashboard', icon: LayoutDashboard, label: 'Dashboard',   roles: ['admin', 'manajer'] },
    ],
  },
  {
    label: 'Inventori',
    items: [
      { to: '/produk',    icon: Package,         label: 'Produk',      roles: ['admin'] },
      { to: '/restock',   icon: Truck,           label: 'Restock',     roles: ['admin'] },
      { to: '/opname',    icon: ClipboardList,   label: 'Stok Opname', roles: ['admin'] },
    ],
  },
  {
    label: 'Keuangan',
    items: [
      { to: '/hpp',       icon: Calculator,      label: 'HPP',         roles: ['admin', 'manajer'] },
      { to: '/laporan',   icon: BarChart3,       label: 'Laporan',     roles: ['admin', 'manajer'] },
    ],
  },
  {
    label: 'Pengaturan',
    items: [
      { to: '/pengguna',    icon: Users,    label: 'Pengguna',   roles: ['admin'] },
      { to: '/pengaturan',  icon: Settings, label: 'Pengaturan', roles: ['admin'] },
    ],
  },
]

export default function AppLayout() {
  const { user, logout, hasRole } = useAuthStore()
  const { isOnline, pendingCount } = useNetworkStore()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <ToastProvider>
      <div className="flex min-h-screen bg-ground">
        {/* === Sidebar === */}
        <aside className="sidebar">
          {/* Logo */}
          <div className="px-5 py-5 border-b border-white/10 flex items-center gap-3">
            <div className="w-9 h-9 rounded-control bg-kopi flex items-center justify-center flex-shrink-0">
              <Coffee size={18} className="text-white" />
            </div>
            <div>
              <p className="font-display font-bold text-white leading-tight text-sm">Kopi Hari Ini</p>
              <p className="text-white/40 leading-tight" style={{ fontSize: 11 }}>POS System</p>
            </div>
          </div>

          {/* Nav */}
          <nav className="flex-1 overflow-y-auto py-3 flex flex-col gap-0.5">
            {navGroups.map((group) => {
              const visibleItems = group.items.filter((item) =>
                item.roles.some((r) => hasRole(r))
              )
              if (!visibleItems.length) return null
              return (
                <div key={group.label} className="mb-1">
                  <p className="px-6 py-2 text-white/30 font-bold uppercase tracking-widest" style={{ fontSize: 10 }}>
                    {group.label}
                  </p>
                  {visibleItems.map((item) => (
                    <NavLink
                      key={item.to}
                      to={item.to}
                      className={({ isActive }) => `sidebar-item ${isActive ? 'active' : ''}`}
                    >
                      <item.icon size={18} className="flex-shrink-0" />
                      <span>{item.label}</span>
                    </NavLink>
                  ))}
                </div>
              )
            })}
          </nav>

          {/* Footer */}
          <div className="px-4 py-4 border-t border-white/10 flex flex-col gap-3">
            {/* Network status */}
            <div className="flex items-center justify-between px-2">
              {isOnline ? (
                <span className="flex items-center gap-1.5 text-laba text-xs font-semibold">
                  <Wifi size={13} /> Online
                </span>
              ) : (
                <span className="flex items-center gap-1.5 text-offline text-xs font-semibold">
                  <WifiOff size={13} /> Offline
                  {pendingCount > 0 && (
                    <span className="ml-1 bg-offline text-white rounded-full px-1.5 py-0.5" style={{ fontSize: 10 }}>
                      {pendingCount}
                    </span>
                  )}
                </span>
              )}
            </div>

            {/* User info */}
            <div className="flex items-center gap-3 px-2">
              <div className="w-8 h-8 rounded-full bg-kopi flex items-center justify-center flex-shrink-0">
                <span className="text-white font-bold text-sm">{user?.name?.[0]?.toUpperCase()}</span>
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-white font-semibold text-sm truncate">{user?.name}</p>
                <p className="text-white/40 capitalize" style={{ fontSize: 11 }}>
                  {user?.role}
                </p>
              </div>
              <button
                onClick={handleLogout}
                className="w-8 h-8 flex items-center justify-center rounded-control text-white/40 hover:text-white hover:bg-white/10 transition-colors"
                title="Keluar"
              >
                <LogOut size={16} />
              </button>
            </div>
          </div>
        </aside>

        {/* === Main content === */}
        <main className="flex-1 flex flex-col min-w-0" style={{ marginLeft: 260 }}>
          <div className="flex-1 p-6 lg:p-8">
            <Outlet />
          </div>
        </main>
      </div>
    </ToastProvider>
  )
}

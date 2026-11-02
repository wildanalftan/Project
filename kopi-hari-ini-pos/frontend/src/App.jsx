import React, { Suspense, lazy, useEffect } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useAuthStore } from './store/auth'
import { useNetworkStore } from './store/network'
import AppLayout from './components/layout/AppLayout'
import LoginPage from './pages/LoginPage'
import LoadingScreen from './components/ui/LoadingScreen'

// Lazy-loaded pages for code splitting
const DashboardPage   = lazy(() => import('./pages/DashboardPage'))
const KasirPage       = lazy(() => import('./pages/KasirPage'))
const ProdukPage      = lazy(() => import('./pages/ProdukPage'))
const RestockPage     = lazy(() => import('./pages/RestockPage'))
const HppPage         = lazy(() => import('./pages/HppPage'))
const LaporanPage     = lazy(() => import('./pages/LaporanPage'))
const OpnamePage      = lazy(() => import('./pages/OpnamePage'))
const PenggunPage     = lazy(() => import('./pages/PenggunPage'))
const PengaturanPage  = lazy(() => import('./pages/PengaturanPage'))

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 2,     // 2 minutes
      retry: (count, err) => {
        if (err?.response?.status >= 400 && err?.response?.status < 500) return false
        return count < 2
      },
    },
  },
})

function ProtectedRoute({ children, requiredRoles }) {
  const { isAuthenticated, hasRole } = useAuthStore()
  if (!isAuthenticated()) return <Navigate to="/login" replace />
  if (requiredRoles && !hasRole(...requiredRoles)) return <Navigate to="/" replace />
  return children
}

export default function App() {
  const { init } = useNetworkStore()

  useEffect(() => {
    init()
  }, [init])

  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Suspense fallback={<LoadingScreen />}>
          <Routes>
            <Route path="/login" element={<LoginPage />} />

            <Route path="/" element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }>
              {/* Default redirect */}
              <Route index element={<Navigate to="/kasir" replace />} />

              {/* Kasir — all roles (with limited access) */}
              <Route path="kasir" element={
                <ProtectedRoute requiredRoles={['admin', 'kasir']}>
                  <KasirPage />
                </ProtectedRoute>
              } />

              {/* Manager + Admin only */}
              <Route path="dashboard" element={
                <ProtectedRoute requiredRoles={['admin', 'manajer']}>
                  <DashboardPage />
                </ProtectedRoute>
              } />

              {/* Admin only */}
              <Route path="produk"    element={<ProtectedRoute requiredRoles={['admin']}><ProdukPage /></ProtectedRoute>} />
              <Route path="restock"   element={<ProtectedRoute requiredRoles={['admin']}><RestockPage /></ProtectedRoute>} />
              <Route path="opname"    element={<ProtectedRoute requiredRoles={['admin']}><OpnamePage /></ProtectedRoute>} />
              <Route path="pengguna"  element={<ProtectedRoute requiredRoles={['admin']}><PenggunPage /></ProtectedRoute>} />
              <Route path="pengaturan" element={<ProtectedRoute requiredRoles={['admin']}><PengaturanPage /></ProtectedRoute>} />

              {/* Admin + Manajer */}
              <Route path="hpp"       element={<ProtectedRoute requiredRoles={['admin', 'manajer']}><HppPage /></ProtectedRoute>} />
              <Route path="laporan"   element={<ProtectedRoute requiredRoles={['admin', 'manajer']}><LaporanPage /></ProtectedRoute>} />
            </Route>

            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </QueryClientProvider>
  )
}

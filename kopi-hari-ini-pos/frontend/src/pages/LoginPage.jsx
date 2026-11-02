import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Coffee, Eye, EyeOff } from 'lucide-react'
import { useAuthStore } from '../store/auth'

export default function LoginPage() {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [showPw, setShowPw]     = useState(false)
  const [error, setError]       = useState('')
  const [loading, setLoading]   = useState(false)

  const { login, hasRole } = useAuthStore()
  const navigate = useNavigate()

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const user = await login(username, password)
      // Redirect based on role
      if (user.role === 'kasir') navigate('/kasir', { replace: true })
      else navigate('/dashboard', { replace: true })
    } catch (err) {
      const msg = err?.response?.data?.errors?.username?.[0]
        ?? err?.response?.data?.message
        ?? 'Gagal masuk. Periksa username dan password Anda.'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-ground flex items-center justify-center p-4">
      <div className="w-full max-w-sm animate-slide-up">
        {/* Brand */}
        <div className="text-center mb-8">
          <div className="w-16 h-16 rounded-2xl bg-ink mx-auto flex items-center justify-center mb-4 shadow-card">
            <Coffee size={30} className="text-kopi" />
          </div>
          <h1 className="font-display font-bold text-ink mb-1" style={{ fontSize: 28 }}>
            Kopi Hari Ini
          </h1>
          <p className="text-ink-45 text-sm">Sistem POS & HPP Otomatis</p>
        </div>

        {/* Card */}
        <div className="card p-6">
          <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
            <div>
              <label htmlFor="username" className="form-label">Username</label>
              <input
                id="username"
                type="text"
                className="form-input"
                placeholder="contoh: kasir01"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                required
                autoComplete="username"
                autoFocus
              />
            </div>

            <div>
              <label htmlFor="password" className="form-label">Password</label>
              <div className="relative">
                <input
                  id="password"
                  type={showPw ? 'text' : 'password'}
                  className="form-input pr-12"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  autoComplete="current-password"
                />
                <button
                  type="button"
                  onClick={() => setShowPw(!showPw)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-ink-45 hover:text-ink transition-colors"
                >
                  {showPw ? <EyeOff size={17} /> : <Eye size={17} />}
                </button>
              </div>
            </div>

            {error && (
              <div className="bg-kritis-10 border border-kritis/30 rounded-control px-4 py-3 text-kritis text-sm font-medium animate-fade-in">
                {error}
              </div>
            )}

            <button
              type="submit"
              disabled={loading || !username || !password}
              className="btn btn-primary btn-lg mt-1"
            >
              {loading ? (
                <>
                  <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                  Masuk…
                </>
              ) : 'Masuk'}
            </button>
          </form>
        </div>

        {/* Demo hint */}
        <p className="text-center text-ink-45 text-xs mt-4">
          Demo: kasir01 / password123
        </p>
      </div>
    </div>
  )
}

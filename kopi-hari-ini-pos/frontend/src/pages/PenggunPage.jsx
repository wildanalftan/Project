import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit2, Power, Users } from 'lucide-react'
import api from '../lib/api'
import { useToast } from '../components/ui/Toast'
import Modal from '../components/ui/Modal'
import { TableSkeleton, EmptyState } from '../components/ui/States'

const ROLE_LABELS = { admin: 'Admin', kasir: 'Kasir', manajer: 'Manajer Keuangan' }

export default function PenggunPage() {
  const qc    = useQueryClient()
  const toast = useToast()

  const [modalOpen, setModalOpen] = useState(false)
  const [editUser, setEditUser]   = useState(null)

  const { data: users = [], isLoading } = useQuery({
    queryKey: ['users'],
    queryFn: () => api.get('/users').then(r => r.data.data ?? []),
  })

  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }) => api.put(`/users/${id}`, { is_active }),
    onSuccess: () => { qc.invalidateQueries(['users']); toast('Status diperbarui', 'success') },
    onError: () => toast('Gagal memperbarui', 'error'),
  })

  const roleColors = { admin: 'badge-locked', kasir: 'badge-sale', manajer: 'badge-offline' }

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      <div className="page-header">
        <h1 className="page-title">Pengguna</h1>
        <button onClick={() => { setEditUser(null); setModalOpen(true) }} className="btn btn-primary gap-2">
          <Plus size={16} /> Tambah Pengguna
        </button>
      </div>

      <div className="table-wrapper">
        <table className="table">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Username</th>
              <th>Peran</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <TableSkeleton rows={4} cols={5} />
            ) : users.length === 0 ? (
              <tr><td colSpan={5}><EmptyState icon={Users} title="Belum ada pengguna" /></td></tr>
            ) : (
              users.map((u) => (
                <tr key={u.id}>
                  <td className="font-medium text-ink">{u.name}</td>
                  <td className="font-mono text-sm text-ink-70">{u.username}</td>
                  <td><span className={`badge ${roleColors[u.role]}`}>{ROLE_LABELS[u.role]}</span></td>
                  <td>
                    <span className={`badge ${u.is_active ? 'badge-aman' : 'badge-draft'}`}>
                      {u.is_active ? 'Aktif' : 'Nonaktif'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => { setEditUser(u); setModalOpen(true) }}
                        className="btn btn-ghost btn-sm"
                      >
                        <Edit2 size={14} />
                      </button>
                      <button
                        onClick={() => toggleMutation.mutate({ id: u.id, is_active: !u.is_active })}
                        className={`btn btn-sm ${u.is_active ? 'btn-ghost text-ink-45' : 'btn-secondary text-laba'}`}
                        title={u.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                      >
                        <Power size={14} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <UserFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        user={editUser}
        onSuccess={() => { setModalOpen(false); qc.invalidateQueries(['users']) }}
      />
    </div>
  )
}

function UserFormModal({ open, onClose, user, onSuccess }) {
  const toast = useToast()
  const [form, setForm] = useState({
    name: user?.name ?? '',
    username: user?.username ?? '',
    password: '',
    role: user?.role ?? 'kasir',
    is_active: user?.is_active ?? true,
  })

  React.useEffect(() => {
    setForm({
      name: user?.name ?? '',
      username: user?.username ?? '',
      password: '',
      role: user?.role ?? 'kasir',
      is_active: user?.is_active ?? true,
    })
  }, [user])

  const mutation = useMutation({
    mutationFn: (data) => user ? api.put(`/users/${user.id}`, data) : api.post('/users', data),
    onSuccess: () => {
      toast(user ? 'Pengguna diperbarui' : 'Pengguna ditambahkan', 'success')
      onSuccess()
    },
    onError: (err) => toast(err?.response?.data?.message ?? 'Gagal menyimpan', 'error'),
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    const data = { ...form }
    if (!data.password) delete data.password
    mutation.mutate(data)
  }

  return (
    <Modal open={open} onClose={onClose} title={user ? 'Edit Pengguna' : 'Tambah Pengguna'} size="sm">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div>
          <label className="form-label">Nama Lengkap *</label>
          <input
            type="text"
            className="form-input"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
            placeholder="Dewi Lestari"
          />
        </div>
        <div>
          <label className="form-label">Username *</label>
          <input
            type="text"
            className="form-input"
            value={form.username}
            onChange={(e) => setForm({ ...form, username: e.target.value })}
            required
            placeholder="kasir01"
          />
        </div>
        <div>
          <label className="form-label">{user ? 'Password Baru (biarkan kosong = tidak berubah)' : 'Password *'}</label>
          <input
            type="password"
            className="form-input"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required={!user}
            placeholder="••••••••"
            minLength={8}
          />
        </div>
        <div>
          <label className="form-label">Peran *</label>
          <select
            className="form-select"
            value={form.role}
            onChange={(e) => setForm({ ...form, role: e.target.value })}
          >
            <option value="kasir">Kasir</option>
            <option value="admin">Admin</option>
            <option value="manajer">Manajer Keuangan</option>
          </select>
        </div>
        <div className="flex items-center gap-3 p-3 bg-ground rounded-control">
          <input
            type="checkbox"
            id="user_active"
            checked={form.is_active}
            onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
            className="w-4 h-4 accent-kopi"
          />
          <label htmlFor="user_active" className="text-sm font-medium text-ink cursor-pointer">
            Akun aktif (bisa login)
          </label>
        </div>
        <div className="flex gap-3 pt-2">
          <button type="button" onClick={onClose} className="btn btn-secondary flex-1">Batal</button>
          <button type="submit" disabled={mutation.isPending} className="btn btn-primary flex-1">
            {mutation.isPending ? 'Menyimpan…' : user ? 'Simpan Perubahan' : 'Tambah Pengguna'}
          </button>
        </div>
      </form>
    </Modal>
  )
}

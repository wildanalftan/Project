import React, { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { Save, Settings } from 'lucide-react'
import api from '../lib/api'
import { useToast } from '../components/ui/Toast'

export default function PengaturanPage() {
  const toast = useToast()
  const [form, setForm] = useState(null)

  const { isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings').then(r => r.data.data),
    onSuccess: (data) => setForm(data),
  })

  const mutation = useMutation({
    mutationFn: (data) => api.put('/settings', data),
    onSuccess: (res) => {
      setForm(res.data.data)
      toast('Pengaturan disimpan', 'success')
    },
    onError: () => toast('Gagal menyimpan pengaturan', 'error'),
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    mutation.mutate(form)
  }

  if (isLoading || !form) {
    return (
      <div className="flex items-center justify-center py-24">
        <div className="w-8 h-8 border-4 border-line border-t-kopi rounded-full animate-spin" />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6 animate-fade-in max-w-2xl">
      <div className="page-header">
        <h1 className="page-title">Pengaturan</h1>
      </div>

      <form onSubmit={handleSubmit} className="flex flex-col gap-6">
        {/* Store info */}
        <div className="card p-6 flex flex-col gap-4">
          <h2 className="font-display font-semibold text-ink" style={{ fontSize: 17 }}>
            Informasi Toko
          </h2>
          <div>
            <label className="form-label">Nama Toko</label>
            <input
              type="text"
              className="form-input"
              value={form.store_name ?? ''}
              onChange={(e) => setForm({ ...form, store_name: e.target.value })}
            />
          </div>
          <div>
            <label className="form-label">Alamat</label>
            <input
              type="text"
              className="form-input"
              value={form.store_address ?? ''}
              onChange={(e) => setForm({ ...form, store_address: e.target.value })}
            />
          </div>
          <div>
            <label className="form-label">Nomor Telepon</label>
            <input
              type="text"
              className="form-input"
              value={form.store_phone ?? ''}
              onChange={(e) => setForm({ ...form, store_phone: e.target.value })}
            />
          </div>
          <div>
            <label className="form-label">Pesan Bawah Struk</label>
            <input
              type="text"
              className="form-input"
              value={form.receipt_footer ?? ''}
              onChange={(e) => setForm({ ...form, receipt_footer: e.target.value })}
              placeholder="Terima kasih telah berbelanja!"
            />
          </div>
        </div>

        {/* Financial settings */}
        <div className="card p-6 flex flex-col gap-4">
          <h2 className="font-display font-semibold text-ink" style={{ fontSize: 17 }}>
            Keuangan & HPP
          </h2>
          <div>
            <label className="form-label">Pajak (%)</label>
            <input
              type="number"
              className="form-input"
              value={form.tax_percent ?? '10'}
              onChange={(e) => setForm({ ...form, tax_percent: e.target.value })}
              min="0" max="100" step="0.5"
              style={{ width: 120 }}
            />
            <p className="text-ink-45 text-xs mt-1">Pajak ditambahkan ke subtotal setiap transaksi</p>
          </div>

          <div>
            <label className="form-label">Metode Perhitungan HPP</label>
            <div className="flex gap-3 mt-1">
              {[
                { value: 'weighted_average', label: 'Rata-rata Tertimbang', desc: 'HPP diperbarui saat restock menggunakan formula rata-rata' },
                { value: 'fifo', label: 'FIFO', desc: 'Stok masuk pertama digunakan pertama. Akurat untuk bahan cepat kadaluarsa.' },
              ].map((m) => (
                <label
                  key={m.value}
                  className={`flex-1 p-4 rounded-card border-2 cursor-pointer transition-all ${
                    form.hpp_method === m.value
                      ? 'border-kopi bg-kopi-10'
                      : 'border-line hover:border-kopi/40'
                  }`}
                >
                  <input
                    type="radio"
                    name="hpp_method"
                    value={m.value}
                    checked={form.hpp_method === m.value}
                    onChange={(e) => setForm({ ...form, hpp_method: e.target.value })}
                    className="sr-only"
                  />
                  <p className={`font-semibold text-sm ${form.hpp_method === m.value ? 'text-kopi' : 'text-ink'}`}>
                    {m.label}
                  </p>
                  <p className="text-ink-45 text-xs mt-1">{m.desc}</p>
                </label>
              ))}
            </div>
          </div>
        </div>

        <button type="submit" disabled={mutation.isPending} className="btn btn-primary gap-2 self-start">
          {mutation.isPending ? (
            <>
              <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
              Menyimpan…
            </>
          ) : (
            <><Save size={16} /> Simpan Pengaturan</>
          )}
        </button>
      </form>
    </div>
  )
}

# Prompt Lanjutan — Sistem POS & HPP "Kopi Hari Ini" (Sesi 2)

> Prompt ini melanjutkan pekerjaan dari sesi sebelumnya. Spesifikasi lengkap ada di file `prompt-antigravity-pos-kopi-hari-ini.md` dalam folder yang sama.

---

## 1. Konteks proyek

Kamu melanjutkan pembangunan Sistem POS & HPP otomatis untuk kedai kopi UMKM "Kopi Hari Ini". Sesi sebelumnya sudah mengerjakan **backend ~95%** dan **frontend ~85%**. Tugasmu sekarang: **perbaiki bug yang ditemukan, selesaikan sisa pekerjaan, dan pastikan semua fitur end-to-end berjalan**.

Lokasi proyek: `c:\Users\alfat\OneDrive\Documents\protein\Project\kopi-hari-ini-pos\`

---

## 2. Apa yang sudah selesai

### 2.1 Backend (Laravel 11 + PHP 8.2 + MySQL 8)

**Models (12 file):**
- `User`, `Category`, `Product`, `Restock`, `StockBatch`, `Transaction`, `TransactionItem`, `StockMovement`, `OpnameSession`, `OpnameItem`, `Setting`, `OperatingExpense`
- Semua di `backend/app/Models/`

**Services (3 file):**
- `HppService` — Weighted Average + FIFO, `calculateWeightedAverage()`, `calculateFifo()`
- `StockService` — `recordRestock()`, `deductForSale()`, `adjustForOpname()`, `getTotalInventoryValue()`
- `ReportService` — `getDashboardData()`, `getProfitLoss()`, aggregasi, chart data
- Semua di `backend/app/Services/`

**Controllers (9 file):**
- `AuthController` — login/logout/me dengan Sanctum token
- `ProductController` — CRUD + posProducts + import CSV + summary
- `RestockController` — index/store/preview
- `TransactionController` — index/show/store/sync (idempotent via client_uuid)
- `OpnameController` — index/store/show/updateItems/lock
- `StockMovementController` — index/export CSV
- `DashboardController` — dashboard/hpp/profitLoss/exportProfitLoss/storeExpense
- `SettingController` — index/update
- `UserController` — index/store/update
- Semua di `backend/app/Http/Controllers/Api/`

**Middleware:**
- `CheckRole` — RBAC middleware di `backend/app/Http/Middleware/CheckRole.php`
- Bootstrap/app.php sudah dikonfigurasi dengan alias `role` dan JSON exception handler

**Migrations (10 file):**
- Users, categories+products (FULLTEXT conditional untuk SQLite), restocks+stock_batches, transactions+items, opname, stock_movements, settings+expenses, personal_access_tokens
- Semua migrasi sudah dijalankan di MySQL database `kopihariini_pos`

**Seeders (10 file):**
- `SettingSeeder`, `UserSeeder` (4 user: achmad.f/admin, kasir01/kasir, kasir02/kasir-nonaktif, sri.manajer/manajer — password: password123)
- `CategorySeeder` (8 kategori), `ProductSeeder` (48 SKU), `RestockSeeder`, `TransactionSeeder` (6 bulan historis)
- `OperatingExpenseSeeder`, `ProductionSeeder`, `DemoResetSeeder`
- Sudah di-seed ke MySQL

**Routes:** `backend/routes/api.php` lengkap (103 baris), RBAC middleware per endpoint

**Tests:** `backend/tests/Feature/HppServiceTest.php` — **10/10 tes lolos**:
- Weighted average dari stok nol, dengan stok existing, pembulatan
- Restock updates stock dan cost
- Checkout deducts stock, throws saat stok kurang
- Duplicate client_uuid idempotent
- Opname lock adjusts stock
- Login returns token, login fails wrong password

**Config:**
- `config/cors.php` — allow all origins
- `.env` terhubung ke MySQL `kopihariini_pos`

### 2.2 Frontend (React 18 + Vite 8 + Tailwind CSS 3)

**Config files:**
- `vite.config.js` — proxy `/api` ke `localhost:8000`, manualChunks (function syntax untuk Vite 8/rolldown)
- `tailwind.config.js` — Design system lengkap (warna kopi, font Fraunces + Plus Jakarta Sans, animasi, shadow)
- `postcss.config.js`
- `index.html` — lang="id", Google Fonts preconnect

**Lib (3 file):**
- `src/lib/api.js` — Axios client, Bearer token, 401 redirect
- `src/lib/fmt.js` — `fmt()` (Rupiah), `fmtDiff()`, `pct()`, `fmtDate()`, `fmtDateTime()`, `fmtQty()`, `stockStatus()`, `calcMargin()`, `fmtShort()`
- `src/lib/db.js` — Dexie.js (IndexedDB): products cache, pendingTx queue, settings cache

**Store (2 file):**
- `src/store/auth.js` — Zustand + persist: login/logout/isAuthenticated/hasRole/can
- `src/store/network.js` — Zustand: isOnline, pendingCount, refreshPendingCount

**UI Components (5 file):**
- `src/components/ui/LoadingScreen.jsx` — Full-screen spinner
- `src/components/ui/Modal.jsx` — Reusable modal (sm/md/lg/xl/full)
- `src/components/ui/Toast.jsx` — ToastProvider + useToast (success/error/warning/info)
- `src/components/ui/ConfirmDialog.jsx` — Danger confirm modal
- `src/components/ui/States.jsx` — Skeleton, TableSkeleton, CardSkeleton, EmptyState, ErrorState

**Layout (1 file):**
- `src/components/layout/AppLayout.jsx` — Sidebar navigasi per role, network indicator, user info, logout

**Pages (10 file):**
- `LoginPage.jsx` — Login form, branded, show/hide password, role-based redirect
- `KasirPage.jsx` — POS: product grid + kategori filter + search, cart, payment method, change calc, offline queue, receipt modal + print
- `DashboardPage.jsx` — 4 stat cards, area chart (omzet vs HPP), pie chart (metode bayar), top products, low stock
- `ProdukPage.jsx` — CRUD table + modal, stock badges, filter kategori + status stok, margin
- `RestockPage.jsx` — History table, HPP preview calculator, form modal
- `HppPage.jsx` — Date range, summary cards, bar chart kontribusi HPP per produk, margin table + total persediaan
- `LaporanPage.jsx` — P&L report, expense entry modal, CSV export, 12-month trend chart
- `OpnamePage.jsx` — Session list, create modal, detail/counting modal, lock confirmation
- `PenggunPage.jsx` — User CRUD, role badges, activate/deactivate
- `PengaturanPage.jsx` — Store info, tax %, HPP method radio cards

**Entry points:**
- `src/main.jsx`, `src/App.jsx` (lazy loading, ProtectedRoute, TanStack Query provider)

**CSS:**
- `src/index.css` — Google Fonts import, Tailwind layers (base/components/utilities), 40+ component classes, 58mm print stylesheet

**Build:** `npm run build` **sukses** dalam 1.75 detik

### 2.3 Infrastructure

- `docker-compose.yml` — PHP 8.2-FPM + MySQL 8.0 + Node
- `docker/php/Dockerfile`, `docker/php/php.ini`
- `docker/mysql/init.sql`
- `.env.example`, `deploy.sh`

---

## 3. Bug dan masalah yang ditemukan saat testing

### 3.1 Frontend tidak menampilkan produk di halaman Kasir
**Gejala:** Halaman kasir berhasil render tapi product grid kosong, menampilkan "Produk tidak ditemukan".
**Penyebab kemungkinan:**
- API `GET /api/products/pos` sudah dikonfigurasi di routes (line 35) dan controller (line 69-90)
- Sudah diperbaiki: `posProducts()` sekarang mengirim `low_stock_threshold` dan `is_active`
- **Perlu diperiksa:** apakah auth token benar-benar tersimpan di localStorage setelah login, dan apakah Axios interceptor mengirimnya dengan benar
- **Perlu diperiksa:** error di browser console — kemungkinan CORS atau 401

### 3.2 PHPUnit RBAC test gagal (1 dari 12)
**Gejala:** `kasir_cannot_access_hpp_endpoint()` dan `manajer_cannot_access_checkout()` menghasilkan exception stack trace bukan 403
**Penyebab:** CheckRole middleware mungkin melempar exception yang tidak di-handle sebagai JSON response saat test
**File:** `backend/app/Http/Middleware/CheckRole.php` — perlu memastikan mengembalikan `response()->json(...)` 403 bukan `abort(403)`

### 3.3 PengaturanPage menggunakan `onSuccess` callback deprecated
**Gejala:** `useQuery({ onSuccess })` sudah deprecated di TanStack Query v5
**Perbaikan:** Gunakan `useEffect` untuk set form data setelah query berhasil

---

## 4. Pekerjaan yang harus diselesaikan (urut prioritas)

### Prioritas TINGGI — harus jalan end-to-end

1. **Debug dan perbaiki halaman Kasir supaya produk tampil**
   - Jalankan `php artisan serve --port=8000` dan `npm run dev` (port 5173)
   - Buka browser di `http://localhost:5173/login`, login sebagai `kasir01` / `password123`
   - Periksa Network tab: apakah `GET /api/products/pos` mengembalikan data?
   - Jika 401: periksa token flow di `useAuthStore` dan `api.js` interceptor
   - Jika produk muncul: lanjut test checkout flow

2. **Verifikasi seluruh flow: login → kasir → checkout → struk → dashboard → laporan**
   - Login sebagai `kasir01` → checkout 1 transaksi tunai → verifikasi struk
   - Login sebagai `achmad.f` → dashboard harus tampil data → restock 1 produk → cek HPP berubah
   - Login sebagai `sri.manajer` → laporan laba rugi harus tampil angka

3. **Perbaiki CheckRole middleware supaya mengembalikan JSON 403 (bukan exception)**

4. **Fix TanStack Query v5 `onSuccess` deprecation di PengaturanPage**

### Prioritas SEDANG — polish

5. **Hapus file `src/App.css` yang tidak dipakai** (remnant dari Vite scaffold)

6. **Buat favicon SVG** — ikon cangkir kopi sederhana, simpan di `frontend/public/favicon.svg`

7. **Tambahkan PWA manifest** — `frontend/public/manifest.json` agar bisa di-install di mobile

8. **Tulis `README.md`** di root project dengan:
   - Cara install dan run (dev + production)
   - Akun demo (achmad.f, kasir01, sri.manajer — password123)
   - Arsitektur (backend/frontend split)
   - Perintah test

9. **Tulis `DEPLOYMENT.md`** sesuai spesifikasi bagian 12:
   - Docker Compose setup
   - Shared hosting cPanel (public_html, .htaccess)
   - Backup cron (`php artisan backup:db`)
   - Checklist verifikasi akhir

### Prioritas RENDAH — nice to have

10. **Vitest setup** — `frontend/src/test/setup.js` dan minimal test untuk `fmt.js`

11. **Service Worker** untuk offline-first (produk cache, background sync)

12. **Commit per tahap** — format `feat(kasir): offline queue sync`

---

## 5. Perintah untuk menjalankan proyek

```bash
# Backend
cd backend
cp .env.example .env  # Sesuaikan DB credentials
php artisan migrate:fresh --seed
php artisan serve --port=8000

# Frontend (terminal terpisah)
cd frontend
npm install
npm run dev
# Buka http://localhost:5173

# Test backend
cd backend
php artisan test tests/Feature/HppServiceTest.php
```

## 6. Akun demo yang ter-seed

| Username     | Password     | Role    | Status  |
|-------------|-------------|---------|---------|
| achmad.f    | password123 | admin   | Aktif   |
| kasir01     | password123 | kasir   | Aktif   |
| kasir02     | password123 | kasir   | Nonaktif|
| sri.manajer | password123 | manajer | Aktif   |

## 7. Aturan kerja (sama dengan sesi sebelumnya)

- Baca dulu spesifikasi lengkap di `prompt-antigravity-pos-kopi-hari-ini.md` untuk referensi detail
- Jangan mengubah stack teknologi, jangan tambah library di luar spec
- Setiap perbaikan harus diverifikasi: jalankan, test, buktikan jalan
- Tidak boleh ada TODO, FIXME, placeholder, atau fungsi kosong
- Semua uang dalam BIGINT Rupiah penuh, frontend **tidak pernah** menghitung HPP/margin/total
- Laporkan tiap tahap: apa yang diperbaiki, cara verifikasi, apa berikutnya

**Mulai dari nomor 1 di bagian 4 sekarang: debug dan perbaiki halaman Kasir supaya produk tampil dan checkout berjalan.**

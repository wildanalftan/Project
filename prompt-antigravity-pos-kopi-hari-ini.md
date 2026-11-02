# Prompt Antigravity — Sistem POS & HPP Otomatis (Kopi Hari Ini)

> Cara pakai: salin seluruh isi mulai bagian **1. Peran dan tujuan** sampai bagian terakhir, lalu tempel sebagai satu prompt pertama di Antigravity.
> Sebelum menempel, ganti tiga nilai ini: `APP_URL` produksi (domain yang nanti dibeli), nama database/user/password MySQL di hosting, dan nama repositori Git jika sudah ada.
> Jika Antigravity membatasi panjang prompt, kirim bagian 1–4 lebih dulu, minta agent membuat scaffold + migrasi, baru kirim bagian 5–13 sebagai lanjutan.

---

## 1. Peran dan tujuan

Kamu adalah full-stack engineer yang membangun satu produk utuh dari nol sampai siap deploy: backend API, frontend web, dan database MySQL yang sudah terisi data awal. Kerjakan sampai selesai tanpa meninggalkan TODO, placeholder, atau fungsi kosong. Setiap fitur yang disebut di dokumen ini harus benar-benar jalan end-to-end.

**Produk:** Sistem Point of Sales (POS) dan penghitungan HPP (Harga Pokok Penjualan / COGS) otomatis untuk UMKM kedai kopi bernama **Kopi Hari Ini**.

**Masalah yang diselesaikan:** pencatatan kasir masih manual, stok gudang tidak terlacak akurat, dan pemilik tidak tahu margin per produk. Sistem harus menghitung HPP dan laba kotor otomatis per transaksi, memotong stok otomatis, dan menyajikan laporan keuangan instan.

**Tiga peran pengguna:**

| Peran | Beranda | Tanggung jawab |
| --- | --- | --- |
| Admin | Master Data Produk | Master data, restock, stock opname, riwayat perubahan, kelola akun |
| Kasir | Layar Kasir | Cari produk, checkout, bayar, cetak struk. Tidak pernah melihat HPP atau laba |
| Manajer Keuangan | Dashboard Keuangan | Omzet, laba rugi, nilai aset persediaan, HPP per produk, ekspor laporan |

**Bahasa:** seluruh antarmuka, label, pesan validasi, dan pesan error memakai Bahasa Indonesia. Komentar kode dan nama variabel memakai Bahasa Inggris.

**Mata uang:** Rupiah, tanpa desimal, format `Rp 142.500` (titik sebagai pemisah ribuan).

**Di luar cakupan:** tidak ada modul HRD, absensi, penggajian, loyalty, atau multi-cabang. Jangan menambah fitur yang tidak diminta.

---

## 2. Tech stack dan struktur repository

Pakai stack ini persis. Jangan mengganti framework, database, atau library inti.

| Lapisan | Teknologi |
| --- | --- |
| Backend | Laravel 11 (PHP 8.2+), API-only, Laravel Sanctum untuk auth token |
| Database | MySQL 8.0 (engine InnoDB, charset `utf8mb4_unicode_ci`) |
| Frontend | React 18 + Vite + React Router v6 + Tailwind CSS 3 |
| State & data | TanStack Query (React Query) untuk server state, Zustand untuk keranjang kasir |
| Offline | Dexie.js di atas IndexedDB |
| Grafik | Recharts |
| Ikon | lucide-react |
| Font | Fraunces (judul) dan Plus Jakarta Sans (antarmuka), dimuat dari Google Fonts |
| Testing | PHPUnit (backend), Vitest (frontend) |

**Struktur repo (monorepo dua folder):**

```
kopi-hari-ini-pos/
├── backend/              # Laravel 11 API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Http/Requests/
│   │   ├── Http/Resources/
│   │   ├── Models/
│   │   ├── Services/      # HppService, StockService, ReportService, SyncService
│   │   ├── Enums/
│   │   └── Policies/
│   ├── database/migrations/
│   ├── database/seeders/
│   ├── routes/api.php
│   └── tests/Feature/
├── frontend/             # React + Vite SPA
│   ├── src/
│   │   ├── api/           # axios client + query hooks per modul
│   │   ├── components/    # Button, Input, Badge, Table, Card, StatCard, Modal
│   │   ├── features/      # auth, kasir, produk, restock, opname, riwayat, hpp, dashboard, laporan, pengguna
│   │   ├── layouts/       # AdminLayout, KasirLayout, ManajerLayout
│   │   ├── lib/           # formatRupiah, offlineDb, syncQueue, rbac
│   │   ├── routes.jsx
│   │   └── main.jsx
│   └── vite.config.js
├── docker-compose.yml    # mysql + php + node untuk development lokal
├── .env.example          # untuk backend
└── README.md             # instruksi install, seed, build, deploy
```

**Aturan teknis wajib:**

1. Backend murni API JSON. Tidak ada Blade view kecuali halaman fallback SPA.
2. Semua respons API dibungkus Laravel API Resource, format konsisten: `{ "data": ..., "meta": ... }`.
3. Semua uang disimpan sebagai `BIGINT` dalam satuan rupiah penuh (bukan sen, bukan float). Jangan pernah pakai `FLOAT` atau `DOUBLE` untuk uang.
4. Semua perubahan stok dan transaksi dijalankan di dalam `DB::transaction()` dengan `lockForUpdate()` pada baris produk.
5. Frontend tidak pernah menghitung HPP, laba, atau total akhir sendiri. Semua angka itu datang jadi dari API.
6. Gunakan `.env` untuk seluruh konfigurasi. Tidak ada kredensial hardcoded di kode.

---

## 3. Skema database MySQL

Buat migrasi Laravel untuk tabel berikut, persis dengan kolom dan relasi ini. Semua tabel punya `id BIGINT UNSIGNED AUTO_INCREMENT` dan `created_at` / `updated_at`.

**users** — akun pengguna

```
name            VARCHAR(100)
username        VARCHAR(50) UNIQUE
password        VARCHAR(255)          -- bcrypt
role            ENUM('admin','kasir','manajer')
is_active       BOOLEAN DEFAULT 1
last_login_at   TIMESTAMP NULL
INDEX (role), INDEX (is_active)
```

**categories** — kategori produk

```
name            VARCHAR(60) UNIQUE     -- Kopi Susu, Espresso Based, Non-Kopi, Makanan
sort_order      SMALLINT DEFAULT 0
```

**products** — master data produk

```
category_id     FK -> categories.id ON DELETE RESTRICT
sku             VARCHAR(30) UNIQUE     -- contoh KHI-0412
name            VARCHAR(120)
unit            VARCHAR(20)            -- pcs, kg, L
selling_price   BIGINT UNSIGNED        -- harga jual, rupiah penuh
current_cost    BIGINT UNSIGNED DEFAULT 0  -- HPP per unit berjalan (rata-rata tertimbang)
stock_qty       DECIMAL(12,3) DEFAULT 0    -- desimal agar mendukung kg dan liter
low_stock_threshold DECIMAL(12,3) DEFAULT 10
is_active       BOOLEAN DEFAULT 1
INDEX (name), INDEX (category_id), INDEX (is_active)
FULLTEXT INDEX ft_products (name, sku)   -- untuk pencarian kasir
```

`current_cost` dan `stock_qty` adalah kolom turunan. Keduanya hanya boleh diubah lewat `StockService`, tidak pernah lewat mass-assignment.

**stock_batches** — lot pembelian, dipakai metode FIFO

```
product_id      FK -> products.id ON DELETE CASCADE
restock_id      FK -> restocks.id NULL ON DELETE SET NULL
qty_in          DECIMAL(12,3)
qty_remaining   DECIMAL(12,3)
unit_cost       BIGINT UNSIGNED
received_at     TIMESTAMP
INDEX (product_id, received_at), INDEX (product_id, qty_remaining)
```

**restocks** — barang masuk

```
product_id      FK -> products.id ON DELETE RESTRICT
user_id         FK -> users.id
qty             DECIMAL(12,3)
unit_cost       BIGINT UNSIGNED        -- harga beli per satuan
supplier        VARCHAR(120) NULL
cost_before     BIGINT UNSIGNED        -- HPP sebelum restock
cost_after      BIGINT UNSIGNED        -- HPP sesudah restock
note            VARCHAR(255) NULL
received_at     TIMESTAMP
INDEX (product_id, received_at)
```

**transactions** — struk penjualan

```
invoice_no      VARCHAR(20) UNIQUE     -- TRX-0231, dibuat server
client_uuid     CHAR(36) UNIQUE NULL   -- idempotency key dari kasir offline
user_id         FK -> users.id         -- kasir
table_label     VARCHAR(30) NULL       -- "Meja 3"
subtotal        BIGINT UNSIGNED
tax_amount      BIGINT UNSIGNED
total           BIGINT UNSIGNED
payment_method  ENUM('tunai','qris','kartu')
paid_amount     BIGINT UNSIGNED
change_amount   BIGINT UNSIGNED
total_cogs      BIGINT UNSIGNED        -- total HPP seluruh item
gross_profit    BIGINT UNSIGNED        -- total - total_cogs
status          ENUM('completed','void') DEFAULT 'completed'
is_offline      BOOLEAN DEFAULT 0
occurred_at     TIMESTAMP              -- waktu transaksi nyata di kasir
synced_at       TIMESTAMP NULL
INDEX (occurred_at), INDEX (user_id), INDEX (status)
```

`client_uuid` adalah kunci anti-duplikat. Transaksi offline yang dikirim dua kali harus ditolak dengan tenang dan mengembalikan struk yang sudah ada.

**transaction_items** — baris item di struk

```
transaction_id  FK -> transactions.id ON DELETE CASCADE
product_id      FK -> products.id ON DELETE RESTRICT
product_name    VARCHAR(120)           -- snapshot nama saat transaksi
qty             DECIMAL(12,3)
unit_price      BIGINT UNSIGNED        -- snapshot harga jual
unit_cost       BIGINT UNSIGNED        -- snapshot HPP per unit saat itu
line_total      BIGINT UNSIGNED
line_cogs       BIGINT UNSIGNED
line_profit     BIGINT UNSIGNED
INDEX (transaction_id), INDEX (product_id)
```

Kolom snapshot wajib. Laporan historis tidak boleh berubah ketika harga atau nama produk diubah di kemudian hari.

**opname_sessions** — sesi stock opname

```
user_id         FK -> users.id
session_date    DATE
status          ENUM('draft','locked') DEFAULT 'draft'
locked_at       TIMESTAMP NULL
note            VARCHAR(255) NULL
INDEX (session_date, status)
```

**opname_items** — baris hitung fisik

```
opname_session_id FK -> opname_sessions.id ON DELETE CASCADE
product_id        FK -> products.id ON DELETE RESTRICT
system_qty        DECIMAL(12,3)        -- stok sistem saat baris dibuat
physical_qty      DECIMAL(12,3) NULL   -- hasil hitung gudang
difference        DECIMAL(12,3) NULL   -- physical - system, dihitung server
note              VARCHAR(255) NULL
UNIQUE (opname_session_id, product_id)
```

**stock_movements** — buku besar pergerakan stok, append-only

```
product_id      FK -> products.id ON DELETE RESTRICT
user_id         FK -> users.id NULL
type            ENUM('restock','sale','opname','adjustment')
reference_type  VARCHAR(50) NULL       -- Restock, Transaction, OpnameSession
reference_id    BIGINT UNSIGNED NULL
qty_before      DECIMAL(12,3)
qty_change      DECIMAL(12,3)          -- negatif untuk pengurangan
qty_after       DECIMAL(12,3)
cost_before     BIGINT UNSIGNED
cost_after      BIGINT UNSIGNED
note            VARCHAR(255) NULL
occurred_at     TIMESTAMP
INDEX (product_id, occurred_at), INDEX (type), INDEX (reference_type, reference_id)
```

Tabel ini tidak pernah di-`UPDATE` atau di-`DELETE`. Blokir keduanya di level model (lempar exception di event `updating` dan `deleting`).

**settings** — konfigurasi toko, satu baris per kunci

```
key             VARCHAR(60) UNIQUE
value           TEXT
```

Kunci awal: `store_name`, `store_address`, `store_phone`, `tax_percent` (default `10`), `hpp_method` (`weighted_average` atau `fifo`, default `weighted_average`), `receipt_footer`.

**personal_access_tokens** — bawaan Laravel Sanctum, pakai migrasi standar.

---

## 4. Logika HPP dan pergerakan stok

Ini inti sistem. Tulis seluruh logika di `app/Services/HppService.php` dan `app/Services/StockService.php`, bukan di controller.

**Metode aktif dibaca dari `settings.hpp_method`.** Implementasikan dua-duanya dan pilih saat runtime.

### Rata-rata tertimbang (default)

Saat restock masuk, HPP baru dihitung dari nilai persediaan lama ditambah nilai barang masuk, dibagi total kuantitas:

```
HPP_baru = ((Q_lama × C_lama) + (Q_masuk × C_masuk)) ÷ (Q_lama + Q_masuk)
```

Contoh yang harus lolos uji: produk Matcha Latte punya stok 0 dan HPP Rp 14.800 dari lot sebelumnya; masuk 20 pcs seharga Rp 14.200 per pcs. Karena `Q_lama = 0`, HPP baru menjadi Rp 14.200. Jika stok lama 10 pcs @ Rp 15.400, maka HPP baru = ((10 × 15.400) + (20 × 14.200)) ÷ 30 = Rp 14.600.

Pembulatan: hasil dibulatkan ke rupiah penuh dengan `round()` (half up). Simpan sebagai `BIGINT`.

### FIFO

Stok disimpan sebagai lot di `stock_batches`. Saat penjualan, konsumsi lot dengan `received_at` tertua lebih dulu sampai kuantitas terpenuhi, kurangi `qty_remaining` tiap lot, dan `line_cogs` adalah jumlah (qty diambil × `unit_cost` lot). `current_cost` produk diisi ulang dengan rata-rata tertimbang dari lot yang masih bersisa, supaya nilai aset persediaan tetap bisa dihitung.

Jika lot habis tetapi masih ada permintaan (stok negatif karena kondisi tak terduga), pakai `unit_cost` lot terakhir dan catat `adjustment` di `stock_movements`.

### Aturan yang berlaku untuk kedua metode

1. **Setiap checkout** menghasilkan satu baris `stock_movements` bertipe `sale` per produk, dengan `qty_change` negatif, serta mengisi `unit_cost`, `line_cogs`, dan `line_profit` pada tiap `transaction_items`.
2. **Laba kotor per item** = `line_total − line_cogs`. **Laba kotor transaksi** = `total − total_cogs`. Hitung di server, jangan di frontend.
3. **Nilai aset persediaan** = jumlah dari (`stock_qty` × `current_cost`) untuk seluruh produk aktif.
4. **Margin produk** = `(harga_jual − HPP) ÷ harga_jual × 100`, tampilkan satu desimal.
5. **Restock** menambah `stock_qty`, membuat `stock_batches` baru, menulis `restocks` dengan `cost_before` dan `cost_after`, dan menulis `stock_movements` bertipe `restock`.
6. **Stock opname** saat dikunci: untuk setiap baris dengan selisih bukan nol, sesuaikan `stock_qty` ke `physical_qty` dan tulis `stock_movements` bertipe `opname` dengan `qty_change` = selisih. HPP tidak berubah karena opname. Sesi berstatus `locked` tidak bisa diedit lagi.
7. **Stok tidak boleh negatif** lewat jalur kasir. Tolak checkout dengan HTTP 422 dan pesan `Stok {nama produk} tidak mencukupi (tersisa {n}).`
8. **Validasi harga jual** harus lebih besar dari HPP saat menyimpan produk. Jika tidak, tolak dengan pesan `Harga jual harus lebih besar dari HPP.`
9. Seluruh langkah di atas berjalan dalam satu `DB::transaction()`. Ambil baris produk dengan `lockForUpdate()` sebelum membaca `stock_qty` dan `current_cost`.

Tulis unit test PHPUnit untuk: rata-rata tertimbang dari stok nol, rata-rata tertimbang dari stok yang ada, konsumsi FIFO lintas dua lot, penolakan stok kurang, dan idempotensi `client_uuid`.

---

## 5. Spesifikasi REST API

Semua endpoint diawali `/api`. Kecuali login, semua butuh header `Authorization: Bearer {token}` dari Sanctum. Kolom `Peran` menunjukkan siapa yang boleh mengakses.

| Method | Endpoint | Peran | Fungsi |
| --- | --- | --- | --- |
| POST | `/auth/login` | publik | Login dengan `username` + `password`, kembalikan token dan profil peran |
| POST | `/auth/logout` | semua | Cabut token aktif |
| GET | `/auth/me` | semua | Profil pengguna yang sedang login |
| GET | `/users` | admin | Daftar akun + jumlah per peran |
| POST | `/users` | admin | Buat akun baru |
| PUT | `/users/{id}` | admin | Ubah nama, peran, status aktif, reset password |
| GET | `/categories` | semua | Daftar kategori |
| GET | `/products` | semua | Daftar produk, filter `?search=`, `?category_id=`, `?status=aman\|menipis\|habis`, paginasi 20 |
| GET | `/products/pos` | kasir, admin | Daftar ringkas untuk cache kasir: id, sku, nama, kategori, harga jual, stok. **Tanpa HPP** |
| POST | `/products` | admin | Buat produk |
| PUT | `/products/{id}` | admin | Ubah produk |
| DELETE | `/products/{id}` | admin | Nonaktifkan produk (soft, set `is_active=0`) |
| POST | `/products/import` | admin | Impor CSV: sku, nama, kategori, satuan, harga jual, stok awal, harga beli |
| GET | `/products/{id}/summary` | admin, manajer | HPP, margin, stok, 10 pergerakan terakhir |
| GET | `/restocks` | admin | Riwayat restock terbaru |
| POST | `/restocks` | admin | Catat barang masuk, hitung ulang HPP, kembalikan `cost_before` dan `cost_after` |
| POST | `/restocks/preview` | admin | Hitung HPP baru tanpa menyimpan, untuk pratinjau di form |
| GET | `/opname` | admin | Daftar sesi opname |
| POST | `/opname` | admin | Buat sesi baru, isi baris dari produk aktif dengan `system_qty` saat ini |
| GET | `/opname/{id}` | admin | Detail sesi + baris |
| PUT | `/opname/{id}/items` | admin | Simpan draft `physical_qty` dan catatan per baris |
| POST | `/opname/{id}/lock` | admin | Kunci sesi, sesuaikan stok, tulis pergerakan |
| GET | `/stock-movements` | admin, manajer | Riwayat perubahan stok, filter `?type=`, `?from=`, `?to=`, `?product_id=`, paginasi 25 |
| GET | `/stock-movements/export` | admin, manajer | Unduh CSV riwayat sesuai filter |
| POST | `/transactions` | kasir, admin | Checkout. Body berisi `client_uuid`, `occurred_at`, `table_label`, `payment_method`, `paid_amount`, `items[]` (`product_id`, `qty`). Server hitung harga, pajak, total, HPP, laba |
| GET | `/transactions` | semua | Daftar transaksi, filter tanggal dan kasir |
| GET | `/transactions/{id}` | semua | Detail struk untuk cetak ulang |
| POST | `/transactions/sync` | kasir, admin | Kirim antrean offline sebagai array. Proses berurutan, lewati `client_uuid` yang sudah ada, kembalikan per item: `accepted`, `duplicate`, atau `failed` + alasan |
| GET | `/hpp` | admin, manajer | HPP dan margin per produk untuk periode, plus ringkasan omzet, total HPP, laba kotor, margin rata-rata |
| GET | `/dashboard` | manajer, admin | Omzet hari ini + perbandingan kemarin, laba kotor, margin, nilai aset persediaan, jumlah transaksi, rata-rata per struk, seri omzet vs HPP 7 hari, produk terlaris, jumlah SKU habis |
| GET | `/reports/profit-loss` | manajer, admin | Laba rugi per bulan: omzet bruto, HPP, laba kotor, beban operasional, laba bersih, margin bersih, tren margin 6 bulan |
| GET | `/reports/profit-loss/export` | manajer, admin | Ekspor PDF atau CSV (`?format=pdf\|csv`) |
| GET | `/settings` | admin, manajer | Baca konfigurasi toko |
| PUT | `/settings` | admin | Ubah konfigurasi toko dan metode HPP |

**Format error seragam:**

```json
{ "message": "Stok Kopi Hitam Tubruk tidak mencukupi (tersisa 8).", "errors": { "items.0.qty": ["..."] } }
```

**Nomor struk:** `TRX-` + urutan harian empat digit, dibuat server dalam transaksi yang sama supaya tidak bentrok.

**Beban operasional** pada laporan laba rugi dibaca dari tabel `operating_expenses` (buat tabel sederhana: `id`, `period` DATE, `category` VARCHAR, `amount` BIGINT) yang bisa diisi admin lewat `POST /reports/expenses`. Isi seeder dengan kategori gaji, sewa, listrik-air-internet, dan hosting-domain.

---

## 6. Frontend React: rute dan isi layar

Satu SPA, tiga pengalaman. Menu yang tidak berhak diakses **tidak dirender sama sekali**, bukan sekadar disembunyikan.

| Rute | Peran | Isi |
| --- | --- | --- |
| `/login` | semua | Layar masuk |
| `/kasir` | kasir, admin | Checkout satu halaman |
| `/kasir/bayar` | kasir, admin | Pembayaran |
| `/kasir/struk/:id` | kasir, admin | Struk berhasil + cetak |
| `/stok/produk` | admin | Master data produk |
| `/stok/restock` | admin | Catat barang masuk |
| `/stok/opname` | admin | Sesi stock opname |
| `/stok/riwayat` | admin, manajer | Riwayat perubahan stok |
| `/hpp` | admin, manajer | HPP dan laba kotor |
| `/dashboard` | manajer, admin | Dashboard keuangan |
| `/laporan` | manajer, admin | Laba rugi bulanan |
| `/pengaturan/pengguna` | admin | Pengguna dan hak akses |

Setelah login, arahkan otomatis ke beranda sesuai peran: admin ke `/stok/produk`, kasir ke `/kasir`, manajer ke `/dashboard`.

### Layar kasir (`/kasir`)

Layar paling penting. Dirancang satu halaman penuh tanpa navigasi berpindah, dioperasikan dengan sentuhan di tablet.

Kiri: kolom pencarian besar dengan placeholder `Cari nama produk atau pindai barcode...`, lalu baris chip kategori (Semua, Kopi Susu, Espresso Based, Non-Kopi, Makanan), lalu grid kartu produk empat kolom. Kartu menampilkan nama dan harga jual saja. Produk dengan stok nol tampil pudar, diberi badge `Stok habis`, dan tidak bisa diklik.

Kanan: panel pesanan dengan label meja dan nomor transaksi sementara, daftar item dengan tombol kurang/tambah, lalu subtotal, pajak, dan total. Tombol utama `Lanjut Bayar →`. Di bawahnya teks kecil: `Stok terpotong otomatis setelah pembayaran berhasil`.

Atas: nama kasir, jam shift, tombol keluar, dan indikator koneksi di kanan atas yang selalu terlihat.

**Pencarian harus di bawah 1 detik.** Caranya: muat `/products/pos` sekali saat masuk, simpan di memori dan IndexedDB, lalu filter lokal dengan debounce 150 ms. Jangan memanggil API setiap ketikan. Input barcode dari scanner masuk sebagai ketikan cepat diakhiri Enter — tangani dengan mendeteksi jeda antar karakter di bawah 30 ms dan langsung tambahkan produk dengan SKU yang cocok.

**Layar kasir tidak pernah menampilkan angka HPP atau laba.**

### Pembayaran (`/kasir/bayar`)

Kiri ringkasan pesanan. Kanan metode pembayaran (Tunai, QRIS, Kartu/EDC), input uang diterima dengan tombol cepat (Pas, 120.000, 150.000, 200.000 — hitung dinamis dari total), kembalian dihitung langsung, lalu tombol `Konfirmasi & Cetak Struk`.

### Struk (`/kasir/struk/:id`)

Tanda centang hijau `Pembayaran berhasil`, struk gaya thermal 58 mm dengan font monospace di kiri, dan di kanan status printer, tombol `Cetak ulang struk`, `Kirim struk digital (WhatsApp)`, dan `Transaksi baru →`. Cetak memakai `window.print()` dengan stylesheet `@media print` yang hanya memunculkan elemen struk.

### Master data produk (`/stok/produk`)

Empat kartu statistik di atas: Total SKU, Stok Aman, Stok Menipis, Stok Habis. Lalu pencarian, filter kategori, filter status, dan tabel: Produk, SKU, Stok, HPP, Harga jual, Status, Aksi. Badge status mengikuti ambang `low_stock_threshold`. Tombol `Impor CSV` dan `Produk baru`. Produk habis menampilkan aksi `Restock` alih-alih `Edit`.

### Restock (`/stok/restock`)

Form: pilih produk (tampilkan stok saat ini), jumlah masuk, satuan, harga beli per satuan, pemasok. Begitu jumlah dan harga beli terisi, panggil `/restocks/preview` dan tampilkan kotak `HPP baru (rata-rata tertimbang)` dengan nilainya. Di kanan, tabel riwayat restock terbaru.

### Stock opname (`/stok/opname`)

Tabel: Produk, Stok sistem, Stok fisik (input), Selisih, Catatan. Selisih dihitung saat mengetik: negatif berwarna merah dengan latar merah muda, positif berwarna teal dengan latar hijau muda, nol netral. Footer menampilkan `Produk diperiksa: 4 / 48` beserta jumlah selisih kurang dan lebih, dengan tombol `Simpan sebagai draft` dan `Kunci & sesuaikan stok`. Tombol kunci memunculkan konfirmasi karena tidak bisa dibatalkan.

### Riwayat perubahan stok (`/stok/riwayat`)

Filter jenis, rentang tanggal, dan pencarian produk. Tabel: Waktu, Jenis (badge), Produk, Sebelum, Sesudah, Perubahan (bertanda dan berwarna), Pengguna. Baris penjualan menyertakan nomor struk. Tombol `Ekspor CSV`. Beri catatan di bawah tabel bahwa baris tidak dapat diubah atau dihapus.

### HPP dan laba kotor (`/hpp`)

Saklar metode (Rata-rata Tertimbang / FIFO) di kanan atas yang membaca dan menulis `settings.hpp_method`. Empat kartu: Omzet hari ini, Total HPP hari ini, Laba kotor (kartu berlatar hijau muda), Margin rata-rata. Lalu tabel per produk: Terjual, HPP/unit, Harga jual, Laba/unit, Margin, Laba total. Di bawahnya panel gelap berisi total nilai aset persediaan.

### Dashboard (`/dashboard`)

Saklar periode (Hari ini / Minggu ini / Bulan ini). Empat kartu: Omzet hari ini dengan perbandingan kemarin, Laba kotor dengan margin, Nilai aset persediaan, Jumlah transaksi dengan rata-rata per struk. Lalu grafik batang berdampingan Omzet vs HPP tujuh hari terakhir (Recharts), dan panel produk terlaris dengan bar proporsional. Di bawah, banner gelap peringatan jumlah SKU habis dengan tombol menuju modul stok.

### Laporan laba rugi (`/laporan`)

Pemilih bulan dan tombol ekspor PDF. Kiri: susunan laba rugi dari omzet bruto, dikurangi HPP, menjadi laba kotor, dikurangi rincian beban operasional, menjadi laba bersih dan margin bersih. Angka pengurang ditulis dalam kurung. Kanan: kartu nilai aset persediaan, grafik garis tren margin kotor enam bulan, dan panel gelap berisi tombol `Unduh PDF` dan `Ekspor CSV`.

### Pengguna dan hak akses (`/pengaturan/pengguna`)

Tiga kartu hitung akun per peran, tabel akun (Nama, Nama pengguna, Peran, Status, Login terakhir, Aksi), dan matriks hak akses modul dengan nilai centang, `Baca`, atau tanda hubung.

### Tampilan ponsel

Layar kasir wajib responsif: grid produk menjadi dua kolom, panel pesanan menjadi bilah bawah yang menempel berisi ringkasan item dan tombol `Bayar` dengan total. Layar lain cukup dapat dibaca di tablet.

---

## 7. Design system

Masukkan token ini ke `tailwind.config.js` sebagai warna kustom. Jangan memakai palet default Tailwind untuk warna merek.

| Token | Hex | Pemakaian |
| --- | --- | --- |
| `ink` | `#15120F` | Teks utama |
| `ink-70` | `#4A423B` | Teks isi |
| `ink-45` | `#7A6F66` | Label |
| `ground` | `#F7F4EF` | Latar halaman |
| `surface` | `#FFFFFF` | Kartu |
| `line` | `#E6E0D8` | Garis dan pembatas |
| `kopi` | `#9C4A21` | Aksen, tombol utama |
| `kopi-10` | `#F7E9DF` | Latar aksen |
| `laba` | `#1D6B55` | Nilai positif, laba |
| `perhatian` | `#9A6B06` | Stok menipis |
| `kritis` | `#A32B2B` | Stok habis, selisih negatif |
| `offline` | `#1F4571` | Status sinkronisasi |

Pasangan warna selalu berbeda terang-gelapnya, bukan hanya rona, agar tetap terbaca bagi pengguna buta warna. Status tidak pernah disampaikan lewat warna saja — selalu sertakan teks atau ikon.

**Tipografi.** Fraunces untuk judul display dan judul halaman; Plus Jakarta Sans untuk seluruh antarmuka.

| Peran teks | Ukuran / bobot |
| --- | --- |
| Display | 54 / 700 |
| Judul halaman | 28 / 700 |
| Judul kartu | 19 / 600 |
| Isi | 14 / 400 |
| Label | 12 / 700 huruf kapital, `letter-spacing: 0.08em` |

**Angka dan mata uang.** Semua angka uang memakai `font-variant-numeric: tabular-nums` dan rata kanan di dalam tabel. Laba kotor ditulis dengan tanda plus berwarna `laba`, selisih negatif dengan tanda minus berwarna `kritis`. Nilai HPP ditampilkan dengan warna `ink-45` agar tidak mendominasi harga jual. Buat satu helper `formatRupiah(value)` dan pakai di mana-mana.

**Komponen.** Radius 10 px untuk kontrol, 16 px untuk kartu. Semua target sentuh minimal 44 px karena layar kasir dioperasikan dengan sentuhan di tablet.

- Tombol: `primary` (latar `kopi`, teks putih), `secondary` (garis `line`, latar putih), `danger` (garis dan teks `kritis`), `disabled` (abu, kursor tidak aktif).
- Input: label di atas, garis `line`, status fokus bergaris `kopi` dua piksel, pesan error merah di bawah kolom.
- Badge status: `Stok aman` hijau, `Stok menipis` kuning, `Stok habis` merah, `Menunggu sinkron` biru, `Draft opname` abu.
- Indikator koneksi: titik hijau `Online · tersinkron`, titik biru `Offline · {n} transaksi tertahan`.

**Sidebar.** Latar `ink`, item aktif berlatar lebih terang, logo cangkir kopi di atas, kartu profil pengguna di bawah berisi inisial, nama, dan peran.

---

## 8. Offline mode dan sinkronisasi

Kasir harus tetap bisa melayani pembayaran ketika internet toko putus. Ini syarat penerimaan, bukan fitur tambahan.

**Penyimpanan lokal.** Pakai Dexie.js dengan tiga store di IndexedDB:

```
products      — cache /products/pos, disegarkan tiap 5 menit saat online
queue         — transaksi offline yang belum terkirim
meta          — waktu sinkron terakhir, nomor urut sementara
```

**Deteksi koneksi.** Jangan hanya mengandalkan `navigator.onLine` karena sering berbohong. Lakukan ping ringan ke `/api/health` tiap 15 detik dengan timeout 3 detik. Status berubah ke offline setelah dua kegagalan berturut-turut, dan kembali online setelah satu keberhasilan.

**Saat offline.** Checkout tetap jalan. Transaksi disimpan ke `queue` dengan:

- `client_uuid` dibuat di perangkat dengan `crypto.randomUUID()`
- nomor sementara `OFF-00231` yang naik berurutan
- `occurred_at` waktu nyata di perangkat
- salinan item lengkap beserta harga jual dari cache

Stok dikurangi secara optimis di cache lokal supaya kasir tidak menjual barang yang sudah habis, lalu diselaraskan ulang setelah sinkronisasi.

Struk tetap bisa dicetak dengan nomor sementara, diberi tanda kecil `Belum tersinkron`.

**Layar antrean offline.** Saat offline, header berubah menjadi biru `offline` dengan teks `Koneksi internet terputus`. Sediakan panel berisi: kartu Menunggu Sinkron (jumlah transaksi), Nilai Tertahan (total rupiah), Mulai Offline (jam), lalu tabel antrean dengan ID sementara, Waktu, Total, dan badge Status.

**Saat kembali online.** Kirim antrean ke `POST /transactions/sync` berurutan menurut `occurred_at`, maksimal 20 per batch. Untuk tiap item:

1. `accepted` — hapus dari antrean, simpan nomor struk resmi dari server.
2. `duplicate` — sudah pernah masuk, hapus dari antrean tanpa pesan error.
3. `failed` — pertahankan di antrean, tandai merah, tampilkan alasan. Kasus paling umum: stok tidak mencukupi karena cabang lain sudah menjual. Beri admin tombol untuk meninjau dan menyesuaikan.

Setelah semua terkirim, segarkan cache produk dan tampilkan notifikasi `7 transaksi berhasil tersinkron`.

**Idempotensi di server.** `POST /transactions` dan `/transactions/sync` memeriksa `client_uuid` sebelum menulis. Jika sudah ada, kembalikan transaksi lama dengan HTTP 200 dan tandai `duplicate: true`. Jangan pernah membuat struk kembar.

**Waktu.** Simpan `occurred_at` sebagai waktu transaksi nyata, bukan waktu sinkronisasi, supaya laporan harian tetap benar. Tangani perbedaan jam perangkat: jika `occurred_at` lebih dari 24 jam dari waktu server, tolak dan catat.

---

## 9. Seeder dan data contoh

Satu perintah `php artisan migrate:fresh --seed` harus menghasilkan sistem yang langsung bisa didemokan. Isi seeder persis seperti ini supaya cocok dengan rancangan UI/UX.

**Akun (password semua: `password123`, minta ganti saat login pertama di produksi):**

| Nama | Username | Peran |
| --- | --- | --- |
| Achmad Fandanish | `achmad.f` | admin |
| Dewi Lestari | `kasir01` | kasir |
| Bagas Saputra | `kasir02` | kasir (nonaktif) |
| Sri Wulandari | `sri.manajer` | manajer |

**Kategori:** Kopi Susu, Espresso Based, Non-Kopi, Makanan.

**Produk (48 SKU total; lima ini wajib ada persis):**

| SKU | Nama | Stok | HPP | Harga jual |
| --- | --- | --- | --- | --- |
| KHI-0412 | Kopi Susu Gula Aren | 48 | 11.400 | 22.000 |
| KHI-0298 | Cappuccino | 31 | 13.100 | 25.000 |
| KHI-0105 | Kopi Hitam Tubruk | 8 | 7.200 | 15.000 |
| KHI-0501 | Matcha Latte | 0 | 14.800 | 26.000 |
| KHI-0633 | Croissant Cokelat | 22 | 9.600 | 18.000 |

Sisanya hasilkan dengan factory sehingga totalnya 48 SKU, dengan komposisi 39 stok aman, 6 menipis, dan 3 habis. Tambahkan juga bahan baku Biji Kopi Arabika (kg), Susu UHT 1L (pcs), dan Gula Aren Cair (L) untuk mengisi riwayat restock.

**Transaksi:** hasilkan enam bulan transaksi mundur dari hari ini, dengan pola jam sibuk pagi dan sore, sehingga dashboard dan tren margin enam bulan punya data nyata. Hari ini harus menghasilkan sekitar 142 transaksi, omzet sekitar Rp 2.840.000, laba kotor sekitar Rp 1.328.000, dan margin sekitar 46,7 persen. Bulan lalu menghasilkan omzet sekitar Rp 84.620.000 dan laba bersih sekitar Rp 16.582.000.

**Pergerakan stok:** setiap transaksi dan restock seeder wajib ikut menulis `stock_movements`, jangan hanya mengubah `stock_qty`. Target sekitar 214 catatan.

**Beban operasional bulan lalu:** gaji dan upah Rp 14.500.000, sewa tempat Rp 6.000.000, listrik-air-internet Rp 2.150.000, hosting dan domain Rp 208.000.

**Pengaturan toko:** nama `Kopi Hari Ini`, alamat `Jl. Merdeka No. 12, Bandung`, telepon `0812-xxxx-xxxx`, pajak 10 persen, metode HPP rata-rata tertimbang, footer struk `Terima kasih telah berbelanja!`.

Seluruh angka ini adalah data contoh untuk keperluan demonstrasi, bukan data penjualan sebenarnya. Sediakan perintah `php artisan db:seed --class=DemoResetSeeder` untuk mengosongkan data demo sebelum serah terima ke klien.

---

## 10. Keamanan, RBAC, dan validasi

**Otentikasi.** Laravel Sanctum token. Token disimpan di `localStorage` frontend. Setiap respons 401 memicu logout dan arahkan ke `/login`. Catat `last_login_at` setiap login berhasil.

**Otorisasi berlapis dua.** Hak akses dipaksakan di server lewat middleware peran dan Laravel Policy pada setiap model, bukan hanya disembunyikan di frontend. Kasir yang memanggil `GET /hpp` secara langsung harus menerima HTTP 403.

| Modul | Admin | Kasir | Manajer |
| --- | --- | --- | --- |
| Kasir (checkout) | penuh | penuh | tidak ada |
| Manajemen stok | penuh | tidak ada | tidak ada |
| HPP dan laba kotor | penuh | tidak ada | baca |
| Dashboard dan laporan | baca | tidak ada | penuh |
| Pengguna dan hak akses | penuh | tidak ada | tidak ada |

**Penyaringan data.** Endpoint `/products/pos` tidak boleh menyertakan `current_cost` dalam respons. Pastikan lewat API Resource terpisah, bukan dengan menyembunyikan di frontend.

**Validasi.** Semua input lewat Form Request. Aturan penting:

- `selling_price` wajib bilangan bulat positif dan lebih besar dari `current_cost`
- `qty` pada restock dan opname wajib lebih besar dari nol, maksimal tiga desimal
- `sku` unik, hanya huruf besar, angka, dan tanda hubung
- `paid_amount` wajib lebih besar atau sama dengan total untuk pembayaran tunai
- `items` pada checkout wajib berisi minimal satu baris dengan produk aktif

**Rate limiting.** `/auth/login` dibatasi 5 percobaan per menit per IP. Endpoint lain 120 permintaan per menit per pengguna.

**Pengerasan lain.** Aktifkan CORS hanya untuk domain frontend. Set `SESSION_SECURE_COOKIE=true` dan paksa HTTPS di produksi. Password dengan bcrypt cost 12. Jangan pernah mencatat password, token, atau isi request body ke log.

---

## 11. Kriteria penerimaan

Pekerjaan dianggap selesai hanya jika seluruh butir di bawah ini terbukti jalan. Tulis test otomatis untuk yang ditandai **(test)**, dan sertakan ringkasan hasilnya di README.

**Kebutuhan fungsional**

- [ ] FR-01 Login wajib sebelum mengakses sistem, peran dikenali otomatis **(test)**
- [ ] FR-02 Hak akses berbasis peran dipaksakan di server, bukan hanya UI **(test)**
- [ ] FR-03 Master data produk dapat dikelola dan menjadi dasar modul lain
- [ ] FR-04 Restock mencatat barang masuk dan menambah stok otomatis **(test)**
- [ ] FR-05 Stok tampil real-time, berubah setelah tiap transaksi dan restock
- [ ] FR-06 Stock opname mencocokkan stok sistem dengan fisik dan menyesuaikan **(test)**
- [ ] FR-07 Riwayat perubahan stok tercatat detail dan tidak bisa diubah **(test)**
- [ ] FR-08 HPP dihitung otomatis dan dinamis dengan rata-rata tertimbang atau FIFO **(test)**
- [ ] FR-09 Laba kotor dihitung otomatis per transaksi berdasarkan HPP **(test)**
- [ ] FR-10 Pencarian produk merespons di bawah 1 detik
- [ ] FR-11 Modul kasir dapat digunakan dengan barcode scanner
- [ ] FR-12 Checkout selesai di bawah 1 detik
- [ ] FR-13 Stok terpotong otomatis setiap transaksi berhasil **(test)**
- [ ] FR-14 Struk dapat dicetak ke printer thermal
- [ ] FR-15 Transaksi tetap berjalan saat koneksi terputus
- [ ] FR-16 Data offline tersinkron ke server setelah koneksi kembali **(test)**
- [ ] FR-17 Dashboard menyajikan omzet, laba rugi, dan nilai aset persediaan instan
- [ ] FR-18 Laporan keuangan akurat dan dapat diekspor

**Kebutuhan non-fungsional**

- [ ] NFR-01 Pencarian dan checkout di bawah 1 detik, buktikan dengan pengukuran nyata
- [ ] NFR-02 Otentikasi dan otorisasi berbasis peran berfungsi
- [ ] NFR-03 Setiap perubahan pada opname tercatat di riwayat
- [ ] NFR-04 Antarmuka ramah pengguna, target sentuh minimal 44 px
- [ ] NFR-05 Tersedia secara cloud dengan offline mode dasar
- [ ] NFR-06 Berjalan stabil di layanan cloud hosting
- [ ] NFR-07 HPP akurat dan stok terpotong otomatis
- [ ] NFR-08 Dokumentasi README lengkap dan source code diserahkan
- [ ] NFR-09 Aplikasi web dapat diakses jarak jauh

**Uji akhir manual.** Jalankan skenario ini dan pastikan lolos:

1. Login sebagai kasir, buat transaksi 2× Kopi Susu Gula Aren, 1× Croissant Cokelat, 3× Kopi Hitam Tubruk. Total harus Rp 117.700 dengan pajak 10 persen. Bayar tunai Rp 120.000, kembalian Rp 2.300.
2. Login sebagai admin, periksa stok Kopi Susu Gula Aren berkurang 2 dan riwayat mencatat baris penjualan dengan nomor struk itu.
3. Restock Matcha Latte 20 pcs @ Rp 14.200 dan pastikan HPP baru terhitung sesuai rumus rata-rata tertimbang.
4. Putuskan koneksi, buat tiga transaksi, sambungkan kembali, pastikan ketiganya tersinkron tanpa duplikat.
5. Login sebagai manajer dan pastikan menu stok tidak dirender, serta `GET /api/products` langsung menolak dengan 403.
6. Coba simpan produk dengan harga jual di bawah HPP dan pastikan ditolak dengan pesan yang benar.

---

## 12. Deployment dan hosting

Hasil akhir harus bisa di-deploy ke shared hosting atau VPS murah, karena anggaran proyek hanya Rp 2.500.000 untuk hosting (RAM minimal 2 GB, SSD, dukungan PHP dan Laravel) dan domain `.com` atau `.co.id` selama satu tahun. Tidak ada anggaran perangkat keras.

**Hasil build.** Frontend dibangun dengan `npm run build` menghasilkan `dist/`, lalu salin isinya ke `backend/public/` sehingga satu domain melayani API dan SPA sekaligus. Tambahkan route fallback Laravel yang mengembalikan `index.html` untuk semua path non-API agar React Router bekerja setelah refresh halaman.

**Berkas yang wajib dibuat:**

1. `.env.example` lengkap dengan seluruh kunci dan komentar penjelasan.
2. `README.md` berisi: prasyarat, instalasi lokal, perintah seed, cara build, dan langkah deploy.
3. `DEPLOYMENT.md` berisi langkah untuk cPanel shared hosting dan untuk VPS Ubuntu + Nginx, keduanya ditulis sebagai daftar perintah yang bisa disalin.
4. `docker-compose.yml` untuk development lokal (MySQL 8 + PHP 8.2 + Node 20).
5. `.github/workflows/deploy.yml` opsional: jalankan test, build frontend, lalu deploy lewat SSH atau FTP.
6. `deploy.sh` yang menjalankan `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, salin `dist`, `php artisan migrate --force`, lalu `config:cache`, `route:cache`, `view:cache`.

**Isi `.env` produksi:**

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kopihariini.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kopihariini_pos
DB_USERNAME=
DB_PASSWORD=
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=kopihariini.com
CACHE_STORE=file
QUEUE_CONNECTION=database
```

**Catatan khusus shared hosting cPanel:** banyak paket mengarahkan domain ke `public_html`. Sediakan instruksi untuk memindahkan isi `backend/public` ke `public_html` dan menyesuaikan `index.php` agar menunjuk ke folder aplikasi di luar `public_html`. Sertakan `.htaccess` yang memaksa HTTPS dan menangani rewrite SPA.

**Setelah deploy pertama**, jalankan `php artisan migrate --force` lalu `php artisan db:seed --class=ProductionSeeder` yang hanya membuat satu akun admin, kategori dasar, dan pengaturan toko — tanpa data demo.

**Backup.** Sertakan perintah `php artisan backup:db` sederhana yang menjalankan `mysqldump` ke folder `storage/backups` dan menyimpan tujuh salinan terakhir, lalu tulis contoh cron harian di `DEPLOYMENT.md`.

**Verifikasi akhir.** Setelah deploy, pastikan: HTTPS aktif, login berhasil, checkout tersimpan, dashboard menampilkan angka, dan refresh halaman di `/stok/produk` tidak menghasilkan 404.

---

## 13. Urutan pengerjaan dan aturan kerja

Kerjakan berurutan. Setelah tiap tahap, jalankan aplikasi dan buktikan tahap itu benar-benar jalan sebelum lanjut. Jangan menulis seluruh kode lalu baru menguji di akhir.

1. **Scaffold.** Buat struktur repo, `docker-compose.yml`, Laravel 11 + Sanctum, React + Vite + Tailwind dengan token warna dan font. Pastikan `docker compose up` menyalakan MySQL dan keduanya saling terhubung.
2. **Database.** Seluruh migrasi, model, relasi, dan enum. Jalankan `migrate:fresh` sampai bersih.
3. **Auth dan RBAC.** Login, token, middleware peran, policy, layar login, dan pengalihan ke beranda per peran.
4. **Inventory.** Master data produk, restock, `StockService`, `stock_movements`, layar produk dan restock.
5. **HPP.** `HppService` dua metode, pratinjau restock, layar HPP, lengkap dengan unit test.
6. **Kasir.** Cache produk, pencarian cepat, keranjang, checkout transaksional, struk, cetak.
7. **Opname dan riwayat.** Sesi draft, kunci, penyesuaian stok, layar riwayat dan ekspor CSV.
8. **Offline.** Dexie, antrean, deteksi koneksi, endpoint sync, layar antrean offline.
9. **Dashboard dan laporan.** Agregasi, grafik, laba rugi, ekspor PDF dan CSV.
10. **Seeder demo.** Enam bulan data sesuai bagian 9.
11. **Uji.** PHPUnit dan Vitest hijau, plus enam skenario manual di bagian 11.
12. **Deploy.** Build, dokumentasi, skrip deploy, dan verifikasi akhir.

**Aturan selama bekerja:**

- Jangan meminta konfirmasi untuk keputusan teknis kecil. Ambil keputusan yang masuk akal, catat di README, lanjutkan.
- Jika ada yang ambigu, pilih tafsiran paling sederhana yang memenuhi kriteria penerimaan di bagian 11.
- Setiap kali selesai satu tahap, laporkan dalam tiga baris: apa yang dibuat, cara mengujinya, apa yang berikutnya.
- Jangan pernah meninggalkan `TODO`, `FIXME`, data palsu yang di-hardcode di komponen, atau fungsi yang melempar `not implemented`.
- Jangan mengganti stack, menambah library besar, atau menambah fitur di luar dokumen ini tanpa alasan teknis yang ditulis.
- Commit per tahap dengan pesan jelas berbahasa Inggris, format `feat(kasir): offline queue sync`.

Mulai dari tahap 1 sekarang.

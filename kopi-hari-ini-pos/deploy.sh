#!/bin/bash
# deploy.sh — Script deployment untuk Kopi Hari Ini POS
# Jalankan dari root repositori: bash deploy.sh
set -e

echo "🚀 Memulai deployment Kopi Hari Ini POS..."

# ─── Backend ─────────────────────────────────────────────────────────────────
echo "📦 Install PHP dependencies..."
cd backend
composer install --no-dev --optimize-autoloader --no-interaction

echo "🔑 Generate app key jika belum ada..."
php artisan key:generate --no-interaction 2>/dev/null || true

echo "🗄️  Jalankan migrasi database..."
php artisan migrate --force --no-interaction

echo "⚡ Cache konfigurasi, route, dan view..."
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "🔗 Symlink storage..."
php artisan storage:link --no-interaction 2>/dev/null || true

cd ..

# ─── Frontend ────────────────────────────────────────────────────────────────
echo "🎨 Install Node dependencies..."
cd frontend
npm ci --silent

echo "🏗️  Build frontend..."
npm run build

echo "📂 Salin dist ke backend/public..."
# Hapus file lama kecuali index.php, .htaccess, dan robots.txt
find ../backend/public -maxdepth 1 -type f \
  ! -name 'index.php' ! -name '.htaccess' ! -name 'robots.txt' \
  -delete 2>/dev/null || true
find ../backend/public -maxdepth 1 -type d \
  ! -path ../backend/public \
  ! -name 'storage' \
  -exec rm -rf {} + 2>/dev/null || true

# Salin semua isi dist
cp -r dist/. ../backend/public/

cd ..

echo "✅ Deployment selesai!"
echo ""
echo "Langkah selanjutnya:"
echo "  1. Pastikan .env sudah dikonfigurasi dengan benar"
echo "  2. Jalankan: php artisan db:seed --class=ProductionSeeder"
echo "  3. Verifikasi HTTPS aktif dan login berhasil"

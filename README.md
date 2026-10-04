# AMAN BOOKING

> Visual Booking & Business Workflow Platform (Multi-Tenant SaaS)

AMAN BOOKING adalah platform reservasi dan otomatisasi alur kerja bisnis multi-tenant yang dirancang untuk berbagai jenis bisnis (UMKM, salon/barbershop, klinik, studio foto, penyewaan lapangan, rental, konsultasi, dan jasa terjadwal lainnya).

---

## 🚀 Teknologi Utama

- **Backend:** Laravel 12 (PHP 8.3+), MySQL/MariaDB InnoDB, Database Queue.
- **Frontend:** Inertia v2, React 19, TypeScript, Tailwind CSS v4, Vite.
- **Testing & Quality:** Pest PHP, Larastan (PHPStan Level 6), Laravel Pint, Vitest, Playwright, ESLint 9, Prettier.
- **Hosting Target:** cPanel / shared hosting (no persistent WebSocket, queue berbasis database + cron).

---

## 📁 Struktur Monolith Modular (`app/Domain/*`)

Sesuai spesifikasi **PRD 203**, logika bisnis dikelompokkan ke dalam domain masing-masing:
```text
app/Domain/
├── Identity/        # Auth, role, permission
├── Tenant/          # Isolasi tenant, resolver
├── Subscription/    # Plan, limit kuota
├── Business/        # Profil outlet, jam operasional
├── Catalog/         # Layanan, produk, paket
├── Resource/        # Staf, ruangan, unit, block time
├── Booking/         # State machine pemesanan, hold, reschedule
├── Availability/    # Single source of truth ketersediaan slot
├── Customer/        # Data pelanggan, guest booking
├── Workflow/        # Visual node flow, versioning
├── Automation/      # Trigger, jobs, retries
├── Payment/         # Transaksi DP & lunas, payment gateway
├── Inventory/       # Stok consumable & peralatan
├── Notification/    # WhatsApp, email, templates
├── Landing/         # Form builder, microsite
├── Reporting/       # Analitik pendapatan & okupansi
├── Audit/           # Immutable audit log
└── Integration/     # Google Calendar, iCal, webhooks
```

---

## 🛠️ Panduan Instalasi Lokal

### 1. Kebutuhan Sistem
- PHP 8.3+ dengan ekstensi: `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `zip`
- Composer 2+
- Node.js 22+ & npm 11+
- MySQL / MariaDB

### 2. Setup Awal
```bash
# Salin konfigurasi environment
cp .env.example .env

# Pasang dependensi PHP & Node
composer install
npm install

# Buat application key
php artisan key:generate

# Jalankan migrasi database
php artisan migrate
```

### 3. Menjalankan Server Pengembangan
```bash
# Terminal 1: Backend
php artisan serve

# Terminal 2: Frontend Vite
npm run dev
```

---

## 🧪 Pengujian & Pemeriksaan Kualitas Kode

### Backend
```bash
# Menjalankan test Pest
composer test

# Pemeriksaan linter Laravel Pint
composer pint

# Perbaikan format otomatis Pint
composer pint:fix

# Analisis statis Larastan (Level 6)
composer analyse
```

### Frontend
```bash
# Linter ESLint
npm run lint

# Format check Prettier
npm run format

# Format otomatis Prettier
npm run format:fix

# Unit & Component test (Vitest)
npm test

# E2E test (Playwright)
npm run test:e2e

# Build produksi (Dual Entry: app & public)
npm run build
```

---

## 📜 Dokumentasi Lengkap
- Dokumen PRD Utama: `docs/PRD_AMAN_BOOKING_LENGKAP.md`
- Rencana Kerja Fase: `docs/AMAN_BOOKING_WORK_PHASE_v1.2.md`
- Kumpulan Prompt Per Fase: `docs/AMAN_BOOKING_PROMPTS_PER_PHASE_v1.2.md`

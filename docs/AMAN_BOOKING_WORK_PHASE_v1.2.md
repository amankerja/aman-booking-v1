# AMAN BOOKING — WORK PHASE v1.2
## Rencana Implementasi Konkret: Phase 0 sampai Finalisasi & Peluncuran

**Pasangan dokumen:** `PRD_AMAN_BOOKING_LENGKAP.md`. Semua rujukan "bagian N" atau "N.x" (mis. bagian 202, 204.1, 208) mengacu ke PRD tersebut; bagian di dokumen ini diberi awalan **WP-**.
**Estimasi:** asumsi 1-2 developer full-time; jika solo kalikan sekitar 1,5x.

---

# WP-1. LANGKAH KONKRET IMPLEMENTASI

Estimasi memakai asumsi **1-2 developer full-time**. Jika solo, kalikan sekitar 1,5x. Setiap phase punya **Exit Criteria**: tidak lanjut ke phase berikut sebelum terpenuhi.

---

## PHASE 0 — FONDASI (Minggu 1-3)

**Tujuan:** kerangka yang kokoh, tenant aman, deploy pipeline jalan.

### Langkah

1. **Keputusan & dokumen** (hari 1-3)
   - Jawab daftar keputusan di bagian 202.
   - Bekukan PRD (`PRD_AMAN_BOOKING_LENGKAP.md`) sebagai baseline; buat backlog di tracker (GitHub Projects/Linear) berdasarkan prioritas P0-P3 (bagian 110).
   - Tulis ADR (Architecture Decision Record) singkat untuk tiap keputusan penting.
2. **Setup repositori & tooling**
   - Repo Git, branching: `main` (produksi), `develop`, feature branch.
   - `composer create-project laravel/laravel`, pasang Inertia + React + TypeScript + Tailwind + Vite (starter kit React resmi Laravel).
   - Pasang: Pest, Larastan (PHPStan level 6+), Laravel Pint, ESLint, Prettier, Husky/pre-commit.
   - GitHub Actions: lint → test → build.
3. **Environment**
   - Lokal (Laragon/Docker), staging di hosting yang sama spesifikasinya dengan produksi, produksi.
   - Pastikan versi PHP dan MySQL staging = produksi.
4. **Skema database fondasi** (migration + seeder)
   - `users`, `tenants`, `business_members`, `plans`, `subscriptions`, `subscription_usage`, `audit_logs`, `idempotency_keys`.
   - Semua tabel tenant memiliki `tenant_id` (index) dan soft delete bila relevan.
5. **Tenant & auth**
   - Registrasi pemilik usaha → membuat tenant + business awal.
   - Middleware `ResolveTenant`, trait `BelongsToTenant` + `TenantScope`.
   - Login/logout, reset password, verifikasi email, rate limit.
   - Role: Super Admin, Owner, Member (permission via spatie teams).
6. **Subscription foundation**
   - Plan + limit (JSON/tabel), service `LimitEnforcer` (`canCreate('services')`), status TRIAL/ACTIVE/…/SUSPENDED.
   - Middleware `EnsureSubscriptionActive` (read-only saat grace/expired).
7. **Audit log**
   - Observer/event generik: actor, role, tenant, aksi, before/after, IP.
8. **Design system dasar**
   - Token (warna, spacing, tipografi), komponen: Button, Input, Select, Modal, Drawer, Toast, Badge status (warna + ikon + label), Table, EmptyState, Skeleton.
   - Layout: `OwnerLayout` (sidebar penuh / ringkas / bottom-nav), `AdminLayout`, `PublicLayout`.
9. **Pipeline deploy**
   - Skrip deploy: build lokal/CI → `rsync`/zip → `php artisan migrate --force` → `config:cache route:cache view:cache` → symlink/rilis.
   - Tulis runbook deploy + rollback.

### Exit Criteria
- Owner bisa daftar, login, dan melihat dashboard kosong.
- Test **tenant isolation** otomatis lulus.
- Deploy ke staging dengan satu perintah.
- Limit plan berfungsi pada satu entitas contoh.

---

## PHASE 1 — BOOKING CORE (Minggu 4-9)

**Tujuan:** engine booking benar dan teruji, walau UI masih sederhana.

### Langkah

1. **Business & jadwal**
   - CRUD profil bisnis, timezone, jam operasional, break, holiday, blackout.
2. **Catalog**
   - CRUD service dengan mode "Basic" dulu (nama, harga, durasi), "Advanced" (buffer, kapasitas, staff, resource) menyusul di langkah ini.
   - Aturan hapus: arsip, bukan hard delete.
3. **Resource engine**
   - `resource_types` (extensible), `resources`, grup, visibilitas PUBLIC/INTERNAL.
   - Staff + skill + jadwal + cuti; room; schedule per resource; block time.
   - Relasi service ↔ staff/resource/room (required/optional, mode assignment).
4. **AvailabilityService** (inti)
   - Implementasi bertahap: (a) service tunggal + 1 staff, (b) + resource/room, (c) + buffer, (d) + kapasitas, (e) multi-resource paralel.
   - Unit test per tahap, termasuk kasus bagian 19, 20, 197, 198, 165.
5. **BookingService & state machine**
   - Aksi `CreateBooking` mengikuti urutan bagian 195, dengan lock bagian 204.1.
   - Snapshot service/harga (bagian 144), kode booking `BK-YYYYMMDD-NNNNN`.
   - `BookingStateMachine` + tabel `booking_status_history`.
   - Idempotency pada create.
6. **Customer module**
   - Tabel customer per tenant, dedup berdasarkan WhatsApp/email, catatan, tag.
7. **Owner UI tahap 1**
   - Quick Booking (bagian 158), Semua Booking (tabel + filter + detail drawer), Kalender Day/Week/Agenda, Kanban dasar.
   - Auto-assign staff/resource dan manual assign.
8. **Uji konkurensi & timezone**
   - Script 50 request paralel; tes lintas timezone (Asia/Jakarta, Makassar, Jayapura).

### Exit Criteria
- 50 request paralel ke slot sama → tepat 1 booking.
- Booking manual owner dan availability konsisten.
- Cakupan unit test Availability dan Booking ≥ 85%.

---

## PHASE 2 — PENGALAMAN PUBLIK (Minggu 10-13)

**Tujuan:** customer bisa booking dari HP dengan cepat.

### Langkah

1. **Routing publik**: `/{slug}`, `/{slug}/booking`, `/{slug}/booking/success`, `/{slug}/booking/manage/{token}`.
2. **Landing page (Blade)**
   - Section: Hero, Layanan, Tentang, Galeri, FAQ, Kontak, CTA (bagian 28).
   - Section disimpan sebagai JSON (`landing_sections`), dirender server-side, meta SEO + Open Graph.
3. **Booking flow (React island)**
   - Layanan → (staff/resource) → tanggal → jam → data customer → review → konfirmasi (bagian 63).
   - Simpan input sementara, error dekat field, tombol besar, keyboard type sesuai.
   - Quick Booking mode (bagian 31).
4. **Konfirmasi & manage booking**
   - Halaman sukses, tombol Tambah ke kalender (.ics), WhatsApp, Reschedule, Cancel sesuai policy, Booking Lagi.
5. **Reschedule & cancellation** sesuai policy (bagian 33, 34).
6. **Notifikasi dasar**
   - Email konfirmasi via queue; tautan `wa.me` dengan teks terisi otomatis.
   - Template pesan + variabel (bagian 183); log notifikasi + retry.
7. **Optimasi performa**
   - Pecah bundle (public vs app), kompres gambar saat upload, cache header aset, audit Lighthouse (target mobile ≥ 90).
8. **Onboarding wizard** (bagian 48) + **template bisnis tahap awal** (Barber, Salon, Spa, Sports Court, Rental, Custom). Template di-install sebagai draft untuk di-review owner (bagian 168).

### Exit Criteria
- Dari HP: buka link → booking selesai < 60 detik.
- Lighthouse mobile ≥ 90 pada landing dan booking page.
- Owner baru bisa publish bisnis < 15 menit (uji dengan 3-5 orang nyata).

---

## PHASE 3 — WORKFLOW & KUSTOMISASI (Minggu 14-20)

### Langkah

1. **Custom status + Kanban penuh**
   - Status kustom dengan pemetaan ke kategori sistem; validasi transisi saat drag.
2. **Form builder**
   - Tipe field, kondisional (bagian 27), render di booking flow publik, simpan di `booking_custom_fields`.
3. **Workflow builder**
   - Canvas XYFlow: node library, properti, undo/redo, zoom, autosave draft.
   - Node MVP: Trigger (Booking Created, Status Changed), Condition (Payment Status, Status, Service), Action (Change Status, Assign Resource, Send Notification), Delay.
   - Draft → Publish → Version immutable; booking terikat ke versi workflow.
4. **Workflow runner**
   - Event listener → run → eksekusi node via queue; log eksekusi, retry, status failed, pemulihan manual.
   - Proteksi loop dan duplikasi (bagian 62).
5. **Template workflow & form** per jenis bisnis (bagian 47) dan versioning template (bagian 186).
6. **Landing page builder sederhana**: atur urutan section, warna, logo, preview desktop/tablet/mobile, publish/unpublish. [SELESAI]

### Exit Criteria [LULUS - docs/gate-phase-3.md]
- Owner membuat workflow Salon (Pending Payment → Confirmed → … → Completed) tanpa bantuan developer. [LULUS]
- Workflow gagal tidak menghilangkan booking dan bisa di-retry. [LULUS]
- Test loop/duplicate execution lulus. [LULUS]

---

## PHASE 4 — OPERASIONAL BISNIS (Minggu 21-26)

### Langkah

1. **Payment**
   - Integrasi gateway (Midtrans & Xendit): invoice, deposit/penuh, webhook idempotent, verifikasi signature. [SELESAI]
   - Temporary hold (bagian 139) + job expire hold (Phase 4.2). [SELESAI]
   - Status payment lengkap, refund manual dicatat dengan approval gate Owner. [SELESAI]
2. **Check-in**: manual, kode booking, QR. [SELESAI]
3. **Inventory opsional**: item, mutasi, reserve/deduct/release sesuai mode (bagian 17).
4. **Fitur lanjutan engine**: multi-service, sequential, paket/komposit, grup/peserta, variasi durasi, service dependency.
5. **Reporting MVP**: booking, revenue, customer baru/repeat, utilization resource (formula bagian 162). Query diindeks; laporan berat dihitung terjadwal (tabel agregat harian) bila perlu.
6. **CRM ringan**: riwayat booking/pembayaran/no-show, tag, consent pemasaran terpisah.
7. **Super Admin**: dashboard tenant, plan management, suspend/activate, extend trial, support access berlog.

### Exit Criteria
- Callback payment ganda tidak menghasilkan data ganda (test replay). [LULUS]
- Hold kadaluarsa melepas slot otomatis. [LULUS]
- Super Admin dapat suspend tenant dan halaman publik menampilkan "tidak tersedia".

---

## PHASE 5 — OTOMASI & INTEGRASI (Minggu 27-32)

1. WhatsApp API/provider + AMAN CHAT: konfirmasi, reminder H-1/H-2, follow-up, repeat booking.
2. Reminder pintar per template (bagian 182).
3. Integrasi AMAN KASIR: event `BookingCompleted` → buat transaksi (via webhook/API bertanda tangan).
4. Sinkronisasi kalender (.ics feed per staff; Google Calendar bila diperlukan).
5. Waitlist (bagian 136) dan overbooking opsional (bagian 154).
6. Template bisnis tambahan hingga katalog 53 (bagian 191), dirilis bertahap berdasarkan permintaan pasar.

### Exit Criteria
- Kegagalan WhatsApp tidak mempengaruhi booking; retry terbatas + dead letter terlihat di UI.
- Satu alur lengkap Booking → Completed → transaksi AMAN KASIR terbukti.

---

## PHASE 6 — LANJUTAN (setelah rilis, iteratif)

REST API + API key, webhook keluar, custom domain, subdomain wildcard, multi-outlet, analitik lanjutan, marketplace template, migrasi ke VPS/Redis bila trigger tercapai (bagian 208 PRD).

---

# WP-2. FASE FINALISASI & PELUNCURAN (Minggu 33-38, atau paralel akhir Phase 4-5)

## WP-2.1 Hardening
1. **Audit keamanan internal**: checklist OWASP Top 10, uji tenant isolation menyeluruh, uji IDOR, uji upload, uji rate limit, uji replay payment.
2. **Dependency audit**: `composer audit`, `npm audit`, kunci versi.
3. **Uji beban**: k6/Artillery — 50-100 booking concurrent di slot berbeda dan sama; ukur p95 availability dan create booking; pastikan tidak ada deadlock.
4. **Backup & restore drill**: backup DB harian + file; **restore benar-benar dicoba** di staging dan dicatat waktunya (bagian 88).
5. **Observability**: log terstruktur dengan correlation ID, error monitoring (Sentry/Flare), halaman status internal untuk antrian gagal.

## WP-2.2 Kualitas
6. **Aksesibilitas**: navigasi keyboard owner, label form, kontras, status tidak hanya warna.
7. **Uji perangkat nyata**: minimal 3 HP Android, 1 iPhone, 1 tablet, 2 browser desktop.
8. **UAT dengan 3-5 bisnis pilot** (mis. barber, salon/spa, lapangan, rental) selama 2-4 minggu; kumpulkan masalah, perbaiki, ulangi.
9. **Definition of Done** (bagian 107) dicek untuk setiap modul; tidak ada bug severity tinggi terbuka.

## WP-2.3 Legal & operasional
10. Syarat & Ketentuan, Kebijakan Privasi, perjanjian pemrosesan data untuk tenant (konsultasi hukum).
11. Prosedur dukungan: kanal bantuan, SLA sederhana, template jawaban, halaman dokumentasi/FAQ onboarding.
12. Pricing final, halaman penawaran, alur upgrade/downgrade, kebijakan refund subscription.

## WP-2.4 Checklist deploy produksi (shared hosting)
1. Pilih PHP 8.3 di cPanel (MultiPHP Manager), aktifkan ekstensi yang diperlukan.
2. Unggah kode **di luar** `public_html` (mis. `/home/USER/aman-booking`).
3. Isi `public_html` (atau subdomain root) dengan isi folder `public/`, sesuaikan path di `index.php` ke `../aman-booking/...`; atau ubah document root langsung ke `public/` bila bisa (lihat bagian 209 PRD).
4. Salin `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, kunci aplikasi baru, kredensial DB, mail, payment (kunci live).
5. `php artisan migrate --force`, `php artisan storage:link` (atau salin/arahkan aset bila symlink diblokir), `config:cache`, `route:cache`, `view:cache`.
6. Buat Cron Job per menit (204.6) dan cron backup.
7. Pastikan folder `storage/` dan `bootstrap/cache/` writable; blokir akses langsung ke `.env`, `storage`, `vendor`.
8. Aktifkan SSL + redirect HTTPS; uji ulang webhook payment dengan URL produksi.
9. Smoke test: daftar → buat bisnis → booking → payment sandbox/live kecil → notifikasi.
10. Rollback plan: simpan rilis sebelumnya + backup DB sebelum migrasi.

## WP-2.5 Peluncuran bertahap
- **Soft launch**: undangan terbatas (pilot) + monitoring harian.
- **Public launch**: buka registrasi dengan trial; pantau KPI bagian 77 dan 105.
- **Pasca-rilis (30 hari)**: perbaikan cepat, tinjau metrik (konversi landing→booking, waktu onboarding, no-show), prioritaskan backlog Phase 5-6.

---

# WP-3. KALENDER ESTIMASI RINGKAS

| Phase | Durasi | Hasil utama |
|---|---|---|
| 0 Fondasi | 3 minggu | Tenant, auth, subscription, deploy pipeline |
| 1 Booking Core | 6 minggu | Availability + booking aman, kalender owner |
| 2 Publik | 4 minggu | Landing + booking mobile + template awal |
| 3 Workflow | 7 minggu | Workflow builder, form builder, landing builder |
| 4 Operasional | 6 minggu | Payment, check-in, inventory, laporan, Super Admin |
| 5 Integrasi | 6 minggu | WhatsApp, AMAN CHAT, AMAN KASIR, waitlist |
| Finalisasi | 4-6 minggu | Hardening, UAT, legal, launch |
| **Total** | **± 36-38 minggu** | MVP komersial penuh |

**Rilis MVP lebih cepat (opsional):** setelah Phase 2 + sebagian Phase 3 (Kanban, status kustom, form builder, notifikasi dasar) sekitar **minggu 18-20** sudah bisa dibuka untuk pilot terbatas, karena workflow builder visual penuh bukan syarat untuk bisnis yang puas dengan workflow default.

---

# WP-4. REGISTER RISIKO

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Availability salah / bentrok | Kepercayaan hilang | Satu service, lock transaksi, test konkurensi, code review wajib |
| Scope creep (builder terlalu kompleks) | Jadwal molor | Patuhi bagian 80 dan 108; fitur baru harus mendukung core journey |
| Shared hosting terlalu terbatas | Antrian lambat, timeout | Trigger migrasi (bagian 208 PRD), semua infrastruktur lewat abstraksi Laravel |
| Notifikasi WhatsApp diblokir/ditolak | Reminder gagal | Fallback email + tautan wa.me, status gagal terlihat |
| Workflow salah konfigurasi owner | Booking tersangkut | Tombol Test, log, retry, default workflow aman |
| Kebocoran data antar tenant | Kritis | Global scope, test otomatis, review khusus, pentest sebelum launch |
| Performa HP kelas bawah | Konversi turun | Budget bundle, uji di HP murah, island kecil |
| Ketergantungan satu developer | Bus factor | Dokumentasi, ADR, runbook, CI wajib |

---

# WP-5. DEFINITION OF READY (SEBELUM TASK DIKERJAKAN)

- Ada user story + acceptance criteria dari PRD (nomor bagian dirujuk).
- Dampak tenant isolation, permission, audit, dan mobile sudah dinilai.
- Desain UI/state (loading, kosong, error) tersedia.
- Test yang akan ditulis sudah disebutkan.

---

# WP-6. KRITERIA SELESAI AKHIR (FINALISASI PRODUK)

AMAN BOOKING dinyatakan siap rilis komersial jika:

1. Seluruh 10 kriteria MVP bagian 104 terpenuhi dan terukur.
2. Uji konkurensi, tenant isolation, replay payment, dan restore backup lulus dan terdokumentasi.
3. Minimal 3 bisnis pilot berbeda jenis menjalankan booking nyata selama ≥ 2 minggu tanpa insiden data.
4. Lighthouse mobile ≥ 90 untuk halaman publik; budget performa bagian 204.8 PRD terpenuhi.
5. Runbook deploy, rollback, backup, dan penanganan insiden tersedia.
6. Dokumen legal dan halaman bantuan terbit.
7. Tidak ada bug severity tinggi/kritis yang terbuka.


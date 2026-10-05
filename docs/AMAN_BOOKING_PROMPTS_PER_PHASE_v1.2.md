# AMAN BOOKING — PROMPT PER PHASE v1.2
## Kumpulan prompt siap tempel untuk menjalankan Phase 0 sampai Peluncuran

**Dokumen pasangan:** `PRD_AMAN_BOOKING_LENGKAP.md` dan `AMAN_BOOKING_WORK_PHASE_v1.2.md`.
**Cocok untuk:** Claude Code (terminal/desktop/web) atau asisten coding sejenis yang bisa membaca dan menulis file di repo.

---

# A. CARA PAKAI

1. Buat repo kosong, lalu taruh tiga dokumen di folder `docs/`:
   - `docs/PRD_AMAN_BOOKING_LENGKAP.md`
   - `docs/AMAN_BOOKING_WORK_PHASE_v1.2.md`
   - `docs/AMAN_BOOKING_PROMPTS_PER_PHASE_v1.2.md` (file ini)
2. Tempel **Master Prompt (bagian B)** sebagai `CLAUDE.md` di root repo. Ini membuat setiap sesi baru langsung paham aturan proyek.
3. Jalankan prompt **berurutan**, satu prompt per sesi atau per cabang Git. Jangan menggabungkan banyak prompt sekaligus.
4. Setelah tiap prompt: jalankan test, baca diff, commit. Baru lanjut.
5. Di akhir tiap phase jalankan **Prompt Gate** (`X.G`). Jangan masuk phase berikutnya sebelum gate lulus.
6. Jika sesi putus atau konteks penuh, pakai **Prompt Lanjutkan Sesi (C.1)**.
7. Teks dalam `[KURUNG SIKU]` adalah isian yang harus Anda ganti.

Catatan penting:
- Prompt meminta **rencana singkat dulu** untuk pekerjaan besar. Baca rencananya sebelum menyetujui.
- Keputusan bisnis (payment gateway, harga plan, provider WhatsApp) harus Anda putuskan sendiri; lihat bagian 202 PRD.
- Dokumen legal (syarat, privasi) hanya draf; wajib ditinjau konsultan hukum.

---

# STATUS PROGRESS IMPLEMENTASI

**Update Terakhir:** 05 Oktober 2026  
**Status Saat Ini:** Phase 4.2 Selesai (Reservation Hold with hold_expires_at, bookings:expire-holds command per minute, AvailabilityService exclusion, Customer live countdown timer & expired banner, Race condition protection with row locking, 8/8 hold tests pass; Total 324 Pest tests pass, Larastan Lvl 6 Clean, Vite Build Clean), Siap Masuk Phase 4.3 (Check-in Module)

| Phase | Langkah | Deskripsi | Status | Commit / Verifikasi |
|---|---|---|---|---|
| **Phase 0** | **0.1** | Setup repositori & tooling (Laravel 12, Inertia v2, React 19, TS, Tailwind v4, Vite dual-entry, Pest, Larastan Lvl 6, Pint, ESLint 9, Prettier, Vitest, Playwright, CI) | **SELESAI** | `96bec4f` (Test, Pint, Analyse, Build 100% Green) |
| **Phase 0** | **0.2** | Skema database fondasi (9 tabel: users, tenants, businesses, members, plans, subscriptions, usage, audit, idempotency + 9 model, factory, seeder) | **SELESAI** | `9a53cee` (12 Pest tests pass, MySQL migrate:fresh --seed pass) |
| **Phase 0** | **0.3** | Tenant, auth, dan role (BelongsToTenant, TenantScope, ResolveTenant, Spatie teams, auth flow, dashboard blank state, test isolasi tenant) | **SELESAI** | `196aa12` (35 Pest tests pass, Larastan L6 0 errors, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 0** | **0.4** | Subscription & limit kuota plan | **SELESAI** | `3c7174e` (46 Pest tests pass, Larastan L6 0 errors, Pint clean, ESLint clean, Vite build pass) |
| **Phase 0** | **0.5** | Audit log terpusat & observer | **SELESAI** | `c08603d` (51 Pest tests pass, Larastan L6 0 errors, Pint clean, ESLint clean, Vite build pass) |
| **Phase 0** | **0.6** | Design system dasar & layout (15 headless UI components, 3 responsive layouts, interactive /app/_styleguide, strict OFALabs design system) | **SELESAI** | `81f2330` (54 Pest pass, 11 Vitest pass, Larastan L6 clean, Pint clean, ESLint clean, Vite build pass) |
| **Phase 0** | **0.7** | Pipeline deploy hosting cPanel (deploy.sh zero-downtime, GitHub Actions workflow, queue scheduler per menit, runbook cPanel, /up health check) | **SELESAI** | `9a8d80a` (55 Pest pass, 11 Vitest pass, Pint clean, Larastan L6 clean, Vite build pass) |
| **Phase 0** | **0.G** | Gate Phase 0 (Exit criteria verification) | **SELESAI** | `docs/gate-phase-0.md` (Gate LULUS 100%, 55 Pest pass, 11 Vitest pass) |
| **Phase 1** | **1.1** | Business, jadwal, dan kalender kerja (Profil bisnis, kebijakan/aturan booking, logo upload, 7-hari jam operasional + breaks, hari libur & blackout kalender, BusinessCalendarService) | **SELESAI** | `32c59aa` (77 Pest pass, 11 Vitest pass, Larastan L6 0 errors, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.2** | Layanan, varian, dan add-on (Katalog layanan CRUD, varian harga & durasi, add-on ekstra, kategori, durasi fleksibel fixed/quantity/size/variable, buffer engine, LimitEnforcer kuota, aturan arsip) | **SELESAI** | `bef6c48` (89 Pest pass, 11 Vitest pass, Larastan L6 0 errors, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.3** | Resource engine (staff, room, equipment, schedule, cuti, time block, service compatibility) | **SELESAI** | `5e93d7d` (107 Pest pass, 11 Vitest pass, Larastan L6 clean, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.4a** | AvailabilityService — tahap dasar (irisan jam bisnis, jadwal service & staff, breaks, holiday, blackout, slot step) | **SELESAI** | `2e74116` (123 Pest pass, 11 Vitest pass, Larastan L6 0 errors, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.4b** | AvailabilityService — resource, buffer, kapasitas, paralel (room/equipment, buffer sebelum/sesudah, group capacity, p95 benchmark) | **SELESAI** | `2002c0b` (131 Pest pass, 11 Vitest pass, Larastan L6 0 errors, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.5** | BookingService, state machine, idempotency (Action CreateBooking, lock baris resource FOR UPDATE, idempotency key, snapshot service & harga, tabel booking) | **SELESAI** | `9e492e5` (147 Pest pass, 11 Vitest pass, Larastan L6 0 errors, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.6** | Customer module (normalisasi nomor WA +62, dedup per tenant, catatan, tag, riwayat booking, counter no-show, consent pemasaran terpisah, pencarian cepat, merge manual, export CSV protection) | **SELESAI** | `1d9031c` (159 Pest pass, 11 Vitest pass, Larastan L6 0 errors, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.7** | Owner UI tahap 1 (Quick Booking PRD 158, Semua Booking tabel + filter + detail drawer, Kalender Day/Week/Agenda, Kanban dasar) | **SELESAI** | `3e12740` (166 Pest pass, 11 Vitest pass, Larastan L6 0 errors, Pint clean, ESLint clean, Prettier clean, Vite build pass) |
| **Phase 1** | **1.8** | Uji konkurensi & timezone (Script 50 request paralel; tes lintas timezone Asia/Jakarta, Makassar, Jayapura) | **SELESAI** | `docs/uji-konkurensi-timezone.md` (50 paralel 1 sukses 49 ditolak, 0 deadlock, 174 Pest pass) |
| **Phase 1** | **1.G** | Gate Phase 1 (Exit criteria verification) | **SELESAI** | `docs/gate-phase-1.md` (Gate LULUS 100%, 174 Pest pass, 11 Vitest pass) |
| **Phase 2** | **2.1** | Routing publik & Landing page (/{slug}, section: hero, layanan, tentang, galeri, FAQ, kontak, CTA) | **SELESAI** | `8b4f33f` (Server-rendered Blade, < 18KB inlined CSS, SEO & JSON-LD, 183 Pest pass, 11 Vitest pass) |
| **Phase 2** | **2.2** | Booking flow (React island di public.tsx: layanan, staff, tanggal, jam, data customer, review, idempotency UUID) | **SELESAI** | `5b33cd2` (React island, 14-day strip, availability real-time slots, idempotent submission, 192 Pest pass, bundle < 150 KB gzip) |
| **Phase 2** | **2.3** | Konfirmasi, kelola booking, reschedule, cancel (QR code, token unik, countdown, audit trail) | **SELESAI** | `1a575e8` (208 Pest pass 1320 assertions, SHA-256 token security, RFC 5545 .ics export, reactive reschedule/cancel portal) |
| **Phase 2** | **2.4** | Notifikasi dasar (WhatsApp notification webhook/stub, reminder H-1, email draf) | **SELESAI** | `62f9256` (220 Pest pass, NotificationEngine, WhatsApp & Email stub, auto retry & dead letter queue) |
| **Phase 2** | **2.5** | Optimasi performa publik (Mobile Lighthouse >= 90, gzip bundle < 150 KB, asset preconnect) | **SELESAI** | `d94e3fa` (226 Pest pass, ImageOptimizationService WebP resize, .htaccess caching & gzip, bundle split < 132 KB, zero render-blocking JS) |
| **Phase 2** | **2.6** | Onboarding wizard & template bisnis awal (Preset salon/barber, klinik, studio foto, les privat, rental) | **SELESAI** | `623d4b9` (8-step wizard, 6 preset templates, DRAFT safety, docs/uji-onboarding.md < 7 min avg, 235 Pest pass) |
| **Phase 2** | **2.G** | Gate Phase 2 (Exit criteria verification) | **SELESAI** | `docs/gate-phase-2.md` (Gate LULUS 100%, 238 Pest pass, 11 Vitest pass, mobile booking < 30s) |
| **Phase 3** | **3.1** | Custom status & Kanban penuh (PRD 24, 25, 140-141, 213) | **SELESAI** | BookingStatus model, seeding 7 default statuses, drag with state machine, payment guard, mobile segmented control, 249 Pest pass |
| **Phase 3** | **3.2** | Form builder & conditional form (PRD 26, 27, 179) | **SELESAI** | 14 field types, server condition evaluation, secure upload, public & quick booking integration, 258 Pest pass |
| **Phase 3** | **3.3** | Workflow builder (UI XYFlow) | **SELESAI** | @xyflow/react canvas, immutable versioning, Kahn topological sort, dry-run simulation, 5 business presets, 272 Pest pass |
| **Phase 3** | **3.4** | Workflow runner | **SELESAI** | Domain event listeners, queue runner, delay scheduler, max depth guard (50), idempotent node execution, retry failed runs, 283 Pest pass |
| **Phase 3** | **3.5** | Template workflow & form + versioning template | **SELESAI** | System templates across verticals, immutable SemVer versions, tenant update diff notification & safe draft opt-in, Super Admin portal, 293 Pest pass |
| **Phase 3** | **3.6** | Landing page builder sederhana | **SELESAI** | Section reordering, brand preset, mobile preview, XSS sanitization, live public blade integration, 306 Pest pass |
| **Phase 3** | **3.G** | Gate Phase 3 (Exit criteria verification) | **SELESAI** | `docs/gate-phase-3.md` (Gate LULUS 100%, 306 Pest pass) |
| **Phase 4** | **4.1** | Payment gateway (Midtrans / Xendit, Invoices, Webhooks, Idempotency, Refund approval gate, Cashier) | **SELESAI** | Invoices & payments schema, Midtrans SHA512 signature, Xendit callback token, replay idempotent, manual payment & refund gate, 316 Pest pass |
| **Phase 4** | **4.2** | Reservation hold (Temporary hold, job expire hold, availability lock) | **SELESAI** | hold_expires_at, expire-holds command per menit, Availability exclusion, UI countdown & expired alert, race protection lockForUpdate, 324 Pest pass |
| **Phase 4** | **4.3** | Check-in (Manual, kode booking, QR) | **BERIKUTNYA** | Siap dikerjakan |

---

# B. MASTER PROMPT (SIMPAN SEBAGAI `CLAUDE.md`)

```text
# AMAN BOOKING — Aturan Proyek

## Konteks
AMAN BOOKING adalah platform booking multi-tenant (SaaS) berbahasa Indonesia untuk banyak jenis bisnis.
Sumber kebenaran produk: docs/PRD_AMAN_BOOKING_LENGKAP.md. Rencana kerja: docs/AMAN_BOOKING_WORK_PHASE_v1.2.md.
Jika PRD ambigu atau bertentangan, ikuti bagian 210 (Errata). Jika masih ragu, TANYA sebelum menulis kode.

## Stack (tidak boleh diganti tanpa persetujuan)
Laravel 12 (PHP 8.3+), Inertia v2, React 19 + TypeScript, Tailwind CSS v4 + komponen headless,
XYFlow (workflow), dnd-kit (Kanban), MySQL/MariaDB InnoDB, database queue + cron, Vite.
Test: Pest, Vitest, Playwright. Lint: Pint, Larastan, ESLint, Prettier.
Target hosting: cPanel/shared hosting (tanpa WebSocket, tanpa proses long-running, build frontend di lokal/CI).

## Aturan wajib (tidak boleh dilanggar)
1. Seluruh aturan bisnis (availability, harga, status, policy) HANYA di backend (app/Domain/*). Frontend tidak menghitungnya.
2. Availability punya SATU sumber kebenaran: AvailabilityService. Dilarang membuat algoritma slot kedua.
3. Pembuatan booking: transaksi DB + row lock resource + cek ulang konflik + idempotency key (PRD 204.1, 204.3).
4. Semua tabel milik tenant memakai tenant_id + TenantScope. Setiap route baru wajib punya test isolasi tenant.
5. Otorisasi di server (Policy/Gate). Menyembunyikan menu di UI bukan kontrol akses.
6. Waktu disimpan UTC (DATETIME). Uang bigint rupiah. Tidak pakai float untuk uang.
7. Tidak ada hard delete untuk booking, service, resource, customer, pembayaran. Pakai arsip/soft delete.
8. Perubahan penting wajib masuk audit log (PRD 52).
9. Notifikasi dan workflow berjalan async lewat queue; kegagalan tidak boleh menghilangkan booking.
10. Customer tidak wajib punya akun. Halaman publik harus ringan (budget PRD 204.8).
11. Pesan error untuk pengguna ramah dan tidak membocorkan detail teknis (PRD 218).
12. Jangan menambah fitur di luar permintaan. Jangan refactor besar tanpa diminta.

## Konvensi
- Kode di app/Domain/<Modul>/ (Actions, Services, Models, Policies, Events). Controller tipis.
- Nama tabel snake_case jamak. Route: /app/* owner, /admin/* super admin, /{slug}/* publik, /api/v1/* API.
- Bahasa UI: Indonesia, teks lewat lang/ (siapkan i18n).
- Commit kecil, pesan jelas. Satu perubahan logis per commit.

## Definition of Done (PRD 107)
UI, API, validasi, permission, audit (jika perlu), state loading/kosong/error, responsif mobile,
unit test, integration test, E2E untuk flow utama, dokumentasi singkat, tidak ada regresi.

## Cara kerja
- Untuk tugas besar: tulis rencana singkat (file yang akan dibuat/diubah, risiko), tunggu persetujuan, baru kerjakan.
- Selesai tugas: jalankan lint + test, lalu laporkan: apa yang dibuat, apa yang diuji, apa yang belum, keputusan yang perlu saya ambil.
- Jangan mengklaim sesuatu "selesai" bila test belum dijalankan. Katakan apa adanya jika ada yang gagal.
```

---

# C. PROMPT UTILITAS (DIPAKAI DI PHASE MANA PUN)

## C.1 Lanjutkan sesi

```text
Baca CLAUDE.md, lalu docs/AMAN_BOOKING_WORK_PHASE_v1.2.md. Periksa git log dan git status untuk mengetahui pekerjaan terakhir.
Saya sedang mengerjakan [PHASE & LANGKAH, mis. Phase 1 langkah 4]. Ringkas dalam 5-8 baris: apa yang sudah selesai,
apa yang setengah jadi, dan langkah berikutnya yang paling masuk akal. Jangan mengubah kode dulu. Tunggu konfirmasi saya.
```

## C.2 Review diff sebelum commit

```text
Review perubahan yang belum di-commit (git diff). Periksa terhadap aturan wajib di CLAUDE.md dan PRD bagian [NOMOR].
Cari secara khusus: kebocoran tenant (query tanpa tenant scope), logika bisnis di frontend, race condition, hard delete,
tidak ada audit/permission, error yang membocorkan detail teknis, dan test yang hilang. Laporkan temuan berurutan
dari paling berbahaya. Jangan memperbaiki apa pun dulu; beri daftar saja.
```

## C.3 Perbaiki bug dengan test dulu

```text
Bug: [DESKRIPSI + LANGKAH REPRODUKSI + PESAN ERROR].
Langkah: (1) tulis test yang gagal dan mereproduksi bug, (2) jelaskan akar masalah dalam 3 kalimat,
(3) perbaiki seminimal mungkin, (4) jalankan seluruh test modul terkait, (5) laporkan. Jangan mengubah perilaku lain.
```

## C.4 Audit keamanan modul

```text
Audit keamanan modul [NAMA MODUL] di app/Domain/[MODUL] dan controller terkait.
Periksa: tenant isolation, IDOR (akses ID tenant lain), mass assignment, validasi input, otorisasi per aksi,
rate limit, penyimpanan rahasia, upload file, dan injeksi. Untuk setiap temuan: lokasi file:baris, dampak, perbaikan yang disarankan.
Tulis test yang membuktikan temuan nyata. Jangan memperbaiki sebelum saya setujui.
```

## C.5 Tambah test yang kurang

```text
Untuk modul [MODUL], daftar skenario dari PRD bagian [NOMOR] (termasuk edge case di bagian 51) yang belum punya test.
Tulis test Pest untuk semua yang kurang. Pastikan test gagal bila logikanya dirusak (uji dengan mengubah sementara satu aturan).
Laporkan cakupan sebelum dan sesudah.
```

## C.6 Ringkasan keputusan arsitektur (ADR)

```text
Tulis ADR singkat di docs/adr/NNN-[judul].md untuk keputusan: [KEPUTUSAN]. Format: Konteks, Opsi yang dipertimbangkan,
Keputusan, Konsekuensi. Maksimal satu halaman. Rujuk bagian PRD yang relevan.
```

---

# PHASE 0 — FONDASI

## 0.1 Setup repositori & tooling [SELESAI - Commit: 96bec4f]

```text
Tugas: setup proyek AMAN BOOKING sesuai docs/AMAN_BOOKING_WORK_PHASE_v1.2.md Phase 0 langkah 2.
Buat proyek Laravel 12 dengan starter kit Inertia + React + TypeScript + Tailwind v4 + Vite.
Pasang dan konfigurasi: Pest, Larastan (level 6), Laravel Pint, ESLint, Prettier, Vitest, Playwright (setup dasar), husky/pre-commit.
Buat struktur folder app/Domain/* sesuai PRD 203 (cukup folder + README singkat per modul).
Buat dua entry Vite: resources/js/app.tsx (owner/admin) dan resources/js/public.tsx (publik), dengan code-splitting terpisah.
Buat workflow GitHub Actions: lint -> test -> build.
Tulis README (cara menjalankan lokal, test, build) dan salin docs ke docs/.
Tulis rencana singkat dulu, tunggu persetujuan, baru kerjakan. Setelah selesai: pastikan `composer test`, `npm run lint`, `npm run build` hijau.
```

## 0.2 Skema database fondasi [SELESAI - Commit: 9a53cee]

```text
Buat migration dan model untuk tabel fondasi sesuai PRD 214: users, tenants, businesses, business_members, plans, subscriptions,
subscription_usage, audit_logs, idempotency_keys. Semua tabel milik tenant punya tenant_id + index.
Gunakan DATETIME (UTC), bigint untuk uang, utf8mb4, engine InnoDB. Tambahkan factory dan seeder (plan BASIC/PRO/BUSINESS dari PRD 217,
satu super admin dev). Tulis test migration (migrate:fresh berhasil) dan test factory.
Jangan membuat tabel booking dulu.
```

## 0.3 Tenant, auth, dan role [SELESAI - Commit 196aa12]

```text
Implementasikan (PRD 5-8, 205, 212):
1. Registrasi pemilik usaha: membuat user, tenant, business awal (slug unik otomatis), subscription TRIAL.
2. Login/logout, reset password, verifikasi email, rate limit login.
3. Trait BelongsToTenant + TenantScope global, middleware ResolveTenant.
4. Role: Super Admin, Owner, Member (preset Manager/Front Desk/Staff/Viewer) memakai spatie/laravel-permission mode teams (team = tenant).
5. Halaman dashboard owner kosong dan dashboard admin kosong.
Wajib test: (a) user tenant A tidak bisa membaca/mengubah data tenant B lewat ID apa pun, (b) member tanpa permission ditolak di server,
(c) query tanpa tenant scope gagal/terdeteksi pada model tenant. Buat helper test reusable untuk uji isolasi tenant per route.
```

## 0.4 Subscription & limit [SELESAI - Commit 3c7174e]

```text
Implementasikan fondasi subscription (PRD 9, 217):
- Service LimitEnforcer dengan canCreate('<entity>') berdasarkan plan.limits, dan pesan PLAN_LIMIT_REACHED ramah (PRD 218).
- Status TRIAL/ACTIVE/PAST_DUE/GRACE_PERIOD/EXPIRED/SUSPENDED/CANCELLED dan middleware EnsureSubscriptionActive
  (read-only saat GRACE_PERIOD/EXPIRED; booking publik ditutup saat SUSPENDED).
- Job harian mengubah status berdasarkan tanggal.
- Terapkan limit pada satu entitas contoh (mis. business_members) sebagai bukti.
Test: batas tercapai menolak pembuatan tapi tetap bisa membaca; perubahan status oleh job; suspended menutup halaman publik.
```

## 0.5 Audit log [SELESAI - Commit c08603d]

```text
Implementasikan audit log generik (PRD 52, 214): trait/observer yang mencatat actor, actor_role, tenant, action, entity_type, entity_id,
before, after, source, ip. Sediakan helper Audit::record(). Tampilkan halaman daftar audit sederhana untuk Owner (filter tanggal/aksi).
Catat minimal: login, logout, perubahan member/permission, perubahan subscription. Data sensitif (password, token) tidak boleh masuk before/after.
Test: setiap aksi di atas menghasilkan entri; entri tenant lain tidak terlihat.
```

## 0.6 Design system & layout [SELESAI - Commit 81f2330]

```text
Bangun design system dasar (PRD 12, 64, 65) di resources/js/Components/ui: Button, Input, Select, Textarea, Modal, Drawer, Toast,
Tooltip, Tabs, Badge Status (warna + ikon + label), Data Table, Empty State, Error State, Skeleton, Confirmation Dialog.
Token warna/spasi/tipografi sebagai CSS variable Tailwind; maksimal 2 font. Dukung mode terang dan gelap bila murah.
Layout: OwnerLayout (sidebar penuh di laptop, ringkas di tablet, bottom-nav di HP), AdminLayout, PublicLayout (tanpa sidebar).
Aksesibilitas: fokus terlihat, label form, kontras memadai, navigasi keyboard. Buat halaman /app/_styleguide untuk memeriksa semua komponen.
Cek tampilan di lebar 360, 768, 1280. Jangan memuat library UI besar.
```

## 0.7 Pipeline deploy [SELESAI - Commit 9a8d80a]

```text
Buat pipeline deploy untuk cPanel/shared hosting (docs WORK_PHASE WP-2.4 dan PRD 201.4, 209):
- skrip deploy.sh (atau workflow GitHub Actions) : build frontend di CI, rsync/zip ke server, php artisan migrate --force,
  config:cache route:cache view:cache, rilis dengan folder bertimestamp bila memungkinkan.
- panduan docs/runbook-deploy.md : persiapan hosting, document root, .env produksi, cron per menit (schedule:run), izin folder, storage:link, rollback.
- konfigurasi schedule: queue:work --stop-when-empty --max-time=50 setiap menit tanpa overlap.
- halaman /up (health) yang tidak membocorkan informasi.
Jangan memasukkan rahasia ke repo. Beri daftar variabel .env yang harus saya isi.
```

## 0.G Gate Phase 0 [SELESAI - docs/gate-phase-0.md]

```text
Verifikasi Exit Criteria Phase 0 di docs/AMAN_BOOKING_WORK_PHASE_v1.2.md. Jalankan seluruh test, lint, build.
Lalu buat laporan docs/gate-phase-0.md berisi tabel: kriteria | status (LULUS/GAGAL/BELUM) | bukti (perintah atau file test).
Kriteria: owner bisa daftar-login-lihat dashboard; test isolasi tenant lulus; deploy staging satu perintah; limit plan bekerja.
Jangan menandai LULUS tanpa bukti. Sebutkan risiko atau utang teknis yang tersisa.
```

---

# PHASE 1 — BOOKING CORE

## 1.1 Business, jadwal, dan kalender kerja [BERIKUTNYA - SIAP DIKERJAKAN]

```text
Implementasikan (PRD 6.1, 18, 146-153): CRUD profil bisnis (nama, logo, WhatsApp, alamat, timezone Asia/Jakarta|Makassar|Jayapura,
kebijakan booking/cancel/refund, minimum/maksimum waktu booking), jam operasional per hari + break, holiday, blackout date, special open/close.
Semua pengaturan lewat form Inertia dengan validasi server. Audit setiap perubahan. Logo diunggah dengan validasi MIME/ukuran dan dikompres.
Test: validasi, permission, isolasi tenant, konversi timezone.
```

## 1.2 Catalog (service)

```text
Implementasikan CRUD Service (PRD 15, 122-124, 144, 194): mode Basic (nama, harga, durasi) dan Advanced (buffer sebelum/sesudah,
kapasitas, aturan staff/resource/room, aturan payment, aturan booking). Durasi fleksibel: tetap, per quantity, per ukuran/varian
(PRD 123). Service yang sudah dipakai booking hanya bisa diarsipkan, bukan dihapus. Terapkan LimitEnforcer.
UI: formulir sederhana + panel "Advanced Settings" yang bisa dilipat. Test: arsip, limit plan, perhitungan durasi tiap model.
```

## 1.3 Resource engine

```text
Implementasikan Resource engine (PRD 16, 125-128, 160, 174-178):
- resource_types (extensible), resources, resource groups, pool, visibility PUBLIC/INTERNAL, state (AVAILABLE/BLOCKED/MAINTENANCE/INACTIVE).
- staff dengan skill, jadwal mingguan, cuti, break; room; resource_schedules; time_blocks (block time manual).
- aturan service<->resource: required/optional, mode assignment (customer pilih/auto/owner/pool), quantity.
Skema mengikuti PRD 214. Test: jadwal staff + cuti, block time, resource inactive tidak muncul, aturan skill (Therapist C tidak boleh Hot Stone).
UI owner: daftar + form + kalender jadwal per resource.
```

## 1.4a AvailabilityService — tahap dasar

```text
Bangun AvailabilityService (PRD 19, 20, 137, 204.2) secara bertahap. Tahap ini: service tunggal + satu staff.
Input: tenant, service, tanggal/rentang, quantity, preferensi staff. Output: daftar slot + alasan jika tidak tersedia (kode di PRD 218).
Algoritma: irisan interval (jam bisnis ∩ jadwal service ∩ jadwal staff) dikurangi (booking aktif + time_block + holiday + break), dipotong sesuai durasi.
Slot step dapat dikonfigurasi. Tulis unit test untuk kasus PRD 19, 197, 198 sebelum menulis implementasi (test-first).
Belum ada kapasitas atau multi-resource. Jangan membuat tabel booking final; gunakan fixture untuk allocations bila perlu.
```

## 1.4b AvailabilityService — resource, buffer, kapasitas, paralel

```text
Lanjutkan AvailabilityService: (b) tambah room/resource required & optional, (c) buffer sebelum/sesudah dihitung sebagai okupasi
(PRD 20, 124), (d) kapasitas dan quantity (PRD 134, 135), (e) multi-resource paralel (PRD 128, 129, 164) dan resource pool/auto-assign.
Tetap satu class sebagai sumber kebenaran. Tambahkan test untuk: konflik lintas service pada resource sama (PRD 165), buffer, kapasitas penuh,
parallel therapist+room, pool memilih alternatif. Ukur waktu: p95 < 500 ms pada 30 hari x 20 resource data seed; laporkan hasilnya.
```

## 1.5 BookingService, state machine, idempotency

```text
Implementasikan pembuatan booking (PRD 195, 204.1, 204.3, 210, 213, 214):
1. Tabel bookings, booking_allocations, booking_status_history, booking_counters, customers (jika belum) sesuai PRD 214.
2. Action CreateBooking: validasi tenant/subscription/service/customer/policy -> hitung durasi -> resolve staff/resource/room ->
   transaksi: lock baris resource (ORDER BY id, FOR UPDATE) -> cek ulang konflik -> insert booking + allocations + history + audit -> commit.
3. Snapshot service & harga (PRD 144). Kode BK-YYYYMMDD-NNNNN lewat booking_counters dengan lock.
4. BookingStateMachine sesuai tabel PRD 213 (kategori sistem, guard, efek samping). Tolak transisi tidak valid dengan INVALID_TRANSITION.
5. Idempotency-Key: request ulang mengembalikan respons booking sebelumnya.
Test wajib: slot bentrok ditolak, kode unik, idempotensi, setiap transisi valid/invalid, audit tercatat, rollback utuh bila langkah gagal.
Jangan membuat UI. Laporkan query lock yang dipakai.
```

## 1.6 Customer module

```text
Implementasikan modul Customer (PRD 7, 39, 40, 210 poin 14): normalisasi nomor WhatsApp ke +62, dedup per tenant,
catatan, tag, riwayat booking, counter no-show, consent pemasaran terpisah. Pencarian cepat (nama/WA/email/kode booking).
Test: normalisasi berbagai format nomor (0812..., +62812..., 62812...), dedup, isolasi tenant, tidak ada ekspor tanpa permission.
```

## 1.7 Owner UI tahap 1

```text
Bangun UI owner (PRD 41, 42, 157, 158, 67, 204.4): Quick Booking (pakai CreateBooking dan AvailabilityService yang sama),
Semua Booking (tabel + filter + detail drawer + aksi sesuai state machine), Kalender Day/Week/Agenda dengan filter staff/resource,
Kanban dasar. Responsif: kalender Agenda/Day di HP, Kanban satu kolom aktif di HP. Drag reschedule memanggil backend (validasi
availability + policy) dan rollback bila ditolak. Polling 30-60 detik, tanpa WebSocket. Tidak ada logika availability di frontend.
Test E2E Playwright: buat booking manual, ubah status, drag reschedule berhasil dan ditolak.
```

## 1.8 Uji konkurensi & timezone

```text
Buat test konkurensi nyata (PRD 204.7): jalankan 50 request paralel ke slot yang sama pada database MySQL sungguhan (bukan SQLite);
hasil harus tepat 1 booking sukses dan lainnya SLOT_TAKEN. Ulangi untuk slot berbeda pada resource sama (tanpa deadlock) dan untuk
kapasitas kelas 20 orang. Buat juga test timezone lintas Asia/Jakarta, Makassar, Jayapura (termasuk booking dekat tengah malam UTC).
Laporkan: skrip yang dipakai, hasil, waktu, dan bila ada deadlock perbaiki strategi lock. Jangan mengubah aturan bisnis.
```

## 1.G Gate Phase 1

```text
Verifikasi Exit Criteria Phase 1 di WORK_PHASE. Jalankan semua test, ukur cakupan Availability dan Booking (target >= 85%).
Tulis docs/gate-phase-1.md (kriteria | status | bukti). Tambahkan bagian "Risiko tersisa" dan "Keputusan yang perlu saya ambil".
```

---

# PHASE 2 — PENGALAMAN PUBLIK

## 2.1 Routing publik & landing page (Blade)

```text
Implementasikan halaman publik (PRD 28, 29, 167, 184, 205, 215.1):
- route /{slug}, /{slug}/booking, /{slug}/booking/success/{code}, /{slug}/booking/manage/{token}.
- landing page server-rendered Blade (tanpa React), section dari JSON (Hero, Layanan, Tentang, Galeri, FAQ, Kontak, CTA), meta SEO + Open Graph,
  tombol "Booking Sekarang" dan "Chat WhatsApp".
- 404 jika tidak dipublikasikan; halaman "tidak tersedia" bila tenant suspended.
- gambar responsif (srcset), WebP, lazy-load. JS minimal.
Target: HTML < 100 KB, JS ~0 KB, Lighthouse mobile >= 90. Laporkan skor Lighthouse yang diukur.
```

## 2.2 Booking flow (React island)

```text
Bangun alur booking customer (PRD 7.1, 30, 31, 63, 64, 215.1) sebagai island React di entry public.tsx:
layanan -> (staff/resource bila diaktifkan) -> tanggal -> jam -> data customer (nama, WhatsApp, email opsional, field kustom) -> review -> konfirmasi.
Mobile-first, tombol besar, keyboard type sesuai (tel/email), input tersimpan sementara, error di dekat field, tanpa modal bertumpuk.
Slot hanya dari endpoint availability; submit lewat POST bookings dengan Idempotency-Key (UUID dibuat saat form dibuka).
Mode Quick Booking opsional. Label zona waktu (WIB/WITA/WIT) selalu tampil. Bundle gzip < 150 KB; laporkan ukuran hasil build.
Test E2E Playwright di viewport 360px: booking sukses, slot baru diambil orang lain (pesan ramah), submit ganda tidak membuat booking ganda.
```

## 2.3 Konfirmasi, kelola booking, reschedule, cancel

```text
Implementasikan halaman sukses dan manage booking (PRD 32, 33, 34, 167, 181, 205, 210 poin 15):
token acak 32+ byte, disimpan sebagai hash, punya masa berlaku; tombol Tambah ke kalender (.ics), buka WhatsApp, Reschedule, Cancel, Booking Lagi.
Reschedule/cancel mengikuti policy bisnis (batas jam, maksimum kali, deposit) dan memakai AvailabilityService + BookingStateMachine.
Rate limit semua endpoint. Test: token salah/kedaluwarsa, batas policy, reschedule ke slot terisi ditolak, tenant lain tidak bisa dibuka.
```

## 2.4 Notifikasi dasar

```text
Implementasikan notification engine dasar (PRD 37, 61, 183, 216): template berbasis variabel, notification_logs, queue, retry maksimum 5x
lalu DEAD_LETTER. Kanal fase ini: email konfirmasi + tautan wa.me dengan teks terisi otomatis. Kegagalan notifikasi tidak boleh membatalkan booking.
Owner dapat mengedit template dan melihat status/retry manual. Test: kegagalan kirim -> retry -> dead letter terlihat; variabel tersubstitusi benar;
tidak ada duplikasi saat job diulang.
```

## 2.5 Optimasi performa publik

```text
Audit dan optimalkan halaman publik terhadap budget PRD 204.8: pecah bundle (publik vs app), kompres/resize gambar saat upload (maks 1600 px, WebP),
header cache aset, preload font utama, hapus JS tak terpakai. Ukur dengan Lighthouse mobile (throttling 4G) pada landing dan booking.
Laporkan tabel sebelum/sesudah (ukuran JS, LCP, skor). Jangan menurunkan fungsionalitas.
```

## 2.6 Onboarding wizard & template bisnis awal

```text
Implementasikan onboarding wizard 8 langkah (PRD 48, 49, 168-170, 192) dan instalasi template sebagai DRAFT yang harus di-review owner:
template awal: Barbershop, Salon Wanita, Spa & Sauna, Sports Court (badminton/futsal), Car/Motorcycle Rental, Custom Business.
Setiap template membuat service, resource, workflow default, form default, section landing, dan pengaturan notifikasi (sumber: PRD 112-191).
Template punya versi dan origin SYSTEM_TEMPLATE; tidak membawa data tenant lain. Zero-config default workflow (PRD 49).
Uji dengan 3 orang nyata: target publish < 15 menit; catat hambatan di docs/uji-onboarding.md.
```

## 2.G Gate Phase 2

```text
Verifikasi Exit Criteria Phase 2: dari HP buka link -> booking < 60 detik (ukur manual + E2E), Lighthouse mobile >= 90 pada landing dan booking,
owner baru publish < 15 menit. Tulis docs/gate-phase-2.md dengan bukti dan angka terukur. Daftar bug terbuka berdasarkan severity.
```

---

# PHASE 3 — WORKFLOW & KUSTOMISASI

## 3.1 Custom status & Kanban penuh

```text
Implementasikan status kustom (PRD 24, 25, 140-141, 213): status milik bisnis dipetakan ke kategori sistem; Kanban membaca status tersebut.
Drag di Kanban menjalankan BookingStateMachine yang sama; transisi tidak valid dikembalikan dengan pesan ramah. Warna + ikon + label
(tidak hanya warna). Kanban mobile: satu kolom aktif + segmented control. Test: pemetaan status kustom, drag valid/tidak valid, payment-required.
```

## 3.2 Form builder & conditional form

```text
Implementasikan form builder (PRD 26, 27, 179): tipe field, properti (label, placeholder, required, default, validasi, visibility condition, urutan),
penyimpanan di booking_custom_fields, render di booking flow publik dan Quick Booking owner. Aturan kondisional dievaluasi di SERVER saat validasi
(frontend hanya menampilkan/menyembunyikan). Upload file: validasi MIME+ukuran, nama acak, penyimpanan aman. Template form per jenis bisnis.
Test: kondisi tampil/sembunyi, field wajib tersembunyi tidak divalidasi, upload berbahaya ditolak.
```

## 3.3 Workflow builder (UI)

```text
Bangun workflow builder dengan XYFlow (PRD 21, 22, 66, 204.5): canvas drag-drop, node library (Trigger, Condition, Action, Delay),
panel properti, undo/redo, zoom, fit, duplicate, disable node, autosave draft, tombol Test dan Publish. Graf disimpan JSON di workflow_versions;
publish membuat versi immutable (PRD 23). Validasi graf di server: ada trigger, tidak ada node yatim, tidak ada siklus tak terbatas.
Dimuat lazy hanya di halaman workflow (bundle terpisah). Node MVP: Booking Created, Status Changed, Payment Status, Status, Service,
Change Status, Assign Resource, Send Notification, Delay. Mobile: tampilan read-only/sederhana.
Test: simpan draft, publish versi, edit setelah publish membuat draft baru, graf tidak valid ditolak.
```

## 3.4 Workflow runner

```text
Implementasikan eksekutor workflow (PRD 62, 204.5): domain event (BookingCreated, StatusChanged, ...) -> cari workflow aktif -> buat workflow_run
-> eksekusi node via queue. Proteksi: max_depth, execution_id unik, aksi idempotent, retry counter, status FAILED yang bisa di-retry manual,
log eksekusi per node. Delay memakai run_at yang diproses scheduler per menit. Booking terikat ke versi workflow saat dibuat.
Perubahan status oleh workflow tetap lewat BookingStateMachine. Kegagalan aksi tidak menghilangkan booking.
Test wajib: loop tidak berakhir terdeteksi dan dihentikan, aksi tidak terkirim dua kali saat job diulang, workflow gagal bisa di-retry.
```

## 3.5 Template workflow & form + versioning template

```text
Tambahkan template workflow dan form per jenis bisnis (PRD 47, 113-119) dan sistem versi template (PRD 186): update template global tidak
menimpa konfigurasi tenant; tampilkan "update tersedia" + pratinjau migrasi. Super Admin dapat membuat/menyunting template dan melihat preview
(PRD 185). Test: tenant lama tidak berubah saat template baru dirilis.
```

## 3.6 Landing page builder sederhana [SELESAI]

```text
Implementasikan builder landing page (PRD 28, 29, 68, 69): atur urutan section (drag), edit teks, warna, logo, font preset (maks 2), gambar,
preview desktop/tablet/mobile, publish/unpublish. Data section disimpan sebagai JSON dan dirender Blade server-side di halaman publik.
Bukan website builder penuh. Tidak ada eksekusi HTML/JS bebas dari pengguna (sanitasi ketat).
Test: urutan section tersimpan, XSS pada teks ditolak/di-escape, unpublish menutup halaman.
```

## 3.G Gate Phase 3 [SELESAI - docs/gate-phase-3.md]

```text
Verifikasi Exit Criteria Phase 3: owner membuat workflow Salon tanpa developer (buat skenario E2E yang melakukannya), workflow gagal
tidak menghilangkan booking dan bisa di-retry, test loop/duplikasi lulus. Tulis docs/gate-phase-3.md dengan bukti.
Sebutkan apakah pilot terbatas sudah layak dibuka (opsi rilis cepat WORK_PHASE WP-3) dan alasannya.
```

---

# PHASE 4 — OPERASIONAL BISNIS

## 4.1 Payment gateway [SELESAI]

```text
Integrasikan payment gateway [MIDTRANS | XENDIT] (PRD 45, 60, 204.3, 210): model no payment/deposit/full/partial, invoice, pembayaran,
status UNPAID/PENDING/PARTIAL/PAID/FAILED/REFUNDED/PARTIAL_REFUND, QRIS wajib. Webhook /webhooks/payment/{provider}: verifikasi signature,
simpan provider_event_id unik, idempotent (event ganda -> 200 tanpa efek ganda). Kunci sandbox dari .env; jangan pernah menaruh kunci di repo.
Pembayaran valid memicu transisi PENDING->CONFIRMED lewat state machine. Refund dicatat manual dan butuh persetujuan Owner (PRD 212).
Test: replay webhook, signature salah, urutan event terbalik, pembayaran sebagian. Beri daftar langkah yang harus saya lakukan di dashboard provider.
```

## 4.2 Reservation hold [SELESAI]

```text
Implementasikan temporary hold (PRD 139, 210 poin 4): booking PENDING dengan hold_expires_at (default 10 menit, dapat dikonfigurasi),
job per menit melepas hold kedaluwarsa menjadi EXPIRED dan membebaskan alokasi, countdown di UI customer, pesan HOLD_EXPIRED ramah.
Hold ikut dalam perhitungan availability selama valid. Test: hold menahan slot, kedaluwarsa melepas slot, bayar tepat sebelum/ sesudah batas,
race antara pembayaran dan kedaluwarsa tidak membuat data tidak konsisten.
```

## 4.3 Check-in

```text
Implementasikan check-in (PRD 36, 213): manual, via kode booking, dan QR. Jendela check-in dapat dikonfigurasi. Memicu workflow dan transisi
CONFIRMED->CHECKED_IN. Tombol cepat di kalender, Kanban, dan mobile. Test: di luar jendela ditolak, dua kali check-in tidak ganda, permission staff terbatas.
```

## 4.4 Inventory opsional

```text
Implementasikan inventory opsional (PRD 17, 112-121): item, mutasi (OPENING/PURCHASE/ADJUSTMENT/CONSUMED_BY_BOOKING/SALE/RETURN/WASTE),
mode stok per bisnis/service (reserve saat booking / deduct saat service / release saat batal / deduct final saat selesai). Stok tidak boleh minus
kecuali diizinkan. Menu hanya tampil jika modul aktif. Test: setiap mode, pembatalan melepas stok, concurrency dua booking pada stok terakhir.
```

## 4.5 Fitur lanjutan engine

```text
Implementasikan kemampuan engine lanjutan di AvailabilityService/BookingService (PRD 130-133, 135, 155, 123):
multi-service dalam satu booking, layanan berurutan (sequential) dengan resource berbeda per tahap, paket/komposit, grup/peserta,
durasi per varian/quantity, dependency service (Consultation -> Main Service). Tetap satu algoritma availability, tetap transaksi + lock.
Test dengan contoh PRD 130, 131, 164 (couple spa), 199. Ukur ulang performa availability dan laporkan.
```

## 4.6 Reporting MVP

```text
Implementasikan laporan (PRD 44, 162, 163): jumlah booking, completed/cancelled/no-show/pending, revenue, customer baru/repeat,
utilization resource (rumus PRD 162). Filter rentang tanggal, layanan, staff. Query diindeks; bila lambat gunakan tabel agregat harian
yang diisi scheduler. Ekspor CSV dengan permission. Test: angka cocok dengan data seed yang dihitung manual; tenant lain tidak ikut terhitung.
```

## 4.7 CRM ringan

```text
Perluas profil customer (PRD 39, 40): riwayat booking/pembayaran/pembatalan/no-show, tag dan segmen sederhana, catatan internal,
consent pemasaran terpisah (tidak otomatis dari booking), ekspor dan permintaan hapus data sesuai PRD 54. Test: pesan pemasaran tidak terkirim
tanpa consent; hapus data menganonimkan tanpa merusak histori transaksi.
```

## 4.8 Super Admin

```text
Implementasikan area Super Admin (PRD 73, 74, 75): dashboard platform, tabel tenant (plan, status, renewal, usage, last activity),
detail tenant (tab Overview/Owner/Business/Subscription/Usage/Billing/Activity/Audit/Support Notes), aksi suspend/activate/change plan/extend trial,
manajemen plan dan fitur, manajemen template. Support access ke tenant harus aman: butuh alasan, dibatasi waktu, dan tercatat di audit
yang juga terlihat oleh Owner. Test: suspend menutup booking publik dan membatasi owner; perubahan plan tidak merusak data; support access berlog.
```

## 4.G Gate Phase 4

```text
Verifikasi Exit Criteria Phase 4: replay webhook tidak menghasilkan data ganda, hold kedaluwarsa melepas slot, suspend tenant menutup halaman publik.
Tambahkan uji regresi penuh (semua test). Tulis docs/gate-phase-4.md dengan bukti. Daftar utang teknis dan risiko keamanan baru.
```

---

# PHASE 5 — OTOMASI & INTEGRASI

## 5.1 WhatsApp & AMAN CHAT

```text
Integrasikan WhatsApp [PROVIDER / AMAN CHAT] (PRD 38, 70, 72, 216): kanal WhatsApp pada notification engine, template pesan dengan variabel,
status pengiriman via webhook, retry terbatas + dead letter, fallback ke email. Kegagalan tidak memengaruhi booking.
Sinkronisasi customer ke AMAN CHAT hanya untuk yang diizinkan. Jelaskan lebih dulu batasan API provider dan apa yang harus saya siapkan
(akun, nomor, template yang perlu disetujui). Test dengan provider palsu (fake) dan simulasi kegagalan.
```

## 5.2 Reminder pintar

```text
Implementasikan reminder terjadwal per template bisnis (PRD 182, 216): H-1, H-2 jam, pickup/check-in reminder, follow-up pasca layanan,
payment reminder. Dapat diubah owner. Idempotent: reminder tidak terkirim dua kali walau scheduler berjalan ulang; reschedule/cancel membatalkan
reminder lama dan membuat yang baru. Test: perubahan jadwal, pembatalan, scheduler dobel jalan.
```

## 5.3 Integrasi AMAN KASIR

```text
Implementasikan integrasi AMAN KASIR (PRD 71): event BookingCompleted -> kirim transaksi via webhook/API bertanda tangan (HMAC),
idempotent dengan kunci booking, retry terbatas, log, dan layar status sinkronisasi. Jangan mengasumsikan struktur API AMAN KASIR; tanyakan
spesifikasi endpoint dan format payload kepada saya bila belum ada, lalu buat adapter terpisah di Domain/Integration agar mudah diganti.
Test dengan server tiruan: sukses, gagal, duplikat.
```

## 5.4 Sinkronisasi kalender

```text
Tambahkan feed .ics per staff/bisnis (PRD 70) dengan token rahasia yang bisa diganti, dan tombol "Tambah ke kalender" yang konsisten.
Google Calendar hanya bila saya minta setelah ini. Test: feed hanya memuat booking staff terkait dan tenant sendiri; token lama tidak berlaku setelah diganti.
```

## 5.5 Waitlist & overbooking

```text
Implementasikan waitlist (PRD 35, 136, 154, 210 poin 9): saat slot penuh, customer bergabung ke daftar tunggu; saat slot tersedia, notifikasi
ke antrean berikutnya dengan batas waktu konfirmasi; tidak otomatis menjadi booking CONFIRMED. Overbooking opsional dengan batas maksimum
(default OFF). Test: urutan antrean, kedaluwarsa tawaran berpindah ke berikutnya, tidak ada oversell di luar batas.
```

## 5.6 Template bisnis tambahan

```text
Tambahkan template bisnis dari katalog PRD 191 secara bertahap, batch 1: [PILIH 6-8 template, mis. Nail, Massage, Pet Grooming, Bengkel Mobil,
Car Wash, Physiotherapy, Photography Studio, Meeting Room]. Tiap template: service, resource, durasi, buffer, form, workflow, reminder, landing section
(PRD 112-186). Tidak menyimpan data klinis sensitif. Test: instalasi menghasilkan konfigurasi valid dan bisa dipublikasikan tanpa error.
```

## 5.G Gate Phase 5

```text
Verifikasi Exit Criteria Phase 5: kegagalan WhatsApp tidak mempengaruhi booking dan dead letter terlihat di UI; satu alur
Booking -> Completed -> transaksi AMAN KASIR terbukti end-to-end. Jalankan regresi penuh. Tulis docs/gate-phase-5.md.
```

---

# FINALISASI & PELUNCURAN

## F.1 Audit keamanan menyeluruh

```text
Lakukan audit keamanan internal menyeluruh (PRD 53, 90, 205; WORK_PHASE WP-2.1): checklist OWASP Top 10, tenant isolation pada SETIAP route
(buat daftar route otomatis + test per route), IDOR, mass assignment, upload file, rate limit, CSRF/CORS, header keamanan (CSP, HSTS,
X-Frame-Options, Referrer-Policy), penyimpanan rahasia, replay webhook, token manage booking, session. Hasilkan docs/security-audit.md
berisi temuan (severity, lokasi, perbaikan) dan perbaiki yang severity tinggi/kritis setelah saya setujui. Sertakan test regresi tiap perbaikan.
```

## F.2 Dependency audit & kunci versi

```text
Jalankan composer audit dan npm audit, perbarui dependensi yang rentan secara aman, kunci versi (lockfile), dan jalankan seluruh test.
Laporkan paket yang tidak bisa diperbarui beserta alasan dan mitigasinya. Jangan melakukan upgrade mayor tanpa persetujuan.
```

## F.3 Uji beban

```text
Buat skenario uji beban (k6 atau Artillery) untuk: 50-100 booking bersamaan di slot berbeda dan di slot sama, lonjakan endpoint availability,
dan antrean notifikasi. Jalankan terhadap staging yang spesifikasinya mirip produksi. Laporkan p50/p95/p99, error rate, deadlock,
penggunaan CPU/memori bila tersedia. Bandingkan dengan budget PRD 204.8 dan trigger migrasi PRD 208. Sarankan perbaikan (indeks, cache, batas).
```

## F.4 Backup & restore drill

```text
Implementasikan backup database dan file harian terjadwal (retensi sesuai kebijakan) dan skrip restore. Lakukan restore nyata ke staging dan
catat: durasi, langkah, dan hasil verifikasi data (jumlah baris, sampel booking). Tulis docs/runbook-backup-restore.md.
Backup dianggap belum valid sebelum restore berhasil diuji (PRD 88).
```

## F.5 Observability

```text
Tambahkan log terstruktur dengan correlation ID pada request kritis, integrasi error monitoring [SENTRY | FLARE], dan halaman internal
status antrean (job gagal, notifikasi dead letter, workflow FAILED) untuk Owner/Super Admin sesuai permission (PRD 87).
Pastikan tidak ada data pribadi/rahasia di log. Tulis docs/runbook-insiden.md (cara diagnosis booking hilang, antrean macet, webhook gagal).
```

## F.6 Aksesibilitas & uji perangkat

```text
Audit aksesibilitas (PRD 65): navigasi keyboard di halaman owner, label form, urutan fokus, kontras, status tidak hanya warna, pesan error terbaca screen reader.
Jalankan axe pada halaman utama (publik dan owner) dan perbaiki pelanggaran serius. Buat checklist uji perangkat nyata (3 HP Android, 1 iPhone,
1 tablet, 2 browser desktop) di docs/uji-perangkat.md dengan kolom hasil untuk saya isi.
```

## F.7 Persiapan UAT pilot

```text
Siapkan UAT dengan 3-5 bisnis pilot: skrip skenario per jenis bisnis (barber, salon/spa, lapangan, rental), formulir umpan balik singkat,
data demo yang bisa direset, dan dashboard internal metrik pilot (booking, error, waktu onboarding). Tulis panduan onboarding pilot (1 halaman)
dan daftar batasan yang diketahui. Jangan menyalin data tenant nyata.
```

## F.8 Draf dokumen legal & bantuan

```text
Buat DRAF (bukan final) Syarat & Ketentuan, Kebijakan Privasi, dan perjanjian pemrosesan data untuk tenant, mengacu pada prinsip UU PDP Indonesia
dan fitur produk (consent pemasaran terpisah, ekspor/hapus data, retensi). Tandai dengan jelas bagian yang perlu ditinjau konsultan hukum.
Buat juga halaman bantuan/FAQ onboarding dan template jawaban dukungan. Jangan mengklaim kepatuhan hukum.
```

## F.9 Deploy produksi

```text
Siapkan dan jalankan checklist deploy produksi di docs/AMAN_BOOKING_WORK_PHASE_v1.2.md WP-2.4. Hasilkan docs/deploy-produksi-checklist.md yang diisi
sesuai kondisi hosting saya: [VERSI PHP, ADA SSH?, DOCUMENT ROOT BISA DIUBAH?]. Sebelum menjalankan perintah apa pun di server, tampilkan daftar langkah
dan tunggu persetujuan. Setelah deploy: smoke test (daftar -> buat bisnis -> booking -> pembayaran sandbox -> notifikasi) dan laporkan hasilnya.
Sertakan rencana rollback dan backup sebelum migrasi.
```

## F.10 Checklist peluncuran & kriteria selesai

```text
Verifikasi seluruh kriteria di WORK_PHASE WP-6 dan PRD 104, 211 satu per satu. Buat docs/launch-readiness.md: kriteria | status | bukti | pemilik tindakan.
Tandai GAGAL/BELUM secara jujur. Akhiri dengan rekomendasi GO / NO-GO beserta alasan dan daftar blocker. Jangan menyatakan siap rilis bila ada bug severity tinggi terbuka.
```

## F.G Gate akhir

```text
Jalankan seluruh suite test (unit, integrasi, E2E) dan tampilkan ringkasan. Bandingkan dengan Definition of Done PRD 107 untuk setiap modul utama.
Hasilkan satu halaman ringkasan eksekutif: apa yang sudah jadi, metrik terukur, risiko tersisa, dan 5 prioritas pasca-rilis 30 hari pertama.
```

---

# PHASE 6 — LANJUTAN (SETELAH RILIS)

## 6.1 REST API & API key

```text
Rancang dan implementasikan REST API v1 (PRD 59, 207, 215): API key per tenant (disimpan hash, bisa dicabut, scope terbatas), endpoint booking,
availability, customer, service, rate limit per key, versioning /api/v1, dokumentasi OpenAPI. Hanya paket BUSINESS. Tulis rencana dan daftar endpoint
dulu. Test: scope, isolasi tenant, rate limit, idempotency.
```

## 6.2 Webhook keluar

```text
Implementasikan webhook keluar per tenant (PRD 59, 22): event pilihan, tanda tangan HMAC, retry dengan backoff + dead letter, log pengiriman, tombol uji.
Test: tanda tangan benar, retry, tidak ada duplikasi tak terkendali, endpoint tujuan internal/localhost ditolak (cegah SSRF).
```

## 6.3 Custom domain & subdomain

```text
Rencanakan dan implementasikan custom domain (PRD 30, 167, 208): verifikasi kepemilikan DNS, pemetaan domain -> tenant, SSL otomatis sesuai kemampuan
hosting/VPS. Jelaskan dulu apakah hosting saat ini mampu; bila tidak, rekomendasikan migrasi sesuai trigger PRD 208 dan buat rencana migrasinya.
```

## 6.4 Multi-outlet

```text
Implementasikan multi-outlet (PRD 9.2, 217): outlet di bawah satu business/tenant, resource dan jadwal per outlet, laporan per outlet,
pemilihan outlet di booking publik. Pastikan migrasi data tenant lama aman (outlet default). Test: isolasi antar outlet pada permission member.
```

## 6.5 Migrasi ke VPS/Redis

```text
Buat rencana dan skrip migrasi dari shared hosting ke VPS (PRD 208): provisioning, Nginx/PHP-FPM, Supervisor untuk queue worker, Redis untuk
cache/queue, backup, SSL, zero-downtime deploy, dan prosedur cutover + rollback. Ubah konfigurasi lewat .env saja (tanpa mengubah kode bisnis).
Tampilkan rencana dan estimasi downtime, tunggu persetujuan sebelum mengeksekusi.
```

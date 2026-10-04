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

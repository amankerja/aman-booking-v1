# PRD — AMAN BOOKING
## Visual Booking & Business Workflow Platform

**Status:** PRD v1.2 — Baseline Produk + Arsitektur + Spesifikasi Pelengkap  
**Produk:** AMAN BOOKING  
**Kategori:** Booking / Reservasi / Scheduling / Workflow / Customer Management  
**Target Pasar:** UMKM, usaha jasa, rental, hospitality, beauty, healthcare, sports, education, studio, konsultasi, event, dan bisnis berbasis jadwal/resource.  
**Bahasa utama:** Bahasa Indonesia  
**Model sistem:** Multi-tenant SaaS  
**Role besar:** Super Admin AMAN BOOKING, Pemilik Usaha, Customer

---

## Tentang Dokumen Ini

Dokumen ini adalah **PRD v1.2**, hasil penggabungan PRD v1.1 (bagian 1–200) dengan keputusan arsitektur dan spesifikasi pelengkap. Rencana kerja bertahap (Phase 0 sampai peluncuran) ada di dokumen terpisah: `AMAN_BOOKING_WORK_PHASE_v1.2.md`.

| Bagian | Nomor | Isi |
|---|---|---|
| **I. Produk & Kebutuhan** | 1–200 | Visi, role, booking engine, resource, workflow, form, landing page, template bisnis, requirement |
| **II. Arsitektur & Keputusan Teknis** | 201–209 | Stack, struktur kode, desain teknis kritis, keamanan, konvensi, migrasi hosting |
| **III. Spesifikasi Pelengkap** | 210–219 | Errata, non-goals, permission, mesin status, skema, endpoint, notifikasi, plan, error, versi |

Jika ada perbedaan antarbagian, rujuk **bagian 210 (Errata & Klarifikasi)**.

## Peta Isi Bagian I

| Nomor | Topik |
|---|---|
| 1–4 | Ringkasan eksekutif, visi, prinsip, istilah |
| 5–9 | Role, otorisasi, multi-tenant, subscription |
| 10–13 | Information architecture, UI/UX, design system, dashboard |
| 14–20 | Booking engine, service, resource, inventory, scheduling, availability, buffer |
| 21–25 | Workflow builder, node, versioning, Kanban, custom status |
| 26–31 | Form builder, landing page, booking page, quick booking |
| 32–45 | Konfirmasi, reschedule, cancel, waitlist, check-in, notifikasi, CRM, kalender, mobile, laporan, payment |
| 46–52 | Model bisnis, workflow contoh, onboarding, aturan default, edge case, audit |
| 53–60 | Keamanan, retensi, analitik, performa, reliabilitas, model data, API, idempotency |
| 61–77 | Retry, keamanan workflow, UX detail, aksesibilitas, template, integrasi, Super Admin, KPI |
| 78–111 | MVP, acceptance criteria, arsitektur logis, rule engine, contoh, positioning, DoD, backlog |
| 112–200 | Template library, service configuration, durasi, resource, kapasitas, REQ |

---

# BAGIAN I — PRODUK & KEBUTUHAN (1–200)

---

# 1. RINGKASAN EKSEKUTIF

AMAN BOOKING adalah platform booking dan reservasi yang dirancang agar dapat digunakan oleh berbagai jenis bisnis tanpa memaksa bisnis mengikuti alur sistem yang kaku.

Prinsip utama:

> **Satu booking engine untuk banyak jenis bisnis, dengan workflow yang dapat dikonfigurasi sendiri oleh pemilik usaha.**

Setiap pemilik usaha memiliki tenant bisnis sendiri dan dapat:

- membuat profil bisnis;
- mengelola layanan, produk, resource, ruangan, terapis, kamar, kendaraan, lapangan, staff, dan ketersediaan;
- membuat jadwal operasional;
- menentukan aturan booking;
- membuat workflow booking dengan Kanban dan visual drag-and-drop;
- membuat form booking sendiri;
- membuat halaman landing page sederhana;
- mengarahkan customer dari landing page ke halaman booking;
- melihat kalender dan seluruh booking;
- mengubah jadwal;
- mengelola kapasitas dan resource;
- mengelola customer;
- mengatur notifikasi dan follow-up;
- mengatur paket/langganan melalui sistem subscription;
- melihat laporan operasional.

Customer tidak wajib membuat akun penuh. Tujuan utama customer:

> **Buka link → pilih layanan → pilih jadwal → isi data minimal → konfirmasi → selesai.**

Data minimal default:

- Nama
- Nomor WhatsApp
- Email (opsional tetapi direkomendasikan)

Field tambahan hanya ditampilkan apabila bisnis memerlukannya.

---

# 2. VISI PRODUK

## 2.1 Visi

Menjadi platform booking yang dapat menyesuaikan proses bisnis apa pun melalui konfigurasi visual tanpa perlu coding.

## 2.2 Misi

1. Memudahkan bisnis menerima dan mengelola booking.
2. Memudahkan customer melakukan booking secepat mungkin.
3. Mengurangi double booking, jadwal bentrok, lupa follow-up, dan data operasional yang tersebar.
4. Membuat workflow bisnis dapat disusun sendiri oleh pemilik usaha.
5. Menghubungkan booking dengan customer, resource, pembayaran, inventory, notifikasi, dan laporan.
6. Menjadikan satu engine dapat digunakan untuk banyak model bisnis.

---

# 3. PRINSIP PRODUK

## 3.1 Simple by default

Fitur advanced ada, tetapi tidak mengganggu pengguna pemula.

## 3.2 Configure instead of code

Pemilik usaha mengatur perilaku sistem melalui:

- drag-and-drop;
- form builder;
- workflow builder;
- status;
- rule;
- condition;
- action;
- schedule;
- resource.

Tidak diperlukan coding untuk workflow standar.

## 3.3 Customer first

Customer harus dapat booking dengan jumlah langkah minimum.

## 3.4 Resource aware

Sistem harus mampu mengetahui siapa/apa yang dipakai oleh sebuah booking.

Contoh resource:

- terapis;
- barber;
- dokter;
- mekanik;
- lapangan;
- kamar;
- kendaraan;
- studio;
- ruang;
- alat;
- meja;
- kursi;
- kapasitas;
- inventory item.

## 3.5 No accidental double booking

Sistem wajib memvalidasi konflik resource, staff, room, capacity, dan waktu sebelum booking dikonfirmasi.

## 3.6 Multi-tenant isolation

Data setiap pemilik usaha wajib terisolasi.

## 3.7 Auditability

Perubahan penting harus dapat dilacak.

---

# 4. DEFINISI ISTILAH

## 4.1 Super Admin
Pengelola platform AMAN BOOKING. Mengelola tenant/pemilik usaha, subscription, paket, status sistem, dan administrasi platform.

## 4.2 Pemilik Usaha
Customer utama platform. Memiliki satu atau lebih bisnis/outlet sesuai paket yang dimiliki.

## 4.3 Customer
Pelanggan dari pemilik usaha yang melakukan booking.

## 4.4 Tenant
Ruang data terisolasi milik satu akun pemilik usaha.

## 4.5 Business
Profil usaha yang ditampilkan kepada customer.

## 4.6 Service
Layanan yang dapat dibooking, misalnya Haircut, Facial, Konsultasi, Futsal 1 Jam, Foto Studio.

## 4.7 Product
Barang yang dapat dijual atau digunakan/dikonsumsi dalam proses bisnis.

## 4.8 Resource
Sumber daya yang dibutuhkan untuk menjalankan booking.

## 4.9 Staff
Orang yang dapat ditugaskan ke booking.

## 4.10 Room/Area
Ruangan atau tempat fisik.

## 4.11 Inventory
Stok barang yang jumlahnya dapat berubah.

## 4.12 Capacity
Jumlah maksimum customer/resource yang dapat dilayani dalam satu slot.

## 4.13 Booking
Data reservasi customer terhadap service/resource/time tertentu.

## 4.14 Workflow
Urutan proses bisnis sebuah booking dari masuk hingga selesai/cancel.

## 4.15 Status
Tahap sebuah booking, misalnya New, Confirmed, Checked In, In Service, Completed.

## 4.16 Template
Workflow, form, landing page, atau konfigurasi yang dapat digunakan ulang.

## 4.17 Availability
Ketersediaan berdasarkan waktu operasional, jadwal, resource, kapasitas, blackout date, dan booking aktif.

## 4.18 Slot
Satu pilihan waktu yang dapat dipilih customer.

---

# 5. ROLE & AUTHORIZATION

Terdapat **3 role besar**.

## 5.1 ROLE 1 — SUPER ADMIN AMAN BOOKING

Super Admin mengelola platform secara global.

### Hak akses utama

- Dashboard platform
- Data seluruh pemilik usaha
- Data tenant
- Data bisnis
- Paket subscription
- Langganan aktif/expired
- Limit pemakaian
- Status pembayaran subscription
- Trial
- Suspend/activate tenant
- Audit platform
- Template platform
- Konfigurasi global
- Notification system
- System health
- Billing administration
- Support/admin notes
- Global feature flags

### Super Admin TIDAK otomatis boleh mengubah data transaksi tenant tanpa audit.

Setiap tindakan administratif yang menyentuh data tenant wajib tercatat.

---

# 6. ROLE 2 — PEMILIK USAHA

Pemilik usaha adalah customer utama AMAN BOOKING.

Pemilik usaha mengelola:

- profil bisnis;
- outlet;
- layanan;
- produk;
- resource;
- staff;
- ruang;
- kamar;
- kendaraan;
- jadwal;
- availability;
- booking;
- workflow;
- form;
- landing page;
- customer;
- pembayaran;
- notifikasi;
- inventory;
- laporan.

## 6.1 Business Settings

Field minimum:

- Nama bisnis
- Logo
- Nomor WhatsApp
- Email
- Alamat
- Kota
- Provinsi
- Jam operasional
- Zona waktu
- Deskripsi bisnis
- Link booking
- Social links
- Kebijakan booking
- Kebijakan pembatalan
- Kebijakan refund
- Minimum waktu booking
- Maksimum waktu booking
- Booking horizon

## 6.2 Business Member

Walaupun role besar hanya 3, owner dapat membuat anggota/staff internal dalam tenant.

Anggota bisnis bukan role platform baru.

Mereka adalah:

> **member dari role Pemilik Usaha dengan permission terbatas.**

Contoh permission:

- melihat jadwal;
- mengelola booking;
- mengelola customer;
- mengelola layanan;
- mengelola resource;
- melihat laporan;
- mengubah workflow.

Permission harus configurable.

---

# 7. ROLE 3 — CUSTOMER

Customer tidak diwajibkan membuat akun penuh.

## 7.1 Default booking flow

```text
Landing/Booking Page
        ↓
Pilih layanan
        ↓
Pilih tanggal
        ↓
Pilih jam
        ↓
Isi data minimum
        ↓
Konfirmasi
        ↓
Booking dibuat
```

## 7.2 Minimum customer data

Wajib:

- Nama
- WhatsApp

Opsional:

- Email

Tambahan sesuai konfigurasi bisnis:

- alamat;
- tanggal lahir;
- identitas;
- nomor kendaraan;
- jumlah orang;
- catatan;
- file;
- pilihan staff;
- pilihan room;
- custom fields.

## 7.3 Customer identity

Identitas customer dapat menggunakan:

- WhatsApp;
- email;
- customer code internal.

No-password booking diperbolehkan.

Verifikasi dapat menggunakan OTP jika bisnis mengaktifkannya.

## 7.4 Customer account

Akun penuh bersifat opsional untuk fase awal.

Customer tetap dapat melihat/kelola booking dengan:

- link booking confirmation;
- kode booking;
- nomor WhatsApp/email yang diverifikasi.

---

# 8. MULTI-TENANT & STRUKTUR DATA

Struktur:

```text
AMAN BOOKING
│
├── Super Admin
│
├── Tenant / Pemilik Usaha A
│   ├── Business
│   ├── Outlet
│   ├── Members
│   ├── Services
│   ├── Products
│   ├── Resources
│   ├── Staff
│   ├── Rooms
│   ├── Inventory
│   ├── Workflows
│   ├── Bookings
│   ├── Customers
│   └── Reports
│
├── Tenant / Pemilik Usaha B
│   └── ...
│
└── Tenant / Pemilik Usaha C
    └── ...
```

## 8.1 Tenant isolation

Setiap entitas tenant harus mempunyai `tenant_id` atau mekanisme isolasi setara.

Tidak boleh ada query bisnis yang dapat mengambil data tenant lain hanya karena ID objek diketahui.

## 8.2 Public booking access

Public booking page menggunakan identifier aman:

- public business slug;
- booking page ID;
- signed/public token jika diperlukan.

Tidak menggunakan ID database mentah sebagai satu-satunya kontrol akses.

---

# 9. SUBSCRIPTION & BILLING

Pemilik usaha adalah customer berlangganan platform.

## 9.1 Entitas subscription

- Subscription
- Plan
- Subscription Item
- Invoice
- Payment
- Usage
- Trial
- Renewal
- Suspension
- Cancellation

## 9.2 Contoh limit paket

### BASIC

- 1 business
- 1 outlet
- 3 staff
- 5 services
- 2 resources
- booking page
- basic workflow

### PRO

- 1 business
- multi-resource
- multi-staff
- advanced workflow
- automation
- CRM
- custom form
- WhatsApp

### BUSINESS

- multi-outlet
- advanced resource
- advanced reports
- custom domain
- API
- webhooks
- automation advanced

Angka limit di atas adalah contoh dan harus dapat dikonfigurasi Super Admin.

## 9.3 Enforcement

Ketika limit tercapai:

- data lama tetap aman;
- data existing tetap dapat dibaca;
- user tidak boleh membuat entity baru yang melewati limit;
- UI menjelaskan limit;
- tersedia CTA upgrade.

## 9.4 Expired subscription

Status:

```text
TRIAL
ACTIVE
PAST_DUE
GRACE_PERIOD
EXPIRED
SUSPENDED
CANCELLED
```

Aturan expired harus jelas.

Default rekomendasi:

- customer public booking dapat diarahkan ke halaman unavailable jika tenant suspended;
- owner dapat melihat data dalam read-only selama periode grace;
- data tidak langsung dihapus.

---

# 10. INFORMATION ARCHITECTURE — PEMILIK USAHA

Sidebar utama:

```text
Dashboard

Booking
├── Kalender
├── Kanban
├── Semua Booking
└── Availability

Bisnis
├── Profil Bisnis
├── Layanan
├── Produk
├── Resource
├── Staff
├── Ruangan / Area
├── Kamar / Unit
└── Jadwal Operasional

Workflow
├── Workflow Booking
├── Automation
├── Form Booking
└── Template

Customer
├── Semua Customer
├── Segmen / Tag
└── Riwayat

Inventory
├── Stok
├── Mutasi
└── Pengaturan

Website
├── Landing Page
├── Booking Page
└── Domain

Pembayaran
├── Payment
├── Invoice
└── Pengaturan

Laporan
├── Booking
├── Revenue
├── Customer
├── Resource
└── Utilization

Pengaturan
├── Tim & Permission
├── WhatsApp
├── Email
├── Integrasi
├── Subscription
└── Audit Log
```

Menu harus dapat disesuaikan berdasarkan subscription/permission.

---

# 11. UI/UX PRINCIPLES

## 11.1 Desktop owner

Target utama:

- desktop;
- laptop;
- tablet.

Layout:

```text
┌──────────────────────────────────────────────────────────┐
│ Logo │ Search │ Notification │ Help │ Business │ Avatar │
├───────────────┬──────────────────────────────────────────┤
│ Sidebar       │ Main Content                             │
│               │                                          │
│ Dashboard     │                                          │
│ Booking       │                                          │
│ Bisnis        │                                          │
│ Workflow      │                                          │
│ Customer      │                                          │
│ Inventory     │                                          │
│ Website       │                                          │
│ Reports       │                                          │
└───────────────┴──────────────────────────────────────────┘
```

## 11.2 Customer mobile-first

Customer booking harus dirancang mobile-first.

Karakter:

- cepat;
- minim field;
- tombol besar;
- kalender mudah dipahami;
- loading jelas;
- tidak mengharuskan login;
- responsive;
- accessible.

---

# 12. DESIGN SYSTEM

## 12.1 Component

Wajib ada:

- Button
- Input
- Select
- Dropdown
- Date picker
- Time picker
- Calendar
- Booking Card
- Status Badge
- Kanban Column
- Modal
- Drawer
- Toast
- Tooltip
- Stepper
- Tabs
- Data Table
- Empty State
- Error State
- Skeleton Loader
- Confirmation Dialog

## 12.2 Status colors

Status tidak boleh hanya dibedakan dengan warna.

Status harus memiliki:

- warna;
- label;
- icon/indicator.

Harus tetap terbaca untuk pengguna dengan color-vision deficiency.

---

# 13. OWNER DASHBOARD

Dashboard harus langsung menjawab:

> “Apa yang terjadi di bisnis saya hari ini?”

Card:

- Booking hari ini
- Booking mendatang
- Pending
- Need Action
- Completed
- Cancellation
- No Show
- Revenue
- Customer baru
- Utilization resource

Quick action:

- Buat booking
- Tambah layanan
- Tambah resource
- Atur jadwal
- Edit workflow
- Buka booking page

---

# 14. BOOKING ENGINE

## 14.1 Booking data minimum

```text
Booking
├── ID
├── Public Booking Code
├── Tenant
├── Customer
├── Service
├── Resource
├── Staff
├── Room/Area
├── Start DateTime
├── End DateTime
├── Duration
├── Quantity
├── Price
├── Discount
├── Total
├── Deposit
├── Payment Status
├── Booking Status
├── Notes
├── Custom Fields
├── Source
├── Created At
├── Updated At
└── Audit Metadata
```

> Catatan v1.2: Resource/Staff/Room di atas adalah ringkasan tampilan. Penyimpanan aktual berupa banyak alokasi per booking (bagian 143, 145, 214).

## 14.2 Booking source

Contoh:

- Public Website
- WhatsApp
- Admin Manual
- Staff
- API
- QR Code
- Imported

## 14.3 Booking ID

Customer-facing booking code harus mudah dibaca.

Contoh:

`BK-20261004-00125`

Internal UUID tetap digunakan di database.

---

# 15. SERVICE / LAYANAN

Service dapat memiliki:

- nama;
- deskripsi;
- foto;
- durasi;
- harga;
- harga minimum/maksimum;
- kapasitas;
- buffer time;
- staff yang dapat melayani;
- resource yang dibutuhkan;
- room yang dapat digunakan;
- stok yang dikonsumsi;
- booking rules;
- cancellation rules;
- payment rules.

## Contoh

**Facial Premium**

- Durasi: 90 menit
- Terapis: 2 orang
- Room: Treatment Room
- Buffer: 15 menit
- Kapasitas: 1 customer
- Consumable: Mask 1 unit

---

# 16. RESOURCE ENGINE

Salah satu engine terpenting.

## 16.1 Resource type

- Person
- Staff
- Room
- Vehicle
- Court
- Studio
- Equipment
- Bed
- Table
- Cabin
- Custom Resource

> Daftar ini hanya contoh awal. Resource type bersifat extensible (lihat bagian 174 dan klarifikasi bagian 210).

## 16.2 Resource properties

- Name
- Type
- Capacity
- Availability
- Schedule
- Status
- Location
- Booking rules
- Maintenance/blocked dates
- Assigned staff
- Service compatibility

## 16.3 Resource state

```text
AVAILABLE
BOOKED
BLOCKED
MAINTENANCE
INACTIVE
```

---

# 17. INVENTORY / STOCK

Inventory bersifat opsional dan hanya diperlukan bisnis yang memakai barang.

## 17.1 Stock examples

- kosmetik;
- obat/produk;
- alat sewa;
- perlengkapan;
- makanan;
- consumable;
- sparepart.

## 17.2 Stock movement

```text
OPENING
PURCHASE
ADJUSTMENT_IN
ADJUSTMENT_OUT
CONSUMED_BY_BOOKING
SALE
RETURN
WASTE
```

## 17.3 Booking stock reservation

Sistem harus mendukung:

1. Reserve saat booking;
2. Deduct saat check-in/service;
3. Release saat cancellation;
4. Deduct final saat completed.

Mode harus dapat dikonfigurasi per bisnis/service.

---

# 18. SCHEDULING ENGINE

Sistem menentukan availability dari:

```text
Business Hours
+ Staff Schedule
+ Resource Schedule
+ Service Duration
+ Buffer
+ Existing Booking
+ Capacity
+ Blocked Time
+ Holiday
+ Blackout Date
+ Subscription Rules
```

## 18.1 Timezone

Setiap business mempunyai timezone.

Default Indonesia:

`Asia/Jakarta`

Pilihan:

- Asia/Jakarta
- Asia/Makassar
- Asia/Jayapura

Customer melihat waktu dalam timezone bisnis.

Semua timestamp internal idealnya disimpan dalam UTC dan dikonversi pada display layer.

## 18.2 DST

Walaupun sebagian besar wilayah Indonesia tidak menggunakan DST, engine tetap harus timezone-aware agar platform aman jika kelak digunakan di negara lain.

---

# 19. AVAILABILITY ENGINE

Availability bukan data statis.

Contoh:

```text
09:00
09:30
10:00
10:30
11:00
```

Slot harus dihitung berdasarkan konflik aktual.

## Contoh konflik

Resource A:

09:00–10:00 booked.

Maka service berdurasi 60 menit:

- 08:00 mungkin tersedia;
- 09:00 tidak tersedia;
- 09:30 tidak tersedia;
- 10:00 tersedia apabila rule memperbolehkan.

---

# 20. BUFFER TIME

Support:

- before-service buffer;
- after-service buffer.

Contoh:

Service 60 menit + buffer 15 menit.

Booking 10:00–11:00.

Resource dianggap occupied:

10:00–11:15.

---

# 21. WORKFLOW BUILDER

Ini adalah **Killer Feature** AMAN BOOKING.

## 21.1 Tampilan

```text
┌────────────────────────────────────────────────────────────┐
│ Workflow: Booking Spa                                       │
│ [Save] [Test] [Publish]                                    │
├──────────────┬─────────────────────────────────────────────┤
│ Node Library │                 Canvas                       │
│              │                                             │
│ Trigger      │  Booking Created                            │
│ Condition    │          ↓                                  │
│ Action       │      Payment?                               │
│ Delay        │       /    \                                │
│ Approval     │     YES     NO                              │
│              │      ↓       ↓                              │
│              │ Confirmed  Pending Payment                  │
└──────────────┴─────────────────────────────────────────────┘
```

## 21.2 Interaction

- drag node;
- drop node;
- connect node;
- edit property;
- duplicate;
- disable;
- test;
- publish;
- version.

---

# 22. NODE TYPES

## Trigger

- Booking Created
- Booking Updated
- Booking Status Changed
- Payment Received
- Payment Failed
- Customer Checked In
- Service Started
- Service Completed
- Booking Cancelled
- Booking No Show
- Schedule Approaching
- Customer Created

## Condition

- Payment Status
- Booking Status
- Service
- Resource
- Staff
- Customer Tag
- Total Amount
- Date/Time
- Quantity
- Custom Field

## Action

- Change Status
- Assign Staff
- Assign Resource
- Send WhatsApp
- Send Email
- Send Notification
- Create Invoice
- Create Task
- Add Tag
- Remove Tag
- Reserve Stock
- Release Stock
- Deduct Stock
- Add Note
- Webhook/API

## Delay

- Minutes
- Hours
- Days
- Before booking
- After booking
- Before start
- After completion

## Approval

- Owner
- Manager/member with permission

---

# 23. WORKFLOW VERSIONING

Workflow yang sudah dipublish tidak boleh diubah secara destruktif.

Gunakan:

```text
Draft
Version 1
Version 2
Version 3
```

Booking existing harus tetap terkait dengan workflow version saat booking diproses, kecuali bisnis sengaja migrasi.

---

# 24. WORKFLOW KANBAN

Kanban menampilkan status yang berasal dari workflow.

Contoh:

```text
NEW
│
├── PENDING PAYMENT
│
├── CONFIRMED
│
├── CHECKED IN
│
├── IN SERVICE
│
├── COMPLETED
│
└── CANCELLED
```

Card booking menampilkan:

- customer;
- service;
- time;
- staff;
- resource;
- payment;
- status.

## Drag-and-drop rules

Drag ke status lain harus menjalankan validasi.

Contoh:

Customer mencoba:

`Pending Payment → Confirmed`

Jika payment required:

> Tidak boleh dipindahkan sebelum payment valid.

---

# 25. CUSTOM STATUS

Pemilik usaha dapat membuat status sendiri.

Contoh:

```text
Request Masuk
↓
Menunggu Konfirmasi
↓
DP Masuk
↓
Siap
↓
Diproses
↓
Selesai
```

Tetap ada system status category/internal state untuk menjaga integritas data.

---

# 26. FORM BUILDER

Pemilik usaha membuat form booking sendiri.

## Field types

- Text
- Long text
- Number
- Currency
- Phone
- Email
- Date
- Time
- Date Time
- Dropdown
- Multi Select
- Radio
- Checkbox
- File Upload
- Address
- Signature
- Customer
- Staff
- Service
- Resource

## Form configuration

- label;
- placeholder;
- required;
- default value;
- validation;
- visibility condition;
- order;
- help text.

---

# 27. CONDITIONAL FORM

Contoh:

Jika:

`Customer memilih Rental Mobil`

maka tampil:

- jenis kendaraan;
- SIM;
- lokasi pickup;
- lokasi return.

Jika memilih:

`Pickup Hotel`

maka field:

- nama hotel;
- nomor kamar.

---

# 28. LANDING PAGE BUILDER

Pemilik usaha dapat membuat landing page sederhana.

Tujuan:

> Menjelaskan bisnis dan layanan lalu mengarahkan customer ke booking.

## Section

- Hero
- Tentang bisnis
- Layanan
- Harga
- Galeri
- Keunggulan
- FAQ
- Testimoni
- Kontak
- CTA Booking
- Footer

## CTA

Primary:

> **Booking Sekarang**

Secondary:

> **Chat WhatsApp**

---

# 29. SIMPLE WEBSITE BUILDER

User tidak perlu membuat website dari nol.

Template:

- Barber
- Salon
- Spa
- Rental
- Sport
- Studio
- Hotel/Villa
- Consultant
- Course
- Clinic
- Workshop
- Generic Business

Customization:

- logo;
- warna;
- font preset;
- gambar;
- teks;
- section;
- urutan section;
- CTA.

MVP tidak perlu page builder yang serumit website builder profesional.

---

# 30. BOOKING PAGE

Booking page harus dapat berdiri sendiri.

Contoh:

```text
booking.amanbooking.com/namabisnis
```

Atau slug/platform domain lain sesuai implementasi.

## Customer journey

```text
Landing Page
      ↓
Pilih Layanan
      ↓
Pilih Resource/Staff jika diperlukan
      ↓
Pilih Date
      ↓
Pilih Time
      ↓
Isi Customer Data
      ↓
Review
      ↓
Payment/Deposit jika diperlukan
      ↓
Confirmation
```

---

# 31. FAST BOOKING MODE

Bisnis dapat mengaktifkan:

> **Quick Booking**

Data:

- service;
- date;
- time;
- WhatsApp.

Form tambahan muncul setelah booking dibuat jika diperlukan.

Tujuannya mengurangi friction.

---

# 32. CUSTOMER CONFIRMATION

Setelah booking:

```text
Booking Berhasil

Kode:
BK-20261004-00125

Layanan:
Facial Premium

Tanggal:
10 Oktober 2026

Jam:
14.00

Resource:
Room 2

Status:
Confirmed
```

CTA:

- Tambah ke kalender
- Buka WhatsApp
- Lihat booking
- Reschedule
- Cancel

Sesuai kebijakan bisnis.

---

# 33. RESCHEDULE

Customer dapat reschedule jika policy mengizinkan.

Rule:

- maksimal X kali;
- minimum X jam sebelum booking;
- hanya slot yang tersedia;
- payment tetap valid;
- workflow reschedule dijalankan.

---

# 34. CANCELLATION

Cancellation policy configurable:

- bebas;
- minimal X jam;
- hanya admin;
- ada refund;
- no refund;
- deposit hangus sebagian;
- deposit hangus seluruhnya.

---

# 35. WAITLIST

Phase berikutnya tetapi arsitektur sebaiknya disiapkan.

Jika slot penuh:

> Join Waitlist

Ketika slot tersedia:

> sistem mengirim notifikasi customer berikutnya.

---

# 36. CHECK-IN

Owner/member dapat check-in customer.

Metode:

- manual;
- booking code;
- QR code.

Check-in dapat memicu workflow.

---

# 37. NOTIFICATION ENGINE

Channel:

- WhatsApp
- Email
- In-App
- SMS (future)

Event:

- booking created;
- confirmed;
- payment received;
- reminder;
- changed;
- cancelled;
- completed;
- no show.

Template message configurable.

---

# 38. WHATSAPP INTEGRATION

AMAN BOOKING idealnya terintegrasi dengan AMAN CHAT.

Use cases:

- booking confirmation;
- payment reminder;
- reminder H-1;
- reminder H-2;
- reschedule;
- cancellation;
- follow-up;
- repeat booking.

---

# 39. CUSTOMER CRM

Customer profile:

```text
Customer
├── Profile
├── Contact
├── Tags
├── Notes
├── Booking History
├── Payment History
├── Cancellation History
├── No-show History
└── Consent
```

Search:

- nama;
- WhatsApp;
- email;
- booking code.

---

# 40. CUSTOMER CONSENT & PRIVACY

Customer form harus dapat menampilkan:

- privacy notice;
- consent;
- marketing consent terpisah.

Marketing consent tidak boleh dianggap otomatis hanya karena customer melakukan booking.

---

# 41. SEARCH & FILTER

Owner dapat mencari booking berdasarkan:

- booking code;
- customer;
- WhatsApp;
- service;
- staff;
- resource;
- room;
- status;
- payment;
- date;
- source.

---

# 42. CALENDAR VIEW

Mode:

- Day
- Week
- Month
- Agenda

Filter:

- staff;
- resource;
- room;
- service;
- status.

Calendar harus mendukung drag-and-drop reschedule untuk owner/member berizin.

Setiap drag harus:

1. validasi conflict;
2. validasi policy;
3. simpan perubahan;
4. catat audit;
5. jalankan workflow;
6. kirim notifikasi jika diperlukan.

---

# 43. MOBILE OWNER

Owner/mobile member harus dapat:

- melihat calendar;
- melihat booking;
- membuat booking;
- ubah status;
- check-in;
- menghubungi customer;
- lihat availability.

Visual workflow builder versi mobile dapat menggunakan read-only atau simplified editor pada MVP; full builder diprioritaskan desktop.

---

# 44. REPORTING

MVP:

- jumlah booking;
- completed;
- cancelled;
- no show;
- pending;
- revenue;
- customer baru;
- repeat customer;
- resource utilization.

Advanced:

- revenue per service;
- revenue per staff;
- utilization per room;
- conversion booking;
- cancellation rate;
- no-show rate;
- customer retention.

---

# 45. PAYMENT

Payment model harus configurable:

- no payment;
- full payment;
- deposit;
- partial payment.

Payment statuses:

```text
UNPAID
PENDING
PARTIAL
PAID
FAILED
REFUNDED
PARTIAL_REFUND
```

---

# 46. GENERIC BUSINESS MODEL

AMAN BOOKING harus mampu memodelkan:

### 46.1 Service business
Barber, salon, spa, consulting.

### 46.2 Resource rental
Mobil, motor, kamera, alat.

### 46.3 Room booking
Hotel, villa, meeting room.

### 46.4 Capacity booking
Class, event, gym, workshop.

### 46.5 Court booking
Futsal, badminton, tennis, padel.

### 46.6 Staff appointment
Dokter, therapist, trainer, consultant.

### 46.7 Hybrid
Service + room + staff + product.

---

# 47. EXAMPLE WORKFLOW TEMPLATES

## 47.1 Barber

```text
Booking Created
↓
Confirmed
↓
Check In
↓
In Service
↓
Completed
↓
Payment
↓
Follow Up
```

## 47.2 Rental

```text
Booking Request
↓
Document Verification
↓
Deposit
↓
Approved
↓
Vehicle Prepared
↓
Picked Up
↓
Rental Active
↓
Returned
↓
Inspection
↓
Completed
```

## 47.3 Hotel/Villa

```text
Booking
↓
Payment
↓
Confirmed
↓
Check In
↓
Stay
↓
Check Out
↓
Completed
```

## 47.4 Sports Court

```text
Booking
↓
Payment
↓
Confirmed
↓
Check In
↓
Playing
↓
Completed
```

## 47.5 Clinic

```text
Booking
↓
Confirmed
↓
Check In
↓
Waiting
↓
Consultation
↓
Completed
```

---

# 48. GENERAL BUSINESS ONBOARDING

Wizard:

## Step 1 — Business

- Nama
- Logo
- WhatsApp
- Email
- Alamat

## Step 2 — Business Type

Pilih template:

- Barber
- Salon
- Spa
- Rental
- Sport
- Hotel/Villa
- Clinic
- Studio
- Course
- Consultant
- Other

## Step 3 — Services

Tambahkan layanan.

## Step 4 — Resource

Tambahkan:

- staff;
- room;
- unit;
- court;
- vehicle.

## Step 5 — Schedule

Atur:

- jam buka;
- hari buka;
- break;
- holiday.

## Step 6 — Workflow

Gunakan template atau buat sendiri.

## Step 7 — Booking Page

Publish.

## Step 8 — Go Live

Link siap dibagikan.

---

# 49. ZERO-CONFIG DEFAULT

Jika user baru tidak memahami workflow:

Sistem membuat default:

```text
Booking Created
↓
Confirmed
↓
Completed
```

Pemilik usaha dapat mengubahnya kapan saja.

---

# 50. DEFAULT BUSINESS RULES

Default harus aman.

### Double booking
Ditolak.

### Missing customer
Ditolak.

### Booking outside business hours
Ditolak.

### Resource inactive
Ditolak.

### Subscription limit
Dicegah.

### Payment required
Booking tidak menjadi Confirmed sebelum rule terpenuhi.

---

# 51. EDGE CASE WAJIB

## 51.1 Double booking race condition

Dua customer memesan slot yang sama hampir bersamaan.

Solusi harus berada di backend/database transaction, bukan hanya UI.

## 51.2 Timezone mismatch

UI business dan customer harus konsisten.

## 51.3 Owner mengubah jam kerja setelah booking dibuat

Booking existing tetap dipertahankan.

Perubahan schedule memengaruhi booking baru kecuali owner melakukan reschedule.

## 51.4 Resource dinonaktifkan

Booking existing tidak otomatis hilang.

Sistem menandai:

> Resource conflict requires action.

## 51.5 Service dihapus

Tidak boleh menghapus secara hard delete jika sudah digunakan booking.

Gunakan archive/inactive.

## 51.6 Customer melakukan booking lalu refresh

Tidak boleh membuat booking duplikat karena retry/idempotency.

## 51.7 Payment callback datang dua kali

Harus idempotent.

## 51.8 WhatsApp gagal

Booking tetap tersimpan.

Notification status menjadi failed dan dapat retry.

## 51.9 Workflow error

Booking tidak boleh hilang.

Harus tersedia:

- event log;
- retry;
- failed action;
- manual recovery.

## 51.10 Subscription expired

Data tidak boleh langsung dihapus.

---

# 52. AUDIT LOG

Audit minimum:

- login;
- logout;
- booking create;
- booking update;
- booking status change;
- reschedule;
- cancellation;
- payment update;
- customer update;
- service update;
- resource update;
- stock adjustment;
- workflow publish;
- workflow change;
- subscription change;
- tenant suspend.

Audit fields:

- actor;
- actor role;
- tenant;
- action;
- entity type;
- entity ID;
- before;
- after;
- timestamp;
- IP/metadata sesuai kebijakan;
- source.

---

# 53. SECURITY

## Authentication

- secure session/token;
- password hashing jika password digunakan;
- optional OTP;
- rate limiting;
- session expiry.

## Authorization

Server-side authorization wajib.

UI hiding saja tidak cukup.

## Tenant isolation

Wajib diuji.

## Public API

- rate limit;
- abuse protection;
- input validation.

## File upload

- type validation;
- size limits;
- secure storage;
- virus/malware scanning bila diperlukan.

---

# 54. DATA RETENTION

Subscription cancellation tidak berarti langsung menghapus tenant.

Diperlukan:

- retention policy;
- export data;
- deletion request;
- backup policy.

Owner harus dapat meminta export data.

---

# 55. ANALYTICS EVENT

Track event produk:

```text
landing_view
booking_started
service_selected
slot_selected
customer_form_started
booking_created
payment_started
payment_success
booking_cancelled
reschedule_completed
workflow_created
workflow_published
landing_published
```

Gunakan untuk product analytics, bukan menjual data pribadi customer.

---

# 56. PERFORMANCE TARGET

## Customer booking

Target:

- initial load cepat;
- availability response ideal < 1–2 detik pada kondisi normal;
- booking submit ideal < 2 detik pada kondisi normal.

## Owner dashboard

Target:

- dashboard initial interaction responsif;
- calendar tidak reload penuh saat pindah tanggal;
- Kanban drag terasa instan dengan optimistic UI jika aman.

## Workflow editor

Target:

- drag/drop node smooth;
- autosave draft optional;
- tidak kehilangan perubahan saat browser refresh/crash.

---

# 57. RELIABILITY

Booking creation adalah transaksi kritikal.

Backend harus menjamin:

```text
Validate
→ Check Availability
→ Reserve
→ Create Booking
→ Apply Payment Rule
→ Commit
```

Jika salah satu langkah kritikal gagal:

> Tidak boleh meninggalkan booking setengah jadi.

Gunakan transaction dan idempotency key pada endpoint yang memungkinkan retry.

---

# 58. DATA MODEL — ENTITAS INTI

Minimal:

```text
users
tenants
businesses
business_members
subscriptions
plans
subscription_usage
services
products
resources
resource_types
staff
rooms
locations
business_hours
staff_schedules
resource_schedules
blackout_dates
bookings
booking_items
booking_resources
booking_custom_fields
booking_statuses
workflow_definitions
workflow_versions
workflow_nodes
workflow_edges
workflow_runs
workflow_logs
forms
form_fields
customers
customer_tags
customer_notes
payments
invoices
inventory_items
inventory_movements
notification_templates
notification_logs
landing_pages
landing_sections
audit_logs
integrations
api_keys
webhooks
```

---

# 59. API MODULES

Kontrak API harus dipisahkan jelas.

## Auth API

- login
- logout
- refresh
- OTP

## Tenant API

- business profile
- members
- settings

## Booking API

- availability
- create
- read
- update
- cancel
- reschedule
- check-in

## Resource API

- staff
- rooms
- units
- resources
- schedules

## Workflow API

- create draft
- save nodes
- publish
- execute
- logs

## Customer API

- profile
- history
- tags

## Payment API

- invoice
- payment
- status

## Public API

Public booking page harus menggunakan endpoint yang aman dan seminimal mungkin.

---

# 60. IDEMPOTENCY

Endpoint berikut wajib mendukung idempotency:

- create booking;
- payment intent;
- payment callback;
- webhook event;
- notification dispatch jika provider mendukung.

Contoh:

`Idempotency-Key: booking-request-unique-key`

---

# 61. NOTIFICATION RETRY

Status:

```text
QUEUED
SENDING
SENT
FAILED
RETRYING
DEAD_LETTER
```

Retry harus terbatas.

Tidak boleh infinite retry.

---

# 62. WORKFLOW EXECUTION SAFETY

Workflow harus mencegah:

- infinite loop;
- duplicate action;
- duplicate notification;
- recursive trigger berbahaya.

Sediakan:

- max execution depth;
- idempotent action;
- execution ID;
- retry counter;
- failure state.

---

# 63. UI UX — CUSTOMER FLOW DETAIL

## Screen 1 — Landing

Informasi minimal:

- logo;
- nama bisnis;
- deskripsi;
- layanan;
- CTA.

## Screen 2 — Services

Card:

```text
Haircut
30 min
Rp50.000
[ Pilih ]
```

## Screen 3 — Schedule

Calendar + available slot.

## Screen 4 — Customer Info

```text
Nama
WhatsApp
Email
Catatan
```

## Screen 5 — Review

Tampilkan:

- service;
- resource;
- date;
- time;
- price;
- policy.

## Screen 6 — Confirmation

Berikan:

- booking code;
- summary;
- next action.

---

# 64. MOBILE CUSTOMER UX RULES

- Jangan memaksa customer login.
- Hindari form panjang.
- Gunakan keyboard type yang sesuai.
- Simpan input sementara.
- Jangan reset form ketika validasi satu field gagal.
- Tampilkan error dekat field.
- Jangan gunakan modal bertumpuk.
- CTA utama selalu jelas.
- Kalender dapat digunakan dengan satu tangan.
- Time slot menunjukkan status tersedia/tidak tersedia dengan jelas.

---

# 65. ACCESSIBILITY

Target minimal:

- keyboard accessible pada owner web;
- semantic labels;
- focus state;
- contrast yang memadai;
- screen-reader friendly form;
- error message jelas;
- jangan mengandalkan warna saja.

---

# 66. OWNER UX — WORKFLOW EDITOR

## Toolbar

- Undo
- Redo
- Zoom
- Fit Canvas
- Save
- Test
- Publish

## Left panel

Node library.

## Center

Canvas.

## Right panel

Property editor.

## Bottom optional

Execution/log preview.

---

# 67. OWNER UX — BOOKING DETAIL

Booking detail drawer/modal:

```text
Customer
Service
Schedule
Staff
Resource
Payment
Workflow
Notes
Activity
```

Quick actions:

- Confirm
- Reschedule
- Assign
- Check In
- Complete
- Cancel
- Contact Customer

---

# 68. BUSINESS PAGE CUSTOMIZATION

Simple builder:

```text
[ + Add Section ]

Hero
Services
About
Gallery
FAQ
Testimonials
Contact
CTA
```

Drag to reorder.

Preview:

- Desktop
- Tablet
- Mobile

Publish/unpublish.

---

# 69. TEMPLATE SYSTEM

Templates dibagi menjadi:

## Platform Template

Dibuat Super Admin.

## Business Template

Dibuat Pemilik Usaha.

Template dapat berupa:

- workflow;
- form;
- landing page;
- automation.

Template tidak boleh membocorkan data customer.

---

# 70. INTEGRATION STRATEGY

Prioritas:

### Phase 1
- WhatsApp link/basic notification
- Email

### Phase 2
- WhatsApp API
- Payment gateway
- Calendar integration

### Phase 3
- AMAN CHAT
- AMAN KASIR
- REST API
- Webhook

---

# 71. INTEGRASI AMAN KASIR

Bukan bagian mandatory MVP.

Future:

```text
Booking Completed
        ↓
AMAN BOOKING
        ↓
Create Transaction
        ↓
AMAN KASIR
        ↓
Financial Reporting
```

Contoh:

Salon melakukan booking Haircut Rp50.000.

Setelah selesai:

- booking Completed;
- transaksi dibuat;
- revenue masuk laporan.

---

# 72. INTEGRASI AMAN CHAT

```text
Booking Created
       ↓
AMAN BOOKING
       ↓
AMAN CHAT
       ↓
WhatsApp
```

CRM customer dapat disinkronkan berdasarkan identitas customer yang diizinkan.

---

# 73. SUPER ADMIN DASHBOARD

Dashboard:

```text
Total Tenant
Active Tenant
Trial Tenant
Expired
Suspended
Bookings Today
Bookings This Month
Revenue Subscription
Failed Payment
System Health
```

## Tenant table

Kolom:

- Business
- Owner
- Plan
- Status
- Created
- Renewal
- Usage
- Last Activity

Action:

- View
- Suspend
- Activate
- Change Plan
- Extend Trial
- Support Access
- Audit

---

# 74. SUPER ADMIN — TENANT DETAIL

Tab:

- Overview
- Owner
- Business
- Subscription
- Usage
- Billing
- Activity
- Audit
- Support Notes

Support access harus aman dan diaudit.

---

# 75. SUPER ADMIN — PLAN MANAGEMENT

Dapat mengubah:

- name;
- price;
- billing cycle;
- limits;
- enabled features;
- trial;
- grace period.

Perubahan plan tidak boleh merusak data tenant existing.

---

# 76. CUSTOMER JOURNEY

```text
Google / Social / Ads / WhatsApp
              ↓
        Landing Page
              ↓
         Lihat Layanan
              ↓
       Klik Booking
              ↓
       Pilih Jadwal
              ↓
       Isi WhatsApp
              ↓
        Konfirmasi
              ↓
         Payment
              ↓
         Reminder
              ↓
        Check In
              ↓
          Service
              ↓
         Completed
              ↓
        Follow Up
              ↓
       Repeat Booking
```

---

# 77. METRICS / KPI PRODUK

## Platform

- Active tenants
- Paid conversion
- Trial conversion
- Churn
- MRR
- ARPU
- Subscription renewal

## Business tenant

- Booking volume
- Booking conversion
- Cancellation rate
- No-show rate
- Repeat booking
- Revenue
- Resource utilization

## Customer UX

- Landing → booking conversion
- Booking completion rate
- Drop-off rate
- Average booking time
- Mobile completion rate

---

# 78. MVP SCOPE

## WAJIB

### Super Admin

- auth;
- tenant;
- owner;
- plans;
- subscription;
- usage;
- suspend/activate.

### Owner

- onboarding;
- business profile;
- service;
- resource;
- schedule;
- availability;
- booking;
- calendar;
- Kanban;
- customer;
- basic workflow;
- form;
- landing page basic;
- booking page.

### Customer

- public landing;
- service selection;
- date/time selection;
- customer info;
- booking;
- confirmation;
- basic reschedule/cancel jika diizinkan.

---

# 79. MVP WORKFLOW

MVP minimal harus mendukung:

```text
Trigger
Condition
Action
```

Node minimum:

- Booking Created
- Payment Status
- Status
- Assign Resource
- Change Status
- Send Notification

Delay/automation dapat masuk MVP akhir jika resource development memungkinkan.

---

# 80. MVP YANG TIDAK WAJIB

Jangan menahan launch karena:

- AI chatbot;
- advanced accounting;
- loyalty;
- marketplace;
- advanced BI;
- payroll;
- complex inventory;
- full website builder;
- native mobile app.

Web responsive harus cukup untuk MVP.

---

# 81. ACCEPTANCE CRITERIA — ROLE

### Super Admin

- dapat membuat tenant;
- dapat mengaktifkan subscription;
- dapat melihat penggunaan;
- dapat suspend tenant;
- tenant yang disuspend kehilangan kemampuan operasional sesuai rule.

### Owner

- dapat membuat bisnis;
- dapat membuat service;
- dapat membuat resource;
- dapat mengatur schedule;
- dapat publish booking page;
- dapat melihat booking;
- dapat mengubah workflow.

### Customer

- dapat booking tanpa akun;
- dapat melihat slot;
- dapat menerima confirmation;
- tidak dapat melihat data customer lain.

---

# 82. ACCEPTANCE CRITERIA — BOOKING

1. Slot conflict tidak dapat dibooking.
2. Booking yang berhasil memiliki unique booking code.
3. Refresh/duplicate submit tidak membuat booking ganda.
4. Booking tersimpan dengan timezone bisnis yang benar.
5. Service, resource, customer, dan payment state dapat direkonsiliasi.
6. Cancel/reschedule mengikuti business policy.
7. Semua perubahan kritikal tercatat audit.

---

# 83. ACCEPTANCE CRITERIA — WORKFLOW

1. User dapat membuat node.
2. User dapat menghubungkan node.
3. User dapat menyimpan draft.
4. User dapat publish version.
5. Workflow aktif dapat dieksekusi.
6. Error execution dapat dilihat.
7. Workflow tidak dapat infinite loop.
8. Duplicate execution dapat dicegah.

---

# 84. ACCEPTANCE CRITERIA — AVAILABILITY

Availability harus konsisten di:

- customer page;
- owner calendar;
- manual booking;
- API.

Sumber kebenaran availability harus satu.

Jangan membuat tiga algoritma availability berbeda.

---

# 85. SINGLE SOURCE OF TRUTH

Definisi bisnis wajib konsisten:

```text
Database
      ↓
Backend Domain Logic
      ↓
Availability / Booking Service
      ↓
API
      ↓
Web Owner
      ↓
Public Customer Page
      ↓
Reports
```

Frontend tidak boleh menghitung aturan kritikal secara mandiri.

Contoh:

> Customer melihat slot tersedia karena backend menyatakan tersedia.

Bukan karena frontend menebak.

---

# 86. ERROR HANDLING

Pesan error harus ramah.

Jangan:

> `SQLSTATE 23000`

Gunakan:

> “Slot tersebut baru saja dipesan customer lain. Silakan pilih waktu lain.”

Admin error:

> “Perubahan gagal disimpan. Data Anda tetap aman. Silakan coba lagi.”

Workflow error:

> “Automasi gagal mengirim WhatsApp. Booking tetap tersimpan.”

---

# 87. OBSERVABILITY

Minimal:

- API logs;
- booking events;
- workflow execution logs;
- notification logs;
- payment logs;
- audit logs;
- error monitoring.

Correlation ID:

Setiap request kritikal memiliki correlation ID.

---

# 88. BACKUP & RECOVERY

Wajib:

- automated backup;
- restore test;
- retention policy;
- disaster recovery procedure.

Backup tidak dianggap valid sampai pernah diuji restore.

---

# 89. TEST STRATEGY

## Unit test

- availability;
- pricing;
- status transition;
- workflow;
- resource conflict;
- timezone;
- subscription limit.

## Integration test

- booking + payment;
- booking + workflow;
- booking + notification;
- booking + inventory.

## E2E test

### Flow 1

Owner onboarding → publish → customer booking.

### Flow 2

Customer booking → payment → confirmation.

### Flow 3

Booking → check-in → complete.

### Flow 4

Double booking simultaneous request.

### Flow 5

Reschedule.

### Flow 6

Cancellation.

### Flow 7

Subscription expired.

### Flow 8

Tenant isolation.

---

# 90. SECURITY TEST CASE

Wajib menguji:

- tenant A tidak bisa membaca tenant B;
- customer tidak bisa mengakses customer lain;
- member tidak bisa mengakses fitur yang tidak diizinkan;
- public endpoint tidak bocor data private;
- expired tenant tidak dapat bypass restriction;
- workflow permission;
- file upload;
- rate limit;
- replay payment callback;
- duplicate booking request.

---

# 91. LAUNCH CHECKLIST

## Platform

- [ ] Auth
- [ ] Tenant isolation
- [ ] Billing
- [ ] Plan
- [ ] Subscription
- [ ] Audit

## Owner

- [ ] Onboarding
- [ ] Business
- [ ] Service
- [ ] Resource
- [ ] Schedule
- [ ] Booking
- [ ] Calendar
- [ ] Kanban
- [ ] Workflow
- [ ] Form
- [ ] Landing page

## Customer

- [ ] Mobile booking
- [ ] Availability
- [ ] Form
- [ ] Confirmation
- [ ] Cancellation
- [ ] Reschedule

## Reliability

- [ ] Transaction
- [ ] Idempotency
- [ ] Retry
- [ ] Monitoring
- [ ] Backup

---

# 92. ROADMAP IMPLEMENTASI

## PHASE 0 — FOUNDATION

- architecture;
- authentication;
- tenant;
- role;
- permission;
- database;
- audit;
- subscription foundation.

## PHASE 1 — BOOKING CORE

- business;
- service;
- resource;
- schedule;
- availability;
- booking;
- customer;
- calendar.

## PHASE 2 — PUBLIC EXPERIENCE

- landing template;
- booking page;
- mobile UX;
- confirmation;
- basic notification.

## PHASE 3 — WORKFLOW

- Kanban;
- custom statuses;
- workflow builder;
- execution engine;
- logs.

## PHASE 4 — BUSINESS OPERATIONS

- inventory;
- payment;
- check-in;
- reschedule;
- cancellation;
- resource utilization.

## PHASE 5 — AUTOMATION & INTEGRATION

- WhatsApp;
- email automation;
- payment gateway;
- AMAN CHAT;
- AMAN KASIR.

## PHASE 6 — ADVANCED

- waitlist;
- API;
- webhook;
- custom domain;
- advanced analytics;
- marketplace/templates.

---

# 93. NON-FUNCTIONAL REQUIREMENTS

## Security
- server-side authorization;
- tenant isolation;
- encryption in transit;
- secure secret management.

## Reliability
- transactional booking;
- idempotency;
- retry;
- observability.

## Scalability
- multi-tenant;
- asynchronous notification;
- workflow queue;
- cache availability jika aman;
- database indexing.

## Maintainability
- domain-driven modules;
- clear service boundaries;
- schema migration;
- automated test suite.

---

# 94. REKOMENDASI ARCHITECTURE LOGIS

Pisahkan domain:

```text
Identity
Tenant
Subscription
Business
Catalog
Resource
Schedule
Booking
Customer
Workflow
Automation
Payment
Inventory
Notification
Landing
Reporting
Audit
Integration
```

Jangan mencampur seluruh logic booking di controller/UI.

---

# 95. ATURAN ARSITEKTUR PENTING

1. Booking status logic harus berada di backend/domain layer.
2. Availability harus memiliki satu source of truth.
3. Workflow adalah mesin konfigurasi, bukan hardcoded per bisnis.
4. Resource harus generic.
5. Service harus dapat meminta resource.
6. Inventory harus optional.
7. Notification asynchronous.
8. Payment callback idempotent.
9. Tenant isolation wajib pada seluruh jalur data.
10. UI tidak boleh menjadi sumber kebenaran untuk business rules.
11. Data transaksi tidak boleh hard delete.
12. Versioning diperlukan untuk workflow yang sudah dipublish.

---

# 96. BUSINESS RULE ENGINE

Untuk fleksibilitas jangka panjang, rule engine dapat menyimpan:

```text
IF
condition

THEN
action

ELSE
action
```

Contoh:

```text
IF
booking.total >= 500000

THEN
require_deposit = true
```

Atau:

```text
IF
service.type = "rental"

THEN
require_document = true
```

Namun rule engine advanced tidak wajib dibuat pada MVP. Struktur data harus disiapkan agar dapat dikembangkan.

---

# 97. CONFIGURATION VS CUSTOM DEVELOPMENT

Semua hal berikut harus bisa dilakukan tanpa developer pada target akhir:

- tambah service;
- tambah resource;
- tambah status;
- ubah form;
- ubah jam operasi;
- ubah landing page;
- ubah booking policy;
- ubah workflow;
- tambah automation;
- tambah member;
- tambah template.

Hal yang tetap membutuhkan developer:

- integrasi payment gateway baru;
- integrasi API eksternal;
- plugin khusus;
- perubahan core engine.

---

# 98. CONTOH USER EXPERIENCE LENGKAP

## Skenario: Salon

Owner membuat bisnis:

**Salon Cantik**

Layanan:

- Haircut
- Hair Coloring
- Facial

Resource:

- Therapist A
- Therapist B
- Room 1
- Room 2

Workflow:

```text
Booking
↓
Pending Payment
↓
Confirmed
↓
Check In
↓
In Service
↓
Completed
```

Customer membuka:

`booking.amanbooking.com/saloncantik`

Memilih:

Facial → 14:00 → isi WhatsApp → Confirm.

Sistem:

1. cek availability;
2. reserve Room;
3. assign therapist sesuai rule;
4. create booking;
5. kirim confirmation;
6. workflow berjalan;
7. reminder dijadwalkan.

---

# 99. CONTOH USER EXPERIENCE RENTAL

Resource:

- Avanza A
- Avanza B
- Innova A

Customer:

- tanggal pickup;
- tanggal return;
- kendaraan.

Workflow:

```text
Request
↓
Document Verification
↓
Deposit
↓
Approved
↓
Pickup
↓
Active
↓
Return
↓
Inspection
↓
Completed
```

Sistem mencegah:

> kendaraan yang sama disewa pada waktu yang tumpang tindih.

---

# 100. CONTOH USER EXPERIENCE HOTEL

Resource:

- Room 101
- Room 102
- Room 103

Service:

- Stay

Booking:

- check-in;
- check-out;
- guest count.

Workflow:

```text
Booking
↓
Payment
↓
Confirmed
↓
Check In
↓
Stay
↓
Check Out
↓
Completed
```

Availability dihitung berdasarkan:

- room;
- date range;
- blocked room;
- maintenance.

---

# 101. CONTOH USER EXPERIENCE LAPANGAN

Resource:

- Court A
- Court B
- Court C

Service:

- Badminton 1 Hour
- Badminton 2 Hours
- Futsal 1 Hour

System:

- pilih lapangan;
- pilih tanggal;
- pilih slot;
- payment;
- confirmation.

---

# 102. CONTOH USER EXPERIENCE KURSUS

Resource:

- Class A
- Teacher A

Capacity:

20 students.

Jika booking ke-20 sukses:

> slot menjadi full.

Booking ke-21:

> masuk waitlist atau ditolak sesuai configuration.

---

# 103. PRODUCT POSITIONING

## Nama

**AMAN BOOKING**

## Category

**Visual Booking & Workflow Platform**

## Core message

> Booking sesuai cara bisnis Anda.

## Secondary message

> Atur jadwal, layanan, resource, customer, pembayaran, dan workflow dalam satu sistem.

## Differentiator

> **Drag & Drop Workflow + Booking Engine Universal + Resource Management + Customer Journey.**

---

# 104. MVP SUCCESS CRITERIA

MVP dianggap berhasil apabila:

1. Satu owner dapat membuat bisnis dalam <15 menit.
2. Owner dapat membuat minimal satu workflow tanpa coding.
3. Customer dapat melakukan booking dari mobile tanpa akun.
4. Sistem tidak menghasilkan double booking dalam concurrent request.
5. Owner dapat melihat booking dalam calendar dan Kanban.
6. Owner dapat mengubah schedule/resource.
7. Customer menerima confirmation.
8. Data tenant terisolasi.
9. Subscription limit berfungsi.
10. Audit log tersedia untuk aktivitas kritikal.

---

# 105. PRODUCT NORTH STAR

North Star Metric:

> **Completed Bookings per Active Business**

Metric pendukung:

- booking conversion;
- repeat booking;
- active business;
- customer completion rate;
- resource utilization;
- subscription retention.

---

# 106. FINAL PRODUCT PRINCIPLE

AMAN BOOKING harus dibangun dengan prinsip:

```text
CORE ENGINE UNIVERSAL
        +
CONFIGURATION VISUAL
        +
SIMPLE CUSTOMER EXPERIENCE
        +
RESOURCE-AWARE SCHEDULING
        +
WORKFLOW AUTOMATION
        +
MULTI-TENANT SAAS
```

Bukan:

```text
Satu aplikasi khusus salon.
```

Tetapi:

```text
SATU ENGINE
        ↓
BANYAK JENIS BISNIS
        ↓
SETIAP BISNIS MEMILIKI WORKFLOW SENDIRI
```

---

# 107. DEFINITION OF DONE

Sebuah modul dianggap selesai hanya apabila:

- UI selesai;
- API selesai;
- validation selesai;
- permission selesai;
- audit selesai jika diperlukan;
- loading state selesai;
- empty state selesai;
- error state selesai;
- mobile responsive selesai;
- test unit selesai;
- integration test selesai;
- E2E untuk flow utama selesai;
- dokumentasi internal selesai;
- tidak ada regression pada modul terkait.

---

# 108. LARANGAN IMPLEMENTASI

Untuk menjaga kualitas:

1. Jangan membuat business rule penting hanya di frontend.
2. Jangan memakai hardcoded status untuk semua bisnis.
3. Jangan membuat availability logic berbeda antara public page dan owner dashboard.
4. Jangan menghapus booking secara hard delete.
5. Jangan menghapus service/resource yang sudah direferensikan transactionally.
6. Jangan membiarkan workflow mengirim action tanpa idempotency.
7. Jangan membiarkan tenant mengakses data tenant lain.
8. Jangan memaksa customer membuat akun sebelum booking.
9. Jangan membuat landing page builder terlalu kompleks pada MVP.
10. Jangan menambah fitur besar yang tidak mendukung core booking journey sebelum core stabil.

---

# 109. OUTPUT PRODUK YANG DIHARAPKAN

Pada kondisi ideal, seorang pemilik usaha baru dapat melakukan:

```text
Daftar
↓
Buat Bisnis
↓
Pilih Template
↓
Tambah Layanan
↓
Tambah Resource
↓
Atur Jadwal
↓
Pilih Workflow
↓
Edit Workflow Drag & Drop
↓
Edit Form
↓
Buat Landing Page
↓
Publish
↓
Bagikan Link
↓
Customer Booking
↓
Booking Masuk
↓
Workflow Berjalan
↓
Service
↓
Completed
↓
Customer Follow-up
↓
Repeat Booking
```

Tanpa membutuhkan developer.

---

# 110. PRIORITAS BACKLOG

Gunakan prioritas:

**P0 — Must Work**

- tenant isolation;
- auth;
- subscription;
- business;
- service;
- resource;
- schedule;
- availability;
- booking;
- customer;
- public booking;
- calendar;
- Kanban basic.

**P1 — Core Differentiator**

- workflow builder;
- custom status;
- form builder;
- landing builder;
- notification;
- automation;
- resource rules.

**P2 — Revenue Expansion**

- WhatsApp API;
- payment gateway;
- inventory;
- advanced reports;
- multi outlet;
- custom domain.

**P3 — Advanced**

- waitlist;
- API;
- webhook;
- integrations;
- marketplace;
- advanced analytics;
- AI.

---

# 111. FINAL SUMMARY

AMAN BOOKING harus diperlakukan sebagai:

> **platform operasi booking yang dapat dikonfigurasi**, bukan sekadar kalender reservasi.

Tiga role besar:

```text
SUPER ADMIN AMAN BOOKING
        ↓
Mengelola platform,
tenant/pemilik usaha,
subscription & system administration


PEMILIK USAHA
        ↓
Mengelola bisnis,
layanan,
produk,
resource,
staff,
ruangan,
kamar,
stok,
jadwal,
workflow,
landing page,
booking,
customer & laporan


CUSTOMER
        ↓
Booking cepat,
mobile-friendly,
tanpa registrasi rumit,
minimal WhatsApp + email,
dan dapat mengikuti alur bisnis.
```

Arsitektur core:

```text
TENANT
  ↓
BUSINESS
  ↓
SERVICE
  ↓
RESOURCE
  ↓
SCHEDULE
  ↓
AVAILABILITY
  ↓
BOOKING
  ↓
WORKFLOW
  ↓
PAYMENT
  ↓
NOTIFICATION
  ↓
CUSTOMER
  ↓
REPORTING
```

Kunci pembeda:

> **Pemilik usaha tidak menyesuaikan bisnisnya dengan aplikasi. Aplikasi yang disesuaikan dengan cara kerja bisnis melalui konfigurasi visual.**



# 112. BUSINESS TEMPLATE LIBRARY — UNIVERSAL

AMAN BOOKING wajib menyediakan template bisnis bawaan agar pemilik usaha tidak harus membuat struktur dari nol.

Prinsip:

> **Template hanya titik awal. Semua template dapat diedit.**

Setiap template minimal menentukan:

- jenis bisnis;
- jenis service;
- durasi default;
- staff/resource yang dapat dipilih;
- resource/room yang diperlukan;
- kapasitas;
- aturan availability;
- buffer time;
- kemungkinan multi-service;
- payment/deposit rule;
- workflow default;
- form booking default;
- notification default;
- landing page sections.

---

# 113. TEMPLATE GROUP — BEAUTY & PERSONAL CARE

## 113.1 Salon Wanita

### Contoh layanan

- Haircut
- Hair Wash
- Hair Styling
- Hair Coloring
- Bleaching
- Hair Spa
- Smoothing
- Rebonding
- Creambath
- Keratin
- Treatment Rambut
- Hair Extension
- Makeup
- Hijab Styling
- Paket Wedding / Event

### Resource

- Stylist
- Hair Therapist
- Makeup Artist
- Wash Station
- Styling Chair
- Treatment Room

### Data service

Contoh:

**Hair Coloring**

- duration: 180 menit
- buffer: 30 menit
- staff required: 1 stylist
- room/resource: 1 styling station
- capacity: 1 customer

### Workflow default

```text
Booking Masuk
↓
Menunggu Konfirmasi
↓
Confirmed
↓
Customer Datang
↓
In Service
↓
Completed
↓
Payment
↓
Follow Up
```

---

## 113.2 Barbershop

### Layanan

- Haircut
- Haircut + Wash
- Haircut + Beard
- Beard Trim
- Shaving
- Styling
- Hair Treatment
- Coloring
- Kids Haircut

### Resource

- Barber
- Barber Chair
- Wash Station

### Contoh

Haircut:

- 45 menit
- buffer 10 menit
- barber 1
- chair 1

Haircut + Hair Wash:

- service duration 60 menit
- buffer 10 menit
- barber 1
- chair 1
- wash station 1

### Workflow

```text
Booking
↓
Confirmed
↓
Check In
↓
In Service
↓
Completed
```

---

## 113.3 Manicure / Pedicure / Nail Studio

### Layanan

- Manicure
- Pedicure
- Gel Polish
- Nail Art
- Extension
- Removal
- Foot Spa

### Resource

- Nail Therapist
- Nail Table
- Pedicure Chair
- Treatment Room

### Pengaturan

Service dapat memiliki:

- duration;
- buffer;
- therapist;
- station;
- consumable product;
- capacity.

Contoh:

Nail Art Premium:

- 120 menit
- 1 nail artist
- 1 nail table
- consumable: polish tertentu.

---

## 113.4 Spa & Sauna / Wellness

### Layanan

- Sauna
- Steam
- Body Spa
- Body Scrub
- Aromatherapy
- Hot Stone
- Body Mask
- Couple Spa
- Wellness Package

### Resource

- Therapist
- Sauna Room
- Steam Room
- Treatment Room
- Couple Room

### Model booking

Bisa:

**Staff + Room**

atau:

**Room only**

Contoh sauna:

- duration 60 menit
- room required
- staff optional
- capacity 4 orang.

---

## 113.5 Pijat / Refleksi

### Layanan

- Full Body Massage
- Back Massage
- Foot Reflexology
- Head Massage
- Couple Massage
- Aromatherapy Massage

### Resource

- Therapist
- Massage Room
- Massage Bed

### Contoh

Foot Reflexology:

- duration 60 menit
- therapist 1
- chair/bed 1
- room 1
- capacity 1.

---

## 113.6 Lash / Brow / Makeup Studio

Tambahan template yang direkomendasikan.

### Layanan

- Eyelash Extension
- Lash Lift
- Brow Treatment
- Brow Shaping
- Brow Lamination
- Makeup
- Event Makeup
- Bridal Makeup

### Resource

- Lash Artist
- Brow Artist
- Makeup Artist
- Treatment Bed
- Makeup Station

---

## 113.7 Beauty Clinic / Aesthetic Care

Template opsional untuk bisnis perawatan kecantikan yang membutuhkan appointment.

### Layanan

- Consultation
- Facial Treatment
- Skin Treatment
- Laser/Device Treatment
- Body Treatment

### Resource

- Therapist/Beautician
- Doctor jika bisnis memang memerlukannya
- Treatment Room
- Device

Catatan:

Template hanya menyediakan scheduling/booking. Domain klinis, diagnosis, resep, dan data medis sensitif bukan bagian dari generic booking engine MVP.

---

# 114. TEMPLATE GROUP — HEALTH, THERAPY & WELLNESS

## 114.1 Terapi Fisik / Fisioterapi

### Layanan

- Initial Assessment
- Physiotherapy
- Exercise Session
- Manual Therapy
- Follow-up Session

### Resource

- Physiotherapist
- Treatment Room
- Therapy Bed
- Equipment

### Contoh

Initial Assessment:

- 60 menit
- therapist required 1
- room required 1

Follow-up:

- 45 menit
- therapist required 1
- room required 1

---

## 114.2 Psikolog / Konseling

### Layanan

- Initial Consultation
- Individual Session
- Couple Session
- Family Session
- Follow-up

### Resource

- Psychologist/Counselor
- Consultation Room
- Online Meeting Room jika tersedia

### Capacity

Default 1 appointment, tetapi dapat diatur menjadi:

- individual;
- couple;
- family;
- group.

### Privacy

Booking page hanya meminta data minimum. Catatan sesi tidak menjadi bagian dari public booking data.

---

## 114.3 Wellness / Yoga / Meditation / Therapy Studio

### Layanan

- Yoga class
- Meditation
- Breathwork
- Private session
- Group session

### Resource

- Instructor
- Studio
- Class Room

### Capacity

Misalnya:

`20 peserta / class`

Slot menjadi penuh ketika kapasitas terpenuhi.

---

# 115. TEMPLATE GROUP — BODY ART

## 115.1 Tattoo Studio

### Layanan

- Consultation
- Small Tattoo
- Medium Tattoo
- Large Tattoo
- Custom Tattoo
- Touch-up

### Resource

- Tattoo Artist
- Tattoo Station
- Private Room jika ada

### Service duration

Tattoo tidak selalu memiliki durasi tetap.

Sistem harus mendukung:

- fixed duration;
- estimated duration;
- custom duration;
- consultation required.

Contoh:

Small Tattoo:

- 60 menit

Large Tattoo:

- 180–300 menit

Owner dapat mengatur duration range jika proses membutuhkan konsultasi.

### Workflow

```text
Booking Request
↓
Consultation
↓
Price/Design Approval
↓
Deposit
↓
Confirmed
↓
Appointment
↓
In Service
↓
Completed
```

---

## 115.2 Piercing Studio

### Layanan

- Ear Piercing
- Nose Piercing
- Body Piercing
- Jewelry Replacement
- Consultation

### Resource

- Piercer
- Piercing Station
- Private Room

### Contoh

Piercing:

- duration 30 menit
- 1 piercer
- 1 station.

---

# 116. TEMPLATE GROUP — FITNESS & SPORTS

## 116.1 GYM

Gym dapat memakai dua model:

### Model A — Visit/Slot

Customer booking jadwal kunjungan.

### Model B — Class

Customer booking kelas.

### Resource

- Trainer
- Studio
- Class Room
- Equipment/Zone

### Layanan

- Gym Session
- Personal Training
- Group Class
- Consultation
- Body Check

---

## 116.2 Personal Trainer

### Service

- PT 1-on-1
- PT 2-person
- Assessment
- Training Package

### Resource

- Trainer
- Gym Zone

### Duration

30 / 45 / 60 / 90 menit.

---

## 116.3 Sports Court

Template harus mendukung:

- Badminton
- Futsal
- Basketball
- Tennis
- Padel
- Volleyball
- Table Tennis

### Resource

- Court 1
- Court 2
- Court 3

### Booking model

```text
Customer
↓
Sport
↓
Court
↓
Date
↓
Time
↓
Duration
↓
Payment
```

### Capacity

Bisa berdasarkan:

- court;
- jumlah pemain;
- participant limit.

---

# 117. TEMPLATE GROUP — PET & ANIMAL BUSINESS

## 117.1 Pet Grooming

### Layanan

- Bath
- Grooming
- Haircut
- Nail Trim
- Ear Cleaning
- Teeth Cleaning
- Full Grooming Package

### Resource

- Groomer
- Grooming Station
- Bath Station
- Cage/Waiting Area

### Form customer

- Pet Name
- Pet Type
- Breed
- Weight
- Owner
- WhatsApp
- Notes
- Special Requirements

### Duration

Dapat berbeda berdasarkan:

- jenis hewan;
- ukuran;
- berat;
- service.

---

## 117.2 Pet Boarding / Pet Hotel

### Resource

- Room/Cage
- Kennel
- Staff

### Booking

Memakai date range:

```text
Check In
↓
Check Out
```

Availability dihitung sepanjang rentang tanggal.

---

## 117.3 Pet Training

### Layanan

- Basic Training
- Behavioral Session
- Private Training
- Group Class

### Resource

- Trainer
- Training Area

---

## 117.4 Pet Shop + Pet Food

Jika bisnis menjual produk dan juga menerima booking:

### Product

- Pet Food
- Accessories
- Medicine/health products sesuai legal scope bisnis
- Grooming supplies

### Booking

- Grooming;
- Consultation;
- Pickup;
- Delivery slot.

Booking engine dapat berintegrasi dengan inventory, tetapi inventory commerce penuh dapat tetap menjadi modul terpisah.

---

## 117.5 Veterinary / Pet Clinic

Template dapat mendukung appointment.

Resource:

- Veterinarian
- Consultation Room
- Treatment Room

Domain rekam medis tidak menjadi bagian generic booking engine MVP.

---

# 118. TEMPLATE GROUP — AUTOMOTIVE

## 118.1 Bengkel Mobil

### Layanan

- Service Berkala
- Ganti Oli
- Tune Up
- Brake Service
- AC Service
- Battery Service
- Inspection
- Engine Service
- Electrical Service
- Tire Service
- Detailing
- Body Repair

### Resource

- Mechanic
- Service Bay
- Lift
- Inspection Area
- Equipment

### Contoh

Service Berkala:

- estimated duration: 120 menit
- mechanic: 1–2
- bay: 1
- lift: optional

### Form booking

- nama;
- WhatsApp;
- nomor kendaraan;
- tipe kendaraan;
- service;
- tanggal;
- jam;
- catatan.

---

## 118.2 Bengkel Motor

### Layanan

- Ganti Oli
- Service Ringan
- Service Berkala
- CVT Service
- Brake Service
- Tire Service
- Battery
- Engine
- Electrical

### Resource

- Mechanic
- Service Bay

---

## 118.3 Ban / Spooring / Balancing

### Layanan

- Tire Replacement
- Tire Repair
- Wheel Balancing
- Wheel Alignment
- Nitrogen

### Resource

- Technician
- Bay
- Machine

Durasi dapat berbeda menurut service.

---

## 118.4 Body Repair / Cat

### Booking model

Biasanya bukan slot sederhana.

Dapat menggunakan:

```text
Request
↓
Inspection
↓
Estimate
↓
Approval
↓
Schedule
↓
Repair
↓
QC
↓
Completed
```

Ini menjadi contoh penting penggunaan workflow custom.

---

# 119. TEMPLATE GROUP — CAR WASH & MOTOR WASH

## 119.1 Cuci Mobil

Layanan:

- Basic Wash
- Wash + Vacuum
- Wax
- Interior Cleaning
- Engine Cleaning
- Detailing
- Ceramic/Coating

Resource:

- Wash Bay
- Detailing Bay
- Worker/Team

## 119.2 Cuci Motor

Layanan:

- Basic Wash
- Premium Wash
- Detail
- Wax
- Coating

### Model duration

Contoh:

Basic Wash:

- 30 menit
- 1 bay
- 1–2 worker.

Detailing:

- 180 menit
- 1 detailing bay
- 2 worker.

---

# 120. TEMPLATE GROUP — GENERAL SERVICE BUSINESS

Tambahkan template generic berikut:

- Photography Studio
- Video Studio
- Event Venue
- Meeting Room
- Co-working Room
- Consultant
- Legal Consultant
- Accounting/Tax Consultant
- Tutor / Les Privat
- Course / Training Center
- Music School
- Driving School
- Rental Equipment
- Car Rental
- Motorcycle Rental
- Villa
- Homestay
- Meeting Space
- Cleaning Service
- Home Service
- Appliance Repair
- Computer Repair
- Phone Repair

Semua menggunakan engine yang sama.

---

# 121. TEMPLATE MATRIX

Setiap template memiliki profil resource berbeda.

| Template | Service | Staff | Room/Resource | Capacity | Inventory | Date Range |
|---|---|---|---|---|---|---|
| Salon | Ya | Ya | Chair/Room | 1+ | Opsional | Tidak |
| Barber | Ya | Ya | Chair | 1 | Opsional | Tidak |
| Nail | Ya | Ya | Table/Chair | 1 | Ya | Tidak |
| Spa | Ya | Ya | Room | 1–2 | Opsional | Tidak |
| Sauna | Ya | Opsional | Sauna Room | 1–n | Tidak | Tidak |
| Massage | Ya | Ya | Room/Bed | 1–2 | Opsional | Tidak |
| Gym | Ya | Opsional | Zone/Class | 1–n | Opsional | Tidak |
| Physio | Ya | Ya | Room | 1 | Opsional | Tidak |
| Psychology | Ya | Ya | Room/Online | 1–n | Tidak | Tidak |
| Tattoo | Ya | Ya | Station | 1 | Opsional | Tidak |
| Piercing | Ya | Ya | Station | 1 | Opsional | Tidak |
| Pet Grooming | Ya | Ya | Station | 1+ | Ya | Tidak |
| Pet Hotel | Ya | Ya | Cage/Room | 1+ | Opsional | Ya |
| Bengkel | Ya | Ya | Bay/Lift | 1+ | Ya | Tidak |
| Car Wash | Ya | Ya | Bay | 1+ | Ya | Tidak |
| Rental | Ya | Opsional | Vehicle | 1 | Opsional | Ya |
| Hotel/Villa | Ya | Opsional | Room | 1–n | Opsional | Ya |
| Sports Court | Ya | Opsional | Court | 1–n | Tidak | Tidak |
| Course | Ya | Ya | Class Room | 1–n | Opsional | Tidak |

---

# 122. SERVICE CONFIGURATION ENGINE

Setiap service harus memiliki konfigurasi yang lengkap.

```text
SERVICE
├── Basic Info
├── Pricing
├── Duration
├── Buffer
├── Capacity
├── Staff Rules
├── Resource Rules
├── Room Rules
├── Schedule Rules
├── Booking Rules
├── Payment Rules
├── Form Rules
├── Notification Rules
├── Inventory Rules
└── Workflow
```

---

# 123. DURATION MODEL

Duration tidak boleh hanya integer sederhana.

Support:

- fixed duration;
- minimum duration;
- maximum duration;
- variable duration;
- custom duration;
- duration berdasarkan quantity;
- duration berdasarkan package;
- duration berdasarkan resource.

## Contoh 1 — Fixed

Haircut:

`45 menit`

## Contoh 2 — Quantity

Carpet Cleaning:

`30 menit × jumlah ruangan`

## Contoh 3 — Size

Pet Grooming:

- Small: 60 menit
- Medium: 90 menit
- Large: 120 menit

## Contoh 4 — Variable

Tattoo:

`60–240 menit`

Customer request masuk terlebih dahulu lalu owner menetapkan slot aktual.

---

# 124. BUFFER TIME ENGINE

Service dapat memiliki:

- preparation buffer;
- cleanup buffer;
- transition buffer.

Contoh car wash:

Service = 45 menit  
Cleanup = 15 menit

Resource occupied:

`60 menit`

Contoh room treatment:

Service = 60 menit  
Preparation = 10 menit  
Cleanup = 15 menit

Total resource occupation:

`85 menit`

---

# 125. STAFF ASSIGNMENT MODES

Setiap service dapat memilih:

## Mode 1 — Customer Selects Staff

Customer memilih therapist/barber.

## Mode 2 — System Assigns

Sistem otomatis memilih staff yang tersedia.

## Mode 3 — Owner Assigns

Booking masuk dahulu, owner menetapkan staff.

## Mode 4 — Staff Pool

Sistem memilih dari group staff.

Contoh:

```text
Service: Massage 60 Menit

Staff Pool:
- Therapist A
- Therapist B
- Therapist C
```

Jika A penuh, sistem mencari B/C.

---

# 126. RESOURCE ASSIGNMENT MODES

Sama seperti staff:

- customer select;
- system assign;
- owner assign;
- pool.

---

# 127. REQUIRED VS OPTIONAL RESOURCE

Setiap service dapat menentukan:

### Required

Harus tersedia.

Contoh:

Massage → 1 Room + 1 Therapist

### Optional

Jika tersedia, digunakan.

Contoh:

Consultation → Therapist required, Room optional karena dapat online.

---

# 128. MULTI-RESOURCE BOOKING

Satu booking bisa membutuhkan banyak resource sekaligus.

Contoh:

```text
Facial Premium
+
Therapist A
+
Room 2
+
Machine RF
```

Semua harus tersedia pada waktu yang sama.

---

# 129. PARALLEL RESOURCE BOOKING

Sistem juga harus mendukung service yang memakai resource secara paralel.

Contoh:

Couple Massage:

```text
Therapist A ───────┐
                   ├── 90 menit
Therapist B ───────┘

Room Couple 1 ─────── 90 menit
```

Booking hanya boleh berhasil jika seluruh resource berhasil di-reserve.

---

# 130. SEQUENTIAL SERVICE

Satu booking dapat berisi beberapa tahap berurutan.

Contoh salon:

```text
Wash 15m
↓
Coloring 120m
↓
Wash 15m
↓
Styling 30m
```

Total:

`180 menit`

Resource dapat berbeda pada setiap tahap.

---

# 131. MULTI-SERVICE BOOKING

Customer dapat memilih lebih dari satu service.

Contoh:

```text
Haircut     45m
Wash        15m
Styling     30m
----------------
Total      90m
```

Sistem menghitung availability berdasarkan gabungan durasi dan resource.

---

# 132. COMPOSITE / PACKAGE SERVICE

Owner dapat membuat paket:

**Premium Hair Package**

yang berisi:

- Wash;
- Haircut;
- Treatment;
- Styling.

Customer melihat satu paket.

Backend menyimpan komponen secara terstruktur.

---

# 133. SERVICE DEPENDENCY

Service dapat memiliki dependency.

Contoh:

```text
Consultation
↓
Approval
↓
Main Service
```

Booking main service dapat ditolak sebelum consultation selesai.

Ini penting untuk bisnis seperti:

- tattoo;
- body repair;
- rental tertentu;
- consultation-based services.

---

# 134. CAPACITY MODEL

Capacity dapat berlaku di:

- service;
- resource;
- room;
- class;
- business;
- slot.

Contoh:

Yoga Class:

`capacity = 20`

Room:

`capacity = 5`

Couple Massage:

`capacity = 2`

---

# 135. GROUP BOOKING

Customer dapat booking untuk beberapa peserta.

Field:

- number of people;
- participant list optional;
- main contact.

Contoh:

Team badminton:

`4 participants`

---

# 136. QUEUE / WAITLIST

Jika capacity penuh:

```text
FULL
↓
Join Waitlist
↓
Slot Available
↓
Notification
↓
Customer Confirm
```

Waitlist tidak otomatis menjadi booking confirmed tanpa rule.

---

# 137. AVAILABILITY CALCULATION — FINAL MODEL

Availability harus dihitung dari semua faktor:

```text
Business Hours
        +
Holiday / Blackout
        +
Service Schedule
        +
Staff Schedule
        +
Resource Schedule
        +
Room Schedule
        +
Existing Booking
        +
Buffer
        +
Capacity
        +
Inventory Rule jika diperlukan
        +
Subscription/Plan Limit
        =
FINAL AVAILABILITY
```

Backend menjadi source of truth.

---

# 138. SCHEDULING CONFLICT MATRIX

| Konflik | Hasil |
|---|---|
| Staff overlap | Tolak |
| Required room overlap | Tolak |
| Required resource overlap | Tolak |
| Capacity penuh | Tolak / waitlist |
| Business closed | Tolak |
| Blackout date | Tolak |
| Staff unavailable | Tolak |
| Resource maintenance | Tolak |
| Subscription limit | Tolak |
| Optional resource unavailable | Cari alternatif sesuai rule |

---

# 139. RESERVATION HOLD

Untuk mencegah customer memilih slot sementara membayar:

Sistem dapat membuat temporary hold.

Contoh:

```text
Slot selected
↓
HOLD 10 minutes
↓
Payment
↓
Confirmed
```

Jika payment tidak selesai:

```text
HOLD EXPIRED
↓
Slot released
```

Hold harus memiliki expiration dan idempotency.

---

# 140. BOOKING STATUS MODEL

System category:

```text
DRAFT
PENDING
CONFIRMED
CHECKED_IN
IN_PROGRESS
COMPLETED
CANCELLED
NO_SHOW
EXPIRED
```

Business custom labels boleh berbeda.

Contoh:

System state:

`IN_PROGRESS`

Label owner:

`Sedang Dikerjakan`

---

# 141. BOOKING STATE TRANSITION

Tidak semua status boleh berpindah bebas.

Contoh:

```text
PENDING
 ├── CONFIRMED
 ├── CANCELLED
 └── EXPIRED

CONFIRMED
 ├── CHECKED_IN
 ├── NO_SHOW
 └── CANCELLED

CHECKED_IN
 ├── IN_PROGRESS
 └── CANCELLED

IN_PROGRESS
 └── COMPLETED
```

Custom workflow mengatur tampilan/alur, tetapi domain service tetap menjaga transisi valid.

> Catatan v1.2: `RESCHEDULED` adalah event, bukan status (booking tetap `CONFIRMED`). Tabel transisi formal ada di bagian 213.

---

# 142. BOOKING RESERVATION LIFECYCLE

```text
Availability Check
↓
Temporary Hold (optional)
↓
Create Booking
↓
Assign Staff/Resource
↓
Payment Rule
↓
Confirm
↓
Workflow
↓
Reminder
↓
Check In
↓
Service
↓
Complete
↓
Follow-up
```

---

# 143. BOOKING DATA — EXTENDED

Tambahkan:

```text
booking_id
tenant_id
business_id
customer_id
service_id
service_snapshot
staff_id
resource_ids[]
room_ids[]
start_at_utc
end_at_utc
business_timezone
duration_minutes
buffer_before_minutes
buffer_after_minutes
capacity
quantity
price_snapshot
discount_snapshot
payment_requirement
payment_status
booking_status
workflow_version_id
source
notes
custom_fields
created_at
updated_at
cancelled_at
completed_at
idempotency_key
```

Snapshot service/harga diperlukan agar perubahan katalog setelah booking tidak merusak histori booking lama.

---

# 144. SERVICE SNAPSHOT

Ketika booking dibuat, simpan snapshot minimal:

- service name;
- duration;
- price;
- resource requirement;
- policy reference.

Tujuan:

> Booking lama tetap dapat direkonstruksi walaupun owner mengubah service di kemudian hari.

---

# 145. RESOURCE BOOKING RECORD

Simpan assignment aktual:

```text
booking_resource
├── booking_id
├── resource_id
├── role
├── required
├── start_at
├── end_at
├── assignment_method
└── status
```

Hal ini penting untuk mengetahui:

> siapa/apa yang sebenarnya dipakai booking tersebut.

---

# 146. BUSINESS HOURS

Owner dapat menentukan:

- opening;
- closing;
- break;
- holiday;
- special opening;
- special closing.

Contoh:

Senin–Jumat:

`09:00–20:00`

Break:

`13:00–14:00`

Sabtu:

`09:00–18:00`

Minggu:

`Closed`

---

# 147. STAFF SCHEDULE

Staff memiliki jadwal sendiri.

Contoh:

Therapist A:

Senin–Jumat:

`10:00–18:00`

Therapist B:

Selasa–Sabtu:

`12:00–20:00`

Availability service mengikuti intersection:

```text
Business Open
∩
Staff Available
∩
Resource Available
```

---

# 148. RESOURCE SCHEDULE

Contoh:

Room 1:

`09:00–21:00`

Room 2:

`10:00–18:00`

System tidak menawarkan booking Room 2 di luar jam tersebut.

---

# 149. SPECIAL DATE

Support:

- holiday;
- leave;
- maintenance;
- event;
- private booking;
- special schedule.

Contoh:

Therapist A cuti:

`15 Oktober 2026`

Maka slot A otomatis unavailable.

---

# 150. SERVICE-SPECIFIC SCHEDULE

Service juga boleh punya schedule sendiri.

Contoh:

Sauna:

`10:00–22:00`

Consultation dokter:

`17:00–20:00`

Walaupun business open:

`09:00–22:00`.

---

# 151. CUSTOMER BOOKING RULES

Owner dapat mengatur:

- minimum advance time;
- maximum advance booking;
- same-day booking;
- cancellation deadline;
- reschedule deadline;
- maximum reschedule count;
- deposit requirement;
- customer confirmation;
- waitlist;
- required fields.

Contoh:

> Booking minimal 2 jam sebelum jadwal.

---

# 152. STAFF BREAK & BUFFER

Break staff tidak boleh dianggap hanya sebagai visual.

Break harus masuk calculation.

Contoh:

Staff:

`09:00–17:00`

Break:

`12:00–13:00`

Booking tidak boleh mengambil slot:

`12:00–13:00`.

---

# 153. BLOCK TIME

Owner/staff dapat membuat manual block:

- meeting;
- cleaning;
- maintenance;
- private event;
- break;
- personal time.

Block time diperlakukan sebagai unavailable resource.

---

# 154. OVERBOOKING POLICY

Default:

> **Overbooking OFF**

Optional untuk bisnis tertentu:

> **Overbooking ON**

Dengan maximum count.

Contoh:

Class capacity 20:

Maximum overbooking:

`+2`

Customer ke-21/22 tetap dapat booking.

---

# 155. WAITING TIME / QUEUE MODEL

Untuk bisnis yang tidak memakai exact appointment:

- salon walk-in;
- car wash;
- workshop.

Sistem dapat memiliki:

```text
Queue
+
Estimated Start
+
Estimated Waiting Time
```

Ini dapat menjadi fase berikutnya setelah appointment engine stabil.

---

# 156. WALK-IN BOOKING

Owner dapat membuat booking manual dari dashboard.

Field dapat disederhanakan:

- Customer;
- Service;
- Staff;
- Time;
- Payment.

Walk-in harus tetap masuk booking database dengan:

`source = MANUAL / WALK_IN`

---

# 157. ADMIN BOOKING

Owner/member dapat membuat booking untuk customer melalui:

- Calendar;
- Kanban;
- Quick Booking.

Quick Booking harus menggunakan availability engine yang sama dengan public booking.

---

# 158. QUICK BOOKING UX

```text
+ New Booking

Customer
[ Cari / Tambah ]

Service
[ Pilih ]

Staff
[ Auto / Pilih ]

Date
[ 10 Oct ]

Time
[ 14:00 ]

Duration
[ 60 min ]

Payment
[ Unpaid ]

[ Create Booking ]
```

---

# 159. QUICK BOOKING — AUTO ASSIGN

Jika staff/resource diset Auto:

```text
Service
↓
Available Staff
↓
Available Resource
↓
Best Match
```

Prioritas dapat berdasarkan:

1. availability;
2. skill/service compatibility;
3. owner priority;
4. workload balancing.

---

# 160. SKILL / SERVICE COMPATIBILITY

Staff dapat memiliki skill.

Contoh:

Therapist A:

- Massage
- Reflexology

Therapist B:

- Massage
- Hot Stone

Therapist C:

- Facial

Service:

`Hot Stone`

System tidak boleh assign Therapist C.

---

# 161. STAFF WORKLOAD

Dashboard owner dapat menampilkan:

- jumlah booking;
- jam kerja terpakai;
- utilization;
- upcoming appointments.

Tujuan:

> Owner dapat melihat apakah resource overload atau underutilized.

---

# 162. RESOURCE UTILIZATION

Formula dasar:

`Booked Resource Time / Available Resource Time × 100%`

Contoh:

Room tersedia 10 jam.

Dipakai booking 7 jam.

Utilization:

`70%`

---

# 163. SERVICE UTILIZATION

Owner dapat melihat layanan:

- paling sering dibooking;
- durasi total;
- revenue;
- cancellation;
- repeat booking.

---

# 164. BOOKING PACKAGE WITH MULTI-RESOURCE

Contoh:

**Couple Spa Package**

Membutuhkan:

- Therapist A;
- Therapist B;
- Couple Room;
- 90 menit.

Semua resource harus tersedia.

---

# 165. CROSS-SERVICE RESOURCE CONFLICT

Contoh:

Booking A:

`10:00–11:30`

Room 1.

Booking B:

`11:00–12:00`

Room 1.

B harus ditolak walaupun service berbeda.

Conflict berdasarkan resource, bukan hanya service.

---

# 166. CROSS-BUSINESS ISOLATION

Resource tidak boleh dipakai lintas tenant.

Bahkan jika ID sama secara numerik, tenant scope harus berbeda.

---

# 167. PUBLIC BOOKING URL

Struktur logical:

```text
/{business-slug}
```

Landing:

```text
/{business-slug}/
```

Booking:

```text
/{business-slug}/booking
```

Success:

```text
/{business-slug}/booking/success
```

Manage booking:

```text
/{business-slug}/booking/manage/{secure-token}
```

Token harus signed/unguessable.

---

# 168. TEMPLATE INSTALLATION

Saat owner memilih template:

```text
Select Template
↓
Preview
↓
Install
↓
Generate Services
↓
Generate Resources
↓
Generate Workflow
↓
Generate Form
↓
Generate Landing Page
↓
Owner Reviews
↓
Publish
```

Template tidak boleh langsung membuat data aktif tanpa review.

---

# 169. TEMPLATE CUSTOMIZATION WIZARD

Contoh:

Owner memilih:

**Barbershop**

System bertanya:

1. Berapa barber?
2. Berapa chair?
3. Jam buka?
4. Layanan apa?
5. Apakah customer memilih barber?
6. Perlu DP?
7. WhatsApp reminder?
8. Apakah walk-in digunakan?

Kemudian template dikonfigurasi otomatis.

---

# 170. TEMPLATE SEED DATA SAFETY

Seed template harus:

- memiliki origin `SYSTEM_TEMPLATE`;
- dapat diedit;
- dapat dihapus jika belum direferensikan;
- tidak membagi data antar tenant;
- tidak membawa customer/payment asli;
- versioned.

---

# 171. BUSINESS TYPE CAN CHANGE

Owner dapat mengubah/menambah tipe bisnis.

Contoh:

Awalnya:

`Pet Grooming`

kemudian menambahkan:

`Pet Shop`

System menambahkan module capability tanpa menghancurkan booking lama.

---

# 172. HYBRID BUSINESS

Satu bisnis boleh memiliki beberapa vertical.

Contoh:

**Pet Care Center**

```text
Pet Grooming
Pet Hotel
Pet Food Store
Pet Training
Veterinary Appointment
```

Semuanya menggunakan satu tenant.

Contoh lain:

**Auto Care Center**

```text
Car Wash
Detailing
Workshop
Tire Service
Coating
```

Ini salah satu alasan engine harus generic.

---

# 173. BUSINESS MODULE SWITCH

Owner dapat mengaktifkan module:

- Booking
- Service
- Product
- Resource
- Staff
- Room
- Inventory
- Payment
- CRM
- Website
- Workflow

UI hanya menampilkan module yang aktif.

---

# 174. RESOURCE TYPES — EXTENSIBLE

Jangan hardcode hanya:

`staff / room / vehicle`.

Gunakan resource type configurable.

Contoh:

```text
STAFF
ROOM
CHAIR
BED
COURT
VEHICLE
BAY
STATION
MACHINE
TABLE
CAGE
UNIT
CUSTOM
```

---

# 175. RESOURCE GROUP

Resource dapat dikelompokkan.

Contoh:

```text
Barber Chairs
├── Chair 1
├── Chair 2
├── Chair 3
```

Atau:

```text
Treatment Rooms
├── Room 1
├── Room 2
```

Ini memudahkan filtering dan scheduling.

---

# 176. RESOURCE POOL

Service dapat memakai pool:

`Any Available Treatment Room`

System memilih salah satu room yang kompatibel.

---

# 177. CUSTOMER SELECTABLE RESOURCE

Owner dapat mengizinkan customer memilih:

- therapist;
- barber;
- court;
- vehicle;
- room.

Tetapi tidak semua resource harus exposed.

Contoh:

Owner dapat menyembunyikan:

`internal machine`

dari customer.

---

# 178. INTERNAL VS PUBLIC RESOURCE

Field:

`visibility = PUBLIC | INTERNAL`

Public:

- Barber A
- Court 1
- Room 2

Internal:

- sterilization machine
- internal bay
- hidden equipment.

---

# 179. BOOKING FORM RULES PER TEMPLATE

Setiap template membuat default form tetapi owner dapat mengubahnya.

Contoh:

### Barber

Nama + WhatsApp.

### Rental

Nama + WhatsApp + Email + pickup/return.

### Pet Grooming

Owner + WhatsApp + Pet Name + Pet Type + Weight.

### Bengkel

Nama + WhatsApp + Plate Number + Vehicle Type.

### Sports

Nama + WhatsApp + Number of Players.

---

# 180. BOOKING FORM — AUTO DATA

Jika customer kembali:

System dapat mengenali customer berdasarkan:

- verified WhatsApp;
- email.

Field dapat dipre-fill bila customer memilih melanjutkan melalui secure booking/manage link.

MVP tidak boleh membutuhkan account/password.

---

# 181. CUSTOMER REPEAT BOOKING

Confirmation page harus memiliki:

> **Booking Lagi**

yang mempertahankan:

- customer;
- service;
- preferred resource jika masih tersedia.

---

# 182. SMART REMINDER PER TEMPLATE

Default recommendation:

### Barber

H-1

### Spa

H-1 + H-2 jam

### Rental

H-1 + pickup reminder

### Hotel/Villa

H-1 + check-in reminder

### Course

H-1

### Bengkel

H-1

Owner dapat mengubah semuanya.

---

# 183. NOTIFICATION TEMPLATE VARIABLE

Template dapat menggunakan variable:

```text
{{customer.name}}
{{business.name}}
{{booking.code}}
{{service.name}}
{{booking.date}}
{{booking.time}}
{{resource.name}}
{{staff.name}}
{{booking.total}}
{{payment.status}}
{{manage_booking_url}}
```

---

# 184. LANDING PAGE TEMPLATE PER BUSINESS

Setiap template mempunyai struktur landing berbeda.

## Salon

Hero → Services → Price → Gallery → Staff → Testimonials → Booking CTA

## Barber

Hero → Services → Barber → Price → Location → Booking

## Spa

Hero → Treatments → Packages → Room → Price → Booking

## Rental

Hero → Fleet → Pricing → Requirements → FAQ → Booking

## Pet

Hero → Services → Pet Care → Package → Gallery → Booking

## Automotive

Hero → Services → Vehicle Types → Workshop → Packages → Booking

---

# 185. TEMPLATE PREVIEW

Super Admin wajib dapat melihat preview template:

- Landing;
- Booking;
- Owner dashboard;
- Workflow;
- Form.

---

# 186. TEMPLATE VERSIONING

Template system:

```text
Barbershop Template
v1.0
v1.1
v2.0
```

Tenant yang sudah install template tidak otomatis berubah ketika Super Admin mengubah template global.

Update template harus berupa:

> available update + migration preview

bukan overwrite otomatis.

---

# 187. PRODUCT REQUIREMENT — GENERIC BOOKING ENGINE

**REQ-BKG-001**

Sistem harus menyediakan service yang dapat memiliki durasi.

**REQ-BKG-002**

Durasi harus menjadi sumber perhitungan end time.

**REQ-BKG-003**

Sistem harus mendukung buffer sebelum/sesudah service.

**REQ-BKG-004**

Sistem harus mendukung staff assignment.

**REQ-BKG-005**

Sistem harus mendukung resource assignment.

**REQ-BKG-006**

Sistem harus mendukung room/area assignment.

**REQ-BKG-007**

Sistem harus dapat menghitung availability dari semua constraint.

**REQ-BKG-008**

Sistem harus mencegah overlapping required resources.

**REQ-BKG-009**

Sistem harus mendukung custom duration bila business rule mengizinkan.

**REQ-BKG-010**

Sistem harus mendukung multi-service booking.

**REQ-BKG-011**

Sistem harus mendukung sequential service.

**REQ-BKG-012**

Sistem harus mendukung parallel resource requirement.

**REQ-BKG-013**

Sistem harus mendukung capacity.

**REQ-BKG-014**

Sistem harus mendukung walk-in/manual booking.

**REQ-BKG-015**

Sistem harus mendukung reschedule/cancel berdasarkan policy.

**REQ-BKG-016**

Sistem harus mencatat snapshot service pada booking.

**REQ-BKG-017**

Sistem harus mendukung secure customer booking tanpa account.

**REQ-BKG-018**

Sistem harus mendukung idempotency saat create booking.

**REQ-BKG-019**

Sistem harus mendukung temporary reservation hold jika payment diperlukan.

**REQ-BKG-020**

Sistem harus memiliki satu availability source of truth di backend.

---

# 188. PRODUCT REQUIREMENT — BUSINESS TEMPLATES

**REQ-TPL-001**

Super Admin dapat membuat dan mengelola template bisnis.

**REQ-TPL-002**

Owner dapat memilih template saat onboarding.

**REQ-TPL-003**

Template dapat membuat konfigurasi awal.

**REQ-TPL-004**

Owner wajib dapat preview sebelum publish.

**REQ-TPL-005**

Template tenant tidak boleh berbagi data runtime dengan template tenant lain.

**REQ-TPL-006**

Template harus versioned.

**REQ-TPL-007**

Template global update tidak boleh overwrite konfigurasi tenant existing tanpa explicit migration.

---

# 189. PRODUCT REQUIREMENT — RESOURCE

**REQ-RSC-001**

Resource type harus extensible.

**REQ-RSC-002**

Resource dapat memiliki schedule.

**REQ-RSC-003**

Resource dapat diblok.

**REQ-RSC-004**

Resource dapat memiliki capacity.

**REQ-RSC-005**

Service dapat membutuhkan satu atau banyak resource.

**REQ-RSC-006**

Resource assignment harus tercatat pada booking.

**REQ-RSC-007**

Public/internal resource visibility harus configurable.

---

# 190. PRODUCT REQUIREMENT — STAFF

**REQ-STF-001**

Staff dapat memiliki jam kerja.

**REQ-STF-002**

Staff dapat memiliki leave/block.

**REQ-STF-003**

Staff dapat memiliki skill.

**REQ-STF-004**

Service dapat dibatasi ke staff tertentu.

**REQ-STF-005**

System dapat auto-assign staff.

**REQ-STF-006**

Customer dapat memilih staff jika owner mengaktifkan.

---

# 191. FINAL TEMPLATE CATALOG — RECOMMENDED INITIAL RELEASE

Untuk launch awal, minimal sediakan:

### Beauty

1. Salon Wanita
2. Barbershop
3. Nail Studio
4. Spa & Sauna
5. Massage / Reflexology
6. Lash & Brow
7. Makeup Studio
8. Beauty/Aesthetic Appointment

### Health & Wellness

9. Physiotherapy
10. Psychology / Counseling
11. Yoga / Wellness
12. Personal Trainer
13. Gym Class

### Body Art

14. Tattoo
15. Piercing

### Pet

16. Pet Grooming
17. Pet Hotel
18. Pet Training
19. Pet Shop + Grooming
20. Pet Clinic Appointment

### Automotive

21. Bengkel Mobil
22. Bengkel Motor
23. Tire / Alignment Service
24. Body Repair
25. Car Wash
26. Motorcycle Wash
27. Detailing / Coating

### Rental & Hospitality

28. Car Rental
29. Motorcycle Rental
30. Equipment Rental
31. Villa
32. Homestay
33. Room Rental

### Sports

34. Badminton Court
35. Futsal Court
36. Tennis Court
37. Padel Court
38. Basketball Court
39. Volleyball Court
40. Table Tennis

### Studio & Education

41. Photography Studio
42. Video Studio
43. Meeting Room
44. Coworking
45. Course / Training
46. Tutor / Les
47. Music School
48. Driving School

### Professional Services

49. Consultant
50. Legal/Accounting Appointment
51. Home Service
52. Repair Service

### Generic

53. Custom Business

Template nomor 53 harus menjadi fallback universal.

---

# 192. CUSTOM BUSINESS TEMPLATE

Jika owner memilih:

> **Custom Business**

wizard meminta:

### A. Apa yang dibooking?

- Service
- Room
- Resource
- Staff
- Vehicle
- Product
- Class
- Capacity
- Combination

### B. Apakah customer memilih staff?

Yes/No.

### C. Apakah customer memilih resource?

Yes/No.

### D. Apakah ada duration?

Yes/No.

### E. Apakah kapasitas?

Yes/No.

### F. Apakah payment?

No payment / Deposit / Full.

### G. Apakah perlu approval?

Yes/No.

Setelah itu sistem membuat initial configuration.

---

# 193. BOOKING CONFIGURATOR

Owner dapat melihat ringkasan sebelum publish:

```text
BUSINESS
Salon Cantik

SERVICES
8

STAFF
5

ROOMS
3

RESOURCES
6

BOOKING TYPE
Appointment

DEFAULT DURATION
30–120 min

CUSTOMER LOGIN
Not required

PAYMENT
Deposit

WORKFLOW
6 statuses

WHATSAPP
Enabled
```

---

# 194. “SHOW ONLY WHAT MATTERS” UX

UI tidak boleh menampilkan semua field sekaligus.

Contoh service creation:

### Basic

- Name
- Price
- Duration

Klik:

> Advanced Settings

muncul:

- staff;
- resource;
- capacity;
- buffer;
- rules;
- inventory;
- workflow.

Tujuannya menjaga UX tetap sederhana.

---

# 195. FINAL BOOKING CREATION LOGIC

Urutan backend yang direkomendasikan:

```text
1. Validate tenant
2. Validate subscription
3. Validate service
4. Validate customer
5. Validate booking policy
6. Calculate duration
7. Resolve start/end time
8. Resolve staff
9. Resolve resource
10. Resolve room
11. Check business hours
12. Check blackout
13. Check staff availability
14. Check resource availability
15. Check room availability
16. Check capacity
17. Start DB transaction
18. Re-check critical conflicts
19. Create reservation/booking
20. Create resource assignments
21. Create payment state
22. Create workflow run/event
23. Commit
24. Queue notifications
25. Return confirmation
```

Critical conflict check harus terjadi di transaction/locking strategy yang benar.

---

# 196. WHY DURATION IS A FIRST-CLASS ENTITY

Duration bukan hanya informasi untuk ditampilkan.

Duration menentukan:

- end time;
- resource occupation;
- staff occupation;
- room occupation;
- availability;
- price jika pricing berbasis durasi;
- reminder;
- schedule;
- utilization;
- conflict detection.

Karena itu duration harus menjadi bagian inti domain Booking dan Service.

---

# 197. DURATION + STAFF EXAMPLE

Service:

**Massage Premium**

Duration:

`120 min`

Therapist A:

Available:

`10:00–18:00`

Customer memilih:

`17:00`

Sistem harus menolak karena appointment selesai:

`19:00`

dan melewati availability therapist.

UI harus menampilkan alasan:

> “Terapis tidak tersedia untuk durasi penuh pada waktu tersebut.”

---

# 198. DURATION + RESOURCE EXAMPLE

Room:

`10:00–18:00`

Service:

`90 min`

Customer memilih:

`17:00`

End:

`18:30`

Slot harus tidak tersedia.

---

# 199. VARIABLE DURATION EXAMPLE

Pet Grooming:

- Small 60 min
- Medium 90 min
- Large 120 min

Customer memilih:

`Large`

Sistem otomatis menggunakan:

`120 min`

tanpa owner menghitung manual.

---

# 200. FINAL PRODUCT POSITIONING UPDATE

AMAN BOOKING bukan:

> “Kalender untuk menerima reservasi.”

AMAN BOOKING adalah:

# **Visual Booking & Workflow Platform**

yang memiliki:

```text
Universal Booking Engine
+
Visual Workflow Builder
+
Resource Scheduling
+
Staff Scheduling
+
Service Duration Engine
+
Capacity Management
+
Customer Booking
+
Landing Page
+
Payment
+
Notification
+
CRM
```

dan kemampuan:

> **Setiap bisnis dapat memiliki cara booking yang berbeda tanpa perlu membuat aplikasi baru.**

---

# BAGIAN II — ARSITEKTUR & KEPUTUSAN TEKNIS (201–209)

---

# 201. KEPUTUSAN ARSITEKTUR FINAL

## 201.1 Stack

| Layer | Pilihan | Catatan penyempurnaan |
|---|---|---|
| Backend | Laravel 12, PHP 8.3+ | Modular per domain (lihat 203) |
| Bridge | Inertia.js v2 | Untuk Owner & Super Admin |
| Frontend app | React 19 + TypeScript | TypeScript wajib agar kontrak data Inertia aman |
| CSS | Tailwind CSS v4 + komponen headless (Radix/Headless UI) | Tanpa UI framework besar |
| Workflow builder | XYFlow (React Flow) | Hanya dimuat di halaman workflow |
| Kanban drag-drop | dnd-kit | Ringan, mendukung touch |
| Kalender owner | Komponen sendiri di atas grid CSS + date-fns; FullCalendar hanya jika waktu mepet | Lihat 204.4 |
| Database | MySQL 8 / MariaDB 10.6+ (InnoDB, utf8mb4) | Wajib InnoDB untuk transaksi dan row lock |
| Auth | Laravel Fortify/Breeze + session | Customer tanpa akun |
| Permission | spatie/laravel-permission (mode teams, team = tenant) | Untuk member dalam tenant |
| Queue | Database queue + cron tiap menit | Upgrade ke Redis saat pindah VPS |
| Cache | File + OPcache | Upgrade ke Redis |
| Build | Vite, build di lokal/CI, upload hasil build | Jangan `npm install` di shared hosting |
| Test | Pest (PHP), Vitest (React), Playwright (E2E) | |
| CI/CD | GitHub Actions: lint, test, build, deploy via SSH/rsync atau zip | |
| Hosting awal | cPanel/shared hosting, PHP 8.3, MySQL | Syarat minimum di 201.4 |

## 201.2 Perubahan penting terhadap rekomendasi awal

1. **Halaman publik (landing + booking customer) tidak dibuat sebagai SPA penuh.**
   Landing page butuh SEO dan load cepat dari Google/Instagram/WhatsApp. Inertia CSR murni kurang ideal untuk SEO.
   - Landing page: **server-rendered Blade** (HTML langsung, tanpa React).
   - Booking flow customer: **React island kecil** (entry Vite terpisah, `public.tsx`) yang hanya memuat kalender, slot, dan form.
   - Owner dan Super Admin: Inertia + React penuh (entry `app.tsx`).
   - Hasil: customer tidak pernah mengunduh kode dashboard, workflow builder, atau report.
2. **Multi-tenancy: satu database, kolom `tenant_id`, global scope Eloquent.**
   Database-per-tenant tidak realistis di shared hosting (limit jumlah DB, migrasi ribet).
3. **Konkurensi booking diselesaikan di database, bukan di UI** (detail 204.1).
4. **Tidak ada WebSocket di fase awal.** Shared hosting tidak cocok untuk proses long-running. Gunakan polling ringan (30-60 detik) di kalender/Kanban owner. Real-time masuk saat pindah VPS.
5. **Uang disimpan sebagai `bigint` dalam rupiah penuh (tanpa desimal); tidak pernah float.**
6. **Semua waktu disimpan UTC pada kolom `DATETIME` (bukan `TIMESTAMP`, agar bebas batas tahun 2038), plus `business_timezone` di booking.** Tampilan dikonversi ke timezone bisnis dengan label zona (WIB/WITA/WIT).
7. **URL publik berbasis path** (`/{slug}`) pada fase awal. Subdomain wildcard dan custom domain ditunda ke Phase 6 karena butuh konfigurasi hosting/SSL.

## 201.3 Diagram arsitektur

```text
Customer (HP)                     Owner / Super Admin (Laptop/Tablet/HP)
     │                                        │
  Blade landing                         Inertia + React (app.tsx)
  + React island (public.tsx)                 │
     │                                        │
     └──────────────┬─────────────────────────┘
                    │  HTTPS
              Laravel (satu codebase)
   ┌────────┬───────┼────────┬──────────┬───────────┐
 Identity Tenant  Booking  Workflow  Notification  Billing ...
                    │
        Availability Service (SATU sumber kebenaran)
                    │
               MySQL (InnoDB)
                    │
   Cron → schedule:run → queue:work --stop-when-empty
```

## 201.4 Syarat minimum hosting (cek sebelum beli/pakai)

- PHP 8.3+, ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd` atau `imagick`.
- Bisa mengatur **document root** ke folder `public/` (atau struktur alternatif di bagian 209).
- Akses **SSH** (sangat disarankan) dan **Cron Job** per menit.
- MySQL dengan InnoDB, user DB punya hak `LOCK TABLES`.
- SSL (Let's Encrypt/AutoSSL).
- Batas `max_execution_time` dan `memory_limit` minimal 256 MB.
- Jika salah satu tidak terpenuhi, langsung pakai VPS kecil (lihat trigger migrasi di bagian 208).

---

# 202. KEPUTUSAN TERBUKA (HARUS DIJAWAB DI PHASE 0)

| # | Keputusan | Rekomendasi default |
|---|---|---|
| 1 | Payment gateway pertama | Midtrans atau Xendit (pilih salah satu; QRIS wajib) |
| 2 | Provider WhatsApp | Fase awal: tautan `wa.me` + integrasi AMAN CHAT; Phase 5: WhatsApp API resmi/provider |
| 3 | Email provider | SMTP hosting untuk dev; Mailgun/Resend/SES untuk produksi |
| 4 | Penyimpanan file | Disk lokal dulu; abstraksi `Storage` Laravel agar bisa pindah ke S3/R2 |
| 5 | Kalender owner | Bangun sendiri (Day/Week/Agenda dulu, Month menyusul) |
| 6 | Model harga subscription | Tentukan plan BASIC/PRO/BUSINESS dan limit final |
| 7 | Nama domain & pola URL publik | `booking.amanbooking.com/{slug}` atau `amanbooking.com/b/{slug}` |
| 8 | Bahasa UI | Indonesia dulu; siapkan i18n (`lang/`) sejak awal |
| 9 | Pajak/PPN pada invoice subscription | Konsultasikan dengan akuntan |
| 10 | Kebijakan retensi data | Tentukan jumlah hari sebelum penghapusan setelah cancel |

---

# 203. STRUKTUR KODE (MODULAR MONOLITH)

```text
app/
├── Domain/
│   ├── Identity/
│   ├── Tenant/
│   ├── Subscription/
│   ├── Business/
│   ├── Catalog/          (service, product, package)
│   ├── Resource/         (resource, staff, room, schedule, block time)
│   ├── Booking/          (booking, status machine, hold, reschedule)
│   ├── Availability/     (SATU-SATUNYA kalkulator slot)
│   ├── Customer/
│   ├── Workflow/         (definition, version, node, runner)
│   ├── Automation/
│   ├── Payment/
│   ├── Inventory/
│   ├── Notification/
│   ├── Landing/
│   ├── Reporting/
│   ├── Audit/
│   └── Integration/
├── Http/
│   ├── Controllers/Owner/
│   ├── Controllers/Admin/
│   ├── Controllers/Public/
│   └── Middleware/       (ResolveTenant, EnsureSubscriptionActive, ...)
└── Support/              (Money, TenantScope, IdempotencyKey, Clock)

resources/js/
├── app.tsx               (owner + admin, Inertia)
├── public.tsx            (booking customer, island ringan)
├── Pages/
├── Components/ui/        (design system)
└── Features/             (booking, calendar, kanban, workflow, ...)
```

Aturan: controller tipis; semua aturan bisnis berada di class `Domain/*` (Action/Service). Frontend tidak menghitung availability, harga, atau status.

---

# 204. DESAIN TEKNIS KRITIS

## 204.1 Anti double-booking (wajib benar)

Pendekatan: **lock baris resource yang terlibat di dalam transaksi, lalu cek overlap, lalu insert.**

```text
BEGIN
  SELECT id FROM resources
   WHERE tenant_id = ? AND id IN (...) ORDER BY id      -- urutan tetap, cegah deadlock
   FOR UPDATE
  cek overlap di booking_allocations (resource_id, start_at, end_at, status aktif)
  jika bentrok → ROLLBACK, kembalikan "slot sudah dipesan"
  INSERT booking, booking_allocations, booking_status_history, audit
COMMIT
```

- Tabel `booking_allocations`: satu baris per resource/staff/room yang dipakai, berisi `start_at`/`end_at` **termasuk buffer**.
- Index: `(tenant_id, resource_id, start_at, end_at)`.
- Status aktif yang menahan slot: `PENDING (hold belum expired)`, `CONFIRMED`, `CHECKED_IN`, `IN_PROGRESS`.
- Untuk kapasitas (kelas/sauna): hitung `SUM(quantity)` dalam slot di bawah lock yang sama.
- Wajib ada **uji konkurensi** (204.7).

## 204.2 Availability service

- Satu class `AvailabilityService` dengan input: tenant, service(s), resource preference, rentang tanggal, quantity.
- Output: daftar slot + alasan jika tidak tersedia (untuk pesan ramah, bagian 197).
- Dipakai oleh: public booking, quick booking owner, drag-drop kalender, API. **Dilarang ada algoritma kedua.**
- Algoritma: irisan interval (business hours ∩ service schedule ∩ staff ∩ resource ∩ room) dikurangi (booking aktif + block time + holiday + break), lalu dipotong sesuai durasi + buffer + step slot.
- Cache hasil per (tenant, service, tanggal) maksimal 30-60 detik; **tidak boleh** dipakai sebagai dasar keputusan saat create booking (create selalu cek ulang di transaksi).

## 204.3 Idempotency

- Tabel `idempotency_keys` (`tenant_id`, `key`, `endpoint`, `request_hash`, `response_snapshot`, `expires_at`), unique `(tenant_id, key, endpoint)`.
- Frontend membuat UUID saat form dibuka; dikirim lewat header `Idempotency-Key`.
- Callback payment: simpan `provider_event_id` dengan unique constraint.

## 204.4 Kalender & Kanban responsif

- Desktop: Week/Day dengan resource column. Tablet: Week/Agenda. HP: Agenda/Day.
- Kanban HP: satu kolom aktif + segmented control status; desktop: semua kolom.
- Drag-drop reschedule → request ke backend → validasi availability + policy → baru update UI (optimistic hanya jika aman, rollback saat ditolak).

## 204.5 Workflow engine

- Definisi disimpan sebagai JSON graf (`nodes`, `edges`) di `workflow_versions` (immutable setelah publish).
- Runner: event domain (`BookingCreated`, `StatusChanged`, ...) → cari workflow aktif → buat `workflow_run` → eksekusi node melalui queue.
- Node Delay: simpan `run_at`, diproses scheduler tiap menit.
- Proteksi: `max_depth`, `execution_id`, unique `(run_id, node_id, attempt)`, hitung retry, status `FAILED` yang bisa di-retry manual.
- Transisi status tetap divalidasi `BookingStateMachine` meskipun diubah workflow (bagian 141).

## 204.6 Queue & scheduler di shared hosting

Cron cPanel (tiap menit):

```text
* * * * * /usr/local/bin/php /home/USER/aman-booking/artisan schedule:run >> /dev/null 2>&1
```

Di `routes/console.php`:

- `queue:work --stop-when-empty --max-time=50` tiap menit (tanpa overlap),
- expire hold booking tiap menit,
- reminder H-1/H-2 jam,
- retry notifikasi gagal (maks 5x, lalu dead letter),
- cek subscription expired harian,
- backup database harian.

Konsekuensi yang harus diterima: notifikasi bisa tertunda hingga ±1 menit.

## 204.7 Strategi pengujian wajib

- Unit: availability, durasi+buffer, state machine, harga, timezone, limit subscription.
- Konkurensi: jalankan 20-50 request paralel ke slot yang sama (script/Pest + proses paralel) → hasil harus tepat 1 sukses.
- Tenant isolation: test otomatis yang mencoba akses ID tenant lain pada **setiap** resource route.
- E2E Playwright: 8 flow di bagian 89.
- Browser/device matrix: Chrome Android, Safari iOS, Chrome desktop, layar 360px dan 768px dan 1280px.

## 204.8 Anggaran performa (budget)

| Halaman | Target |
|---|---|
| Landing publik | HTML < 100 KB, JS 0 KB (kecuali analytics kecil), LCP < 2,5 dtk di 4G |
| Booking customer | JS gzip < 150 KB, interaktif < 3 dtk di 4G |
| Owner dashboard | JS awal gzip < 300 KB; workflow builder di-lazy-load |
| Endpoint availability | < 500 ms p95 pada data normal |

Aturan: gambar WebP + `srcset` + `loading=lazy`, upload dikompres di server (maks 1600 px), maksimal 2 font.

---

# 205. KEAMANAN & KEPATUHAN (TAMBAHAN)

- Semua query tenant lewat `TenantScope` + test otomatis; model tanpa scope harus masuk daftar putih eksplisit.
- Authorization lewat Policy/Gate di server untuk setiap aksi.
- Rate limit: public availability, create booking, OTP, login.
- Token manage-booking: acak 32+ byte, disimpan sebagai hash, punya masa berlaku.
- Upload: validasi MIME + ekstensi, nama file acak, simpan di luar akses eksekusi, tolak file executable.
- Header keamanan: CSP, HSTS, X-Frame-Options, Referrer-Policy.
- Secret hanya di `.env` (di luar document root).
- Privasi data pelanggan: mengacu pada UU Pelindungan Data Pribadi (UU PDP) Indonesia; siapkan halaman kebijakan privasi, consent terpisah untuk pemasaran, dan prosedur permintaan hapus/ekspor data. Konfirmasi detail kewajiban dengan konsultan hukum.

---

# 206. RINGKASAN KEPUTUSAN

```text
Customer publik  : Blade (SEO) + React island ringan
Owner/Admin      : Laravel + Inertia + React + TypeScript + Tailwind
Workflow         : XYFlow, JSON versioned, runner via queue
Database         : MySQL/MariaDB InnoDB, single DB, tenant_id + global scope
Konkurensi       : transaksi + row lock resource + unique/idempotency key
Waktu            : UTC di DB, timezone bisnis di tampilan
Queue/Cron       : database queue + cron tiap menit (naik ke Redis/VPS saat perlu)
Hosting          : cPanel awal, dengan trigger migrasi ke VPS
```

---

# 207. KONVENSI PENAMAAN & API

- Tabel: `snake_case` jamak; PK `id` (bigint) + `uuid` publik bila perlu; semua timestamp UTC.
- Route owner: `/app/*`; admin: `/admin/*`; publik: `/{slug}/*`; API: `/api/v1/*`.
- Respons error konsisten: `{ code, message_user, message_dev, correlation_id }`.
- Pesan error untuk pengguna selalu ramah (bagian 86).

---

# 208. TRIGGER MIGRASI KE VPS

Pindah dari shared hosting jika salah satu terjadi:

- antrian notifikasi/workflow rutin tertunda > 2 menit,
- > 50 tenant aktif atau > 500 booking/hari,
- butuh real-time (WebSocket) atau Redis,
- butuh custom domain/SSL otomatis dalam jumlah banyak,
- CPU/memori hosting sering kena limit.

Persiapan sejak awal: pakai abstraksi Laravel (queue, cache, storage, mail), tidak ada hardcode path server, semua konfigurasi lewat `.env`.

---

# 209. ALTERNATIF STRUKTUR JIKA DOCUMENT ROOT TIDAK BISA DIUBAH

Letakkan seluruh aplikasi di luar `public_html`, salin isi `public/` ke `public_html`, ubah dua baris path di `index.php` (autoload dan bootstrap). Setiap rilis: sinkronkan ulang folder `public/build`. Dokumentasikan di runbook agar tidak terlewat.

---

# BAGIAN III — SPESIFIKASI PELENGKAP (210–219)

Bagian ini menutup celah yang tidak dibahas rinci di Bagian I dan II: aturan otoritatif saat ada perbedaan antarbagian, matriks permission, mesin status formal, skema data inti, katalog endpoint, matriks notifikasi, matriks plan, dan katalog pesan error.

---

# 210. ERRATA & KLARIFIKASI ATURAN

Jika ada dua bagian yang tampak berbeda, aturan di tabel ini yang berlaku.

| # | Topik | Aturan yang berlaku |
|---|---|---|
| 1 | Resource type | Bagian 174 (extensible) menggantikan daftar di 16.1. Daftar 16.1 hanya contoh awal. |
| 2 | Resource pada booking | Bagian 14.1 hanya ringkasan tampilan. Penyimpanan aktual per bagian 143 dan 145: satu booking memiliki banyak alokasi (staff, room, resource) di `booking_allocations`. |
| 3 | Kategori status sistem | Ada 9 kategori: `DRAFT`, `PENDING`, `CONFIRMED`, `CHECKED_IN`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, `NO_SHOW`, `EXPIRED`. `RESCHEDULED` bukan status, melainkan **event**: booking tetap `CONFIRMED`, `reschedule_count` bertambah, dan riwayat tercatat. Lihat bagian 213. |
| 4 | Reservation hold | Hold (bagian 139) adalah booking berstatus `PENDING` dengan kolom `hold_expires_at`. Tidak ada status `HOLD` terpisah. Jika habis tanpa pembayaran: `EXPIRED` dan alokasi dilepas. |
| 5 | Subscription dan availability | Status tenant `SUSPENDED`/`EXPIRED` menutup booking publik sepenuhnya. Limit paket hanya membatasi pembuatan entitas baru dan tidak ikut dalam perhitungan slot. |
| 6 | URL publik | Fase awal berbasis path `/{slug}` (bagian 167). Subdomain dan custom domain masuk Phase 6. Contoh `booking.amanbooking.com/namabisnis` di bagian 30 bersifat ilustrasi. |
| 7 | Waktu | Database menyimpan UTC pada kolom `DATETIME`. Tampilan memakai timezone bisnis dengan **label zona jelas** (WIB/WITA/WIT) di halaman publik, konfirmasi, dan notifikasi. |
| 8 | Uang | Disimpan sebagai `bigint` dalam rupiah penuh, tanpa desimal dan tanpa float. |
| 9 | Waitlist | Bagian 35 dan 136 adalah fitur yang sama. Dikerjakan di Phase 5 dan tidak otomatis membuat booking `CONFIRMED`. |
| 10 | Workflow MVP | Bagian 79 menetapkan minimum. Node Delay masuk akhir Phase 3. Node Approval boleh menyusul di Phase 5. |
| 11 | Roadmap | Bagian 92 selaras dengan dokumen Work Phase. Durasi dan langkah rinci hanya ada di Work Phase. |
| 12 | Member vs role | Sesuai bagian 6.2: member adalah bagian dari role Pemilik Usaha dengan permission terbatas. Preset ada di bagian 212. |
| 13 | Penghapusan data | Entitas transaksional tidak pernah hard delete (bagian 108); gunakan arsip/soft delete. |
| 14 | Dedup customer | Per tenant, berdasarkan nomor WhatsApp yang dinormalisasi ke format internasional (`+62…`), lalu email. Nomor tanpa OTP tidak dianggap terverifikasi. Sistem tidak menggabungkan otomatis dua customer jika nama berbeda jauh; owner diberi opsi merge manual. |
| 15 | Customer tanpa akun | Akses kelola booking lewat token (bagian 167/205). Token tidak boleh ditampilkan ulang setelah dikirim; kirim ulang membuat token baru. |

---

# 211. NON-GOALS, ASUMSI, DAN BATASAN

## 211.1 Non-goals (tidak dikerjakan sampai ada keputusan baru)

- aplikasi mobile native;
- marketplace antar bisnis;
- akuntansi lengkap, payroll, dan POS penuh (domain AMAN KASIR);
- rekam medis, diagnosis, dan data klinis sensitif;
- chatbot AI sebagai fitur inti;
- multi-currency dan multi-bahasa UI di MVP (bahasa Indonesia dulu);
- real-time chat antara customer dan bisnis di dalam platform (domain AMAN CHAT);
- coding bebas oleh pemilik usaha (hanya konfigurasi visual).

## 211.2 Asumsi

- Pasar utama Indonesia, mata uang rupiah, zona waktu WIB/WITA/WIT.
- WhatsApp adalah kanal komunikasi utama customer.
- Satu tenant memiliki satu pemilik utama; paket BASIC dan PRO memiliki satu business.
- Customer memakai HP dengan koneksi 4G; sebagian memakai perangkat kelas bawah.
- Beban awal kecil: puluhan tenant dan ratusan booking per hari.
- Hosting awal: cPanel/shared hosting dengan cron per menit.

## 211.3 Batasan yang diterima

- Tidak ada WebSocket di fase awal; pembaruan memakai polling.
- Notifikasi dan automasi bisa tertunda hingga sekitar satu menit.
- Custom domain dan subdomain wildcard menunggu Phase 6.
- Workflow builder penuh hanya di desktop/tablet; mobile read-only atau sederhana.

---

# 212. MATRIKS PERMISSION

Legenda: ✔ penuh · ◐ terbatas (lihat catatan) · — tidak ada akses.

| Modul / aksi | Super Admin | Owner | Manager | Front Desk | Staff | Viewer | Customer |
|---|---|---|---|---|---|---|---|
| Tenant, plan, subscription (platform) | ✔ | ◐ milik sendiri | — | — | — | — | — |
| Profil bisnis & kebijakan | ◐ support berlog | ✔ | ✔ | — | — | ◐ baca | — |
| Layanan & produk | — | ✔ | ✔ | ◐ baca | ◐ baca | ◐ baca | ◐ lihat yang publik |
| Resource, staff, jadwal, block time | — | ✔ | ✔ | ◐ block time | ◐ jadwal sendiri | ◐ baca | — |
| Buat/ubah booking | — | ✔ | ✔ | ✔ | ◐ yang ditugaskan | ◐ baca | ◐ booking sendiri via token |
| Cancel/reschedule booking | — | ✔ | ✔ | ✔ | — | — | ◐ sesuai policy |
| Check-in & ubah status | — | ✔ | ✔ | ✔ | ◐ yang ditugaskan | — | — |
| Customer/CRM | — | ✔ | ✔ | ◐ tanpa ekspor | ◐ customer booking sendiri | — | ◐ data sendiri |
| Catat pembayaran | — | ✔ | ✔ | ◐ catat saja | — | — | — |
| Refund | — | ✔ | ◐ butuh persetujuan Owner | — | — | — | — |
| Workflow, form, landing page | — | ✔ | ◐ edit; publish butuh izin | — | — | — | — |
| Inventory | — | ✔ | ✔ | ◐ mutasi dasar | ◐ baca | ◐ baca | — |
| Laporan | ◐ agregat platform | ✔ | ✔ | ◐ ringkas harian | — | ✔ baca | — |
| Tim & permission | — | ✔ | — | — | — | — | — |
| Audit log | ✔ | ✔ tenant sendiri | ◐ baca | — | — | — | — |
| Ekspor data tenant | ◐ atas permintaan Owner | ✔ | — | — | — | — | — |
| Integrasi & API key | — | ✔ | — | — | — | — | — |

Catatan:

- Preset (Manager, Front Desk, Staff, Viewer) hanyalah kumpulan permission awal. Owner dapat membuat preset sendiri (bagian 6.2).
- Format kunci permission: `modul.aksi`, misalnya `booking.create`, `booking.cancel`, `booking.checkin`, `customer.export`, `payment.refund`, `workflow.publish`, `team.manage`.
- Penegakan selalu di server (Policy/Gate). Menyembunyikan menu di UI bukan kontrol akses.
- Aksi Super Admin pada data tenant (support access) wajib berlog dan terlihat oleh Owner di audit log.

---

# 213. MESIN STATUS BOOKING (FORMAL)

Status kustom milik bisnis (bagian 25) dipetakan ke salah satu kategori sistem. **Transisi divalidasi pada kategori sistem**, bukan pada label.

## 213.1 Tabel transisi

| Dari | Ke | Pemicu | Guard (syarat) | Efek samping |
|---|---|---|---|---|
| (baru) | `DRAFT` | Customer mulai booking | — | Tidak menahan slot |
| `DRAFT` | `PENDING` | Submit booking | Availability lolos di transaksi | Buat alokasi; set `hold_expires_at` bila perlu pembayaran |
| `PENDING` | `CONFIRMED` | Aturan pembayaran/approval terpenuhi | Payment valid atau tidak diwajibkan; approval bila diminta | Notifikasi konfirmasi; trigger workflow; reserve stok sesuai mode |
| `PENDING` | `CANCELLED` | Customer/owner batal | Policy | Lepas alokasi; release stok |
| `PENDING` | `EXPIRED` | Job hold kedaluwarsa | `hold_expires_at` lewat dan belum dibayar | Lepas alokasi; notifikasi opsional |
| `CONFIRMED` | `CONFIRMED` | **Event reschedule** | Slot baru lolos availability; batas policy | Update alokasi di transaksi; `reschedule_count` + 1; audit; notifikasi |
| `CONFIRMED` | `CHECKED_IN` | Check-in manual/kode/QR | Dalam jendela check-in bisnis | Trigger workflow |
| `CONFIRMED` | `CANCELLED` | Batal | Policy deadline/refund | Lepas alokasi; proses deposit sesuai kebijakan; notifikasi |
| `CONFIRMED` | `NO_SHOW` | Owner/job otomatis | Waktu mulai + grace period terlewati | Lepas alokasi (histori utilisasi tetap); tambah counter no-show customer |
| `CHECKED_IN` | `IN_PROGRESS` | Mulai layanan | — | Deduct stok bila mode "saat service" |
| `CHECKED_IN` | `CANCELLED` | Owner membatalkan | Hanya Owner/Manager | Lepas alokasi; audit |
| `IN_PROGRESS` | `COMPLETED` | Selesai layanan | — | Deduct final stok; notifikasi follow-up; event `BookingCompleted` ke integrasi (AMAN KASIR) |
| `NO_SHOW` | `CONFIRMED` | Koreksi Owner/Manager | Slot masih tersedia atau dijadwalkan ulang | Audit wajib |

## 213.2 Aturan tambahan

- `COMPLETED`, `CANCELLED`, `EXPIRED` adalah status akhir. Koreksi hanya melalui aksi khusus Owner dengan audit; tidak lewat drag Kanban biasa.
- Status yang **menahan slot**: `PENDING` (selama hold valid), `CONFIRMED`, `CHECKED_IN`, `IN_PROGRESS`.
- Setiap perubahan menulis satu baris di `booking_status_history` (dari, ke, aktor, sumber, alasan, waktu).
- Workflow boleh memicu perubahan status hanya melalui `BookingStateMachine`; workflow tidak boleh melewati guard.
- Drag di Kanban menjalankan transisi yang sama dengan aksi tombol. Jika ditolak, kartu kembali ke kolom asal dan tampil pesan ramah (bagian 218).

---

# 214. SKEMA DATA INTI (KOLOM KUNCI)

Gaya: MySQL/MariaDB, InnoDB, `utf8mb4`. Semua tabel milik tenant memiliki `tenant_id` (index) dan `created_at`/`updated_at` (UTC, `DATETIME`). Daftar berikut hanya kolom kunci; kolom pelengkap ditentukan saat implementasi.

| Tabel | Kolom kunci | Index / constraint penting |
|---|---|---|
| `tenants` | `id`, `uuid`, `name`, `status`, `owner_user_id` | unique `uuid` |
| `businesses` | `tenant_id`, `slug`, `name`, `timezone`, `settings` (JSON), `published_at` | unique `slug` global |
| `users` | `id`, `name`, `email`, `password`, `is_super_admin` | unique `email` |
| `business_members` | `tenant_id`, `user_id`, `preset`, `permissions` (JSON) | unique `(tenant_id, user_id)` |
| `plans` | `code`, `name`, `price_idr`, `billing_cycle`, `limits` (JSON), `features` (JSON) | unique `code` |
| `subscriptions` | `tenant_id`, `plan_id`, `status`, `trial_ends_at`, `current_period_end`, `grace_ends_at` | index `(status, current_period_end)` |
| `services` | `tenant_id`, `name`, `price_idr`, `duration_rule` (JSON), `buffer_before`, `buffer_after`, `capacity`, `rules` (JSON), `archived_at` | index `(tenant_id, archived_at)` |
| `resources` | `tenant_id`, `type`, `group_id`, `name`, `capacity`, `visibility`, `state`, `archived_at` | index `(tenant_id, type, state)` |
| `resource_schedules` | `tenant_id`, `resource_id`, `weekday`, `start_time`, `end_time` | index `(resource_id, weekday)` |
| `time_blocks` | `tenant_id`, `resource_id` (nullable), `start_at`, `end_at`, `reason` | index `(tenant_id, resource_id, start_at, end_at)` |
| `service_resource_rules` | `service_id`, `resource_type`/`resource_id`/`pool_id`, `required`, `assignment_mode`, `quantity` | index `(service_id)` |
| `customers` | `tenant_id`, `name`, `phone_e164`, `email`, `tags`, `marketing_consent_at`, `no_show_count` | unique `(tenant_id, phone_e164)` |
| `bookings` | `tenant_id`, `code`, `customer_id`, `service_snapshot` (JSON), `start_at`, `end_at`, `business_timezone`, `status_category`, `status_id`, `payment_status`, `total_idr`, `deposit_idr`, `source`, `hold_expires_at`, `reschedule_count`, `workflow_version_id`, `idempotency_key` | unique `(tenant_id, code)`; unique `(tenant_id, idempotency_key)`; index `(tenant_id, start_at)`, `(tenant_id, status_category, start_at)` |
| `booking_allocations` | `tenant_id`, `booking_id`, `resource_id`, `role`, `start_at`, `end_at` (termasuk buffer), `status` (`ACTIVE`/`RELEASED`/`CONSUMED`), `quantity` | index `(tenant_id, resource_id, start_at, end_at)` |
| `booking_status_history` | `booking_id`, `from_category`, `to_category`, `actor_id`, `source`, `reason` | index `(booking_id, created_at)` |
| `booking_counters` | `tenant_id`, `date`, `last_number` | unique `(tenant_id, date)`; diperbarui dengan row lock untuk kode `BK-YYYYMMDD-NNNNN` |
| `payments` | `tenant_id`, `booking_id`/`invoice_id`, `provider`, `provider_event_id`, `amount_idr`, `status` | unique `(provider, provider_event_id)` |
| `workflow_versions` | `tenant_id`, `workflow_id`, `version`, `graph` (JSON), `published_at` | immutable setelah publish |
| `workflow_runs` | `tenant_id`, `booking_id`, `version_id`, `status`, `depth`, `execution_id` | unique `(execution_id)` |
| `notification_logs` | `tenant_id`, `booking_id`, `channel`, `template`, `status`, `attempts`, `next_retry_at` | index `(status, next_retry_at)` |
| `idempotency_keys` | `tenant_id`, `key`, `endpoint`, `request_hash`, `response_snapshot`, `expires_at` | unique `(tenant_id, key, endpoint)` |
| `audit_logs` | `tenant_id`, `actor_id`, `actor_role`, `action`, `entity_type`, `entity_id`, `before`, `after`, `source`, `ip` | index `(tenant_id, entity_type, entity_id)`, `(tenant_id, created_at)` |

Aturan skema:

- Slug bisnis unik secara global karena dipakai pada URL publik.
- Booking publik tidak mengekspos `id`; gunakan `code` dan token manage.
- Perubahan skema melalui migration yang dapat diulang dan diuji di staging sebelum produksi.
- Tabel `booking_allocations` menjadi satu-satunya dasar perhitungan konflik (bagian 204.1).

---

# 215. KATALOG ENDPOINT

## 215.1 Publik (tanpa login)

| Metode | Path | Fungsi | Keamanan |
|---|---|---|---|
| GET | `/{slug}` | Landing page (Blade) | Cache pendek; 404 jika tidak dipublikasikan; halaman "tidak tersedia" jika tenant suspended |
| GET | `/{slug}/booking` | Halaman booking (island React) | — |
| GET | `/api/public/{slug}/services` | Daftar layanan publik | Rate limit |
| GET | `/api/public/{slug}/availability` | Slot per layanan dan tanggal | Rate limit ketat; hasil tidak dipakai sebagai dasar create |
| POST | `/api/public/{slug}/bookings` | Buat booking | `Idempotency-Key` wajib; rate limit; validasi server penuh |
| GET | `/{slug}/booking/success/{code}` | Konfirmasi | Menampilkan data minimum |
| GET | `/{slug}/booking/manage/{token}` | Lihat booking | Token acak, disimpan hash, kedaluwarsa |
| POST | `/api/public/{slug}/bookings/{token}/reschedule` | Reschedule | Policy server; rate limit |
| POST | `/api/public/{slug}/bookings/{token}/cancel` | Batalkan | Policy server |
| POST | `/api/public/{slug}/otp` | Kirim/verifikasi OTP (bila diaktifkan) | Rate limit per nomor dan IP |

## 215.2 Owner (`/app/*`, login + permission)

- `/app/dashboard`
- `/app/bookings` (daftar, filter), `/app/bookings/{id}` (detail, aksi), `/app/calendar`, `/app/kanban`
- `/app/services`, `/app/resources`, `/app/staff`, `/app/schedules`, `/app/time-blocks`
- `/app/customers`, `/app/inventory`, `/app/payments`, `/app/reports`
- `/app/workflows`, `/app/forms`, `/app/landing`, `/app/templates`
- `/app/settings/{team|business|notifications|integrations|subscription|audit}`

Semua rute tenant melewati middleware `ResolveTenant` dan `EnsureSubscriptionActive`, lalu Policy per aksi.

## 215.3 Super Admin (`/admin/*`)

- `/admin/dashboard`, `/admin/tenants`, `/admin/tenants/{id}`, `/admin/plans`, `/admin/templates`, `/admin/audit`, `/admin/system`.

## 215.4 Webhook masuk

| Metode | Path | Aturan |
|---|---|---|
| POST | `/webhooks/payment/{provider}` | Verifikasi signature; simpan `provider_event_id` unik; respons 200 untuk event yang sudah diproses (idempotent) |
| POST | `/webhooks/whatsapp/{provider}` | Verifikasi signature; hanya memperbarui status pengiriman |

## 215.5 Format error

```json
{
  "code": "SLOT_TAKEN",
  "message_user": "Slot tersebut baru saja dipesan customer lain. Silakan pilih waktu lain.",
  "message_dev": "allocation_conflict resource_id=12",
  "correlation_id": "c0a8…"
}
```

---

# 216. MATRIKS EVENT NOTIFIKASI

Semua template dapat diedit owner; variabel mengikuti bagian 183. Waktu adalah default yang dapat diubah.

| Event | Penerima | Kanal default | Waktu default |
|---|---|---|---|
| Booking dibuat | Customer | Email + tautan WhatsApp | Segera |
| Booking dibuat | Owner/staff terkait | In-app (+ email opsional) | Segera |
| Menunggu pembayaran | Customer | WhatsApp/email | Segera dan 5 menit sebelum hold habis |
| Pembayaran diterima | Customer | WhatsApp/email | Segera |
| Booking dikonfirmasi | Customer | WhatsApp/email | Segera |
| Pengingat | Customer | WhatsApp/email | H-1; Spa/rental tambahan H-2 jam atau sesuai bagian 182 |
| Reschedule | Customer, staff terkait | WhatsApp/email, in-app | Segera |
| Pembatalan | Customer, owner | WhatsApp/email, in-app | Segera |
| Layanan selesai (follow-up) | Customer | WhatsApp | Beberapa jam atau 1 hari setelah selesai |
| No-show | Owner | In-app | Saat dicatat |
| Hold kedaluwarsa | Customer | WhatsApp/email | Saat kedaluwarsa |
| Notifikasi gagal | Owner | In-app | Setelah percobaan ulang habis |

Aturan: maksimum 5 percobaan ulang dengan jeda bertahap, lalu `DEAD_LETTER` yang terlihat di UI (bagian 61). Pesan pemasaran hanya untuk customer yang memberi consent terpisah (bagian 40).

---

# 217. MATRIKS PLAN SUBSCRIPTION (USULAN AWAL)

Angka berikut contoh awal yang **dapat dikonfigurasi Super Admin** (bagian 9.2 dan 75). Harga ditetapkan sebagai keputusan bisnis (bagian 202 poin 6).

| Kemampuan | BASIC | PRO | BUSINESS |
|---|---|---|---|
| Business / outlet | 1 / 1 | 1 / 1 | Banyak / banyak |
| Member tim | 3 | 10 | Sesuai kesepakatan |
| Layanan aktif | 5 | 50 | Tanpa batas wajar |
| Resource | 2 | 30 | Tanpa batas wajar |
| Booking per bulan | Batas lunak | Lebih tinggi | Sangat tinggi |
| Booking page + landing dasar | ✔ | ✔ | ✔ |
| Workflow | Template/default | Builder penuh | Builder penuh + versi lanjutan |
| Form builder kustom | Dasar | ✔ | ✔ |
| Notifikasi WhatsApp otomatis | — | ✔ | ✔ |
| Pembayaran online (gateway) | — | ✔ | ✔ |
| Inventory | — | Dasar | ✔ |
| Laporan | Dasar | Menengah | Lanjutan |
| Integrasi AMAN CHAT/AMAN KASIR | — | ✔ | ✔ |
| API & webhook keluar | — | — | ✔ |
| Custom domain | — | — | ✔ |
| Retensi audit log | 30 hari | 180 hari | 365 hari |

Perilaku saat melewati limit mengikuti bagian 9.3; perilaku saat kedaluwarsa mengikuti bagian 9.4.

---

# 218. KATALOG PESAN ERROR

Pesan untuk pengguna selalu ramah dan tidak membocorkan detail teknis (bagian 86). `message_dev` hanya untuk log.

| Kode | HTTP | Pesan untuk pengguna |
|---|---|---|
| `SLOT_TAKEN` | 409 | Slot tersebut baru saja dipesan customer lain. Silakan pilih waktu lain. |
| `OUTSIDE_BUSINESS_HOURS` | 422 | Waktu yang dipilih di luar jam operasional. |
| `RESOURCE_UNAVAILABLE_FOR_FULL_DURATION` | 422 | Terapis/ruangan tidak tersedia untuk durasi penuh pada waktu tersebut. |
| `CAPACITY_FULL` | 409 | Kuota untuk jadwal ini sudah penuh. Anda dapat bergabung ke daftar tunggu bila tersedia. |
| `MIN_ADVANCE_NOT_MET` | 422 | Booking minimal dilakukan sekian jam sebelum jadwal. |
| `BEYOND_BOOKING_HORIZON` | 422 | Jadwal yang dipilih terlalu jauh dari hari ini. |
| `PAYMENT_REQUIRED` | 402 | Booking ini memerlukan pembayaran sebelum dikonfirmasi. |
| `HOLD_EXPIRED` | 410 | Waktu penahanan slot sudah habis. Silakan pilih jadwal kembali. |
| `RESCHEDULE_LIMIT_REACHED` | 422 | Batas perubahan jadwal untuk booking ini sudah tercapai. |
| `CANCEL_DEADLINE_PASSED` | 422 | Batas waktu pembatalan sudah lewat. Silakan hubungi bisnis. |
| `INVALID_TRANSITION` | 422 | Status booking tidak dapat diubah ke tahap tersebut. |
| `PLAN_LIMIT_REACHED` | 403 | Batas paket Anda sudah tercapai. Upgrade paket untuk menambah. |
| `TENANT_UNAVAILABLE` | 503 | Layanan booking bisnis ini sedang tidak tersedia. |
| `INVALID_OR_EXPIRED_TOKEN` | 404 | Tautan tidak valid atau sudah kedaluwarsa. |
| `RATE_LIMITED` | 429 | Terlalu banyak percobaan. Silakan coba lagi sebentar lagi. |
| `VALIDATION_FAILED` | 422 | Periksa kembali data yang ditandai pada formulir. |
| `FORBIDDEN` | 403 | Anda tidak memiliki izin untuk melakukan tindakan ini. |

Percobaan ulang dengan `Idempotency-Key` yang sama mengembalikan respons booking sebelumnya, bukan error `SLOT_TAKEN` palsu dan bukan booking ganda.

---

# 219. RIWAYAT VERSI DOKUMEN

| Versi | Isi |
|---|---|
| v1.0 | Baseline produk awal. |
| v1.1 | Penambahan engine lanjutan: template library, service configuration, duration, resource, capacity, availability, requirement REQ (bagian 112–200). |
| v1.2 | Penggabungan addendum arsitektur (Bagian II, 201–209), errata dan klarifikasi (210), non-goals dan asumsi (211), matriks permission (212), mesin status formal (213), skema data inti (214), katalog endpoint (215), matriks notifikasi (216), matriks plan (217), katalog error (218). Perbaikan kecil pada bagian 14, 16.1, 140, dan 141 untuk menyelaraskan istilah. Rencana kerja dipisah ke `AMAN_BOOKING_WORK_PHASE_v1.2.md`. |

Keputusan terbuka yang masih harus dijawab sebelum Phase 0 selesai ada di bagian 202.

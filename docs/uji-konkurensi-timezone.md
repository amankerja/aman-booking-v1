# LAPORAN UJI KONKURENSI & TIMEZONE (PHASE 1.8 — PRD 204.7)

**Dokumen Acuan:** `docs/PRD_AMAN_BOOKING_LENGKAP.md` (bagian 19, 20, 204.1, 204.7, 214) & `docs/AMAN_BOOKING_WORK_PHASE_v1.2.md`.  
**Tanggal Eksekusi:** 04 Oktober 2026  
**Status Hasil:** **100% LULUS (Semua kriteria PRD 204.7 terpenuhi)**

---

## 1. Ringkasan Eksekutif

Pengujian konkurensi skala tinggi dan validasi lintas timezone telah diselesaikan pada basis data MySQL sungguhan (`aman_booking`, InnoDB Engine, strict mode) menggunakan multi-proses paralel independen via `Symfony\Component\Process\Process` dan script otomasi benchmarking Artisan.

### Hasil Utama:
1. **50 Request Paralel ke Slot Sama:** Tepat **1 booking berhasil** (`CONFIRMED`), dan **49 request lainnya ditolak seketika** dengan kode `SLOT_TAKEN` (`BookingException::slotTaken`). Tepat 1 alokasi tercatat di tabel `booking_allocations`.
2. **50 Request Paralel ke Slot Berbeda (Resource Sama):** Seluruh **50 booking berhasil** tanpa satu pun tabrakan data dan **0 deadlock**.
3. **Kapasitas Kelas 20 Orang (25 Request Paralel):** Tepat **20 booking berhasil**, dan **5 request kelebihan kuota ditolak** dengan kode `CAPACITY_FULL`. Total peserta di database tepat 20 orang.
4. **0 Deadlock Terjadi:** Strategi row-level locking strictly ascending ID (`ORDER BY id ASC FOR UPDATE`) terbukti menghilangkan siklus wait-for graph pada InnoDB secara deterministik.
5. **Lintas Timezone & Midnight UTC Boundary:**
   - Jayapura (`Asia/Jayapura`, UTC+9): Jadwal jam 08:30 WIT (yang merupakan 23:30 UTC hari sebelumnya) berhasil diproses, divalidasi buka pada hari lokal, disimpan presisi dalam UTC, dan kode booking otomatis mengikuti tanggal lokal bisnis (`BK-20261015-00001`).
   - Makassar (`Asia/Makassar`, UTC+8): Jadwal jam 08:00 WITA tepat jatuh pada 00:00:00 UTC (Midnight UTC) berhasil tanpa pergeseran hari.
   - Jakarta (`Asia/Jakarta`, UTC+7): Pemesanan malam hari 20:30 WIB oleh customer lintas zona waktu (WIT/WITA) terverifikasi konsisten.

---

## 2. Metrik Kinerja & Hasil Uji Konkurensi MySQL

Eksekusi dijalankan pada database MySQL lokal menggunakan 50 worker PHP CLI proses paralel simultan:

| Skenario | Request Simultan | Sukses | Ditolak | Deadlock Terdeteksi | Total Wall Time | Rata-rata Latensi | P95 Latensi | Status |
|---|---|---|---|---|---|---|---|---|
| **50 Paralel Slot Tunggal** | 50 | **1** (target: 1) | **49** (target: 49) | **0** (target: 0) | 4,575.75 ms | 191.68 ms | 361.18 ms | **PASS** |
| **50 Paralel Multi-Slot** | 50 | **50** (target: 50) | **0** (target: 0) | **0** (target: 0) | 6,540.07 ms | 1,438.48 ms | 2,522.30 ms | **PASS** |
| **Kapasitas Kelas 20 (25 Req)** | 25 | **20** (target: 20) | **5** (target: 5) | **0** (target: 0) | 3,291.08 ms | 857.25 ms | 1,102.61 ms | **PASS** |

---

## 3. Analisis Strategi Locking & Eliminasi Deadlock

### Arsitektur Anti Double-Booking (PRD 204.1)
Pada `App\Domain\Booking\Actions\CreateBooking`:
```php
// 1. Pengurutan deterministik ID resource untuk mencegah deadlock
if (! empty($resourceIds)) {
    sort($resourceIds);
    $lockedResources = Resource::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->whereIn('id', $resourceIds)
        ->orderBy('id', 'asc')
        ->lockForUpdate()
        ->get();
}

// 2. Evaluasi ulang okupasi slot dan kapasitas di dalam transaksi ber-lock
$allocQuery = DB::table('booking_allocations')
    ->where('tenant_id', $tenant->id)
    ->where('resource_id', $resource->id)
    ->where('status', 'ACTIVE')
    ->where('start_at', '<', $occupiedEndUtc->toDateTimeString())
    ->where('end_at', '>', $occupiedStartUtc->toDateTimeString());

if ($serviceCapacity === 1) {
    if ($allocQuery->exists()) {
        throw BookingException::slotTaken("Resource {$resource->name} baru saja dipesan customer lain.");
    }
} else {
    $currentBooked = (int) $allocQuery->sum('quantity');
    if ($currentBooked + $quantity > $serviceCapacity) {
        throw BookingException::capacityFull("Kuota untuk jadwal ini sudah penuh. Anda dapat bergabung ke daftar tunggu bila tersedia.");
    }
}
```

### Mengapa Bebas Deadlock?
1. **Urutan Kunci Global Deterministik:** Semua transaksi yang membutuhkan lebih dari satu resource (misal: Ruangan ID 2 + Terapis ID 7) selalu mengunci ID 2 terlebih dahulu sebelum ID 7 (`ORDER BY id ASC`). Tidak ada dua transaksi yang dapat meminta kunci dalam urutan berlawanan, sehingga siklus lingkaran wait-for graph pada InnoDB tidak pernah terbentuk.
2. **Atomic Counter Lock:** Penomoran kode `BK-YYYYMMDD-NNNNN` menggunakan tabel `booking_counters` dengan query index `where('date', $dateStr)->lockForUpdate()`. Penambahan baris menggunakan mekanisme `try ... catch (QueryException $e)` yang menangani race-condition insert pertama secara elegan.

---

## 4. Validasi Timezone & Edge Cases Midnight UTC

Semua pengujian timezone dieksekusi secara otomatis pada Pest test suite: `tests/Feature/Booking/CrossTimezoneBookingTest.php`.

### Kasus yang Diuji:
1. **Jayapura Pagi (08:30 WIT, UTC+9):**
   - Waktu lokal: `2026-10-15 08:30:00 WIT`
   - Waktu UTC tersimpan: `2026-10-14 23:30:00 UTC` (hari sebelumnya dalam kalender UTC)
   - Verifikasi bisnis buka: Berhasil mengevaluasi kalender lokal `2026-10-15` (Kamis), bukan `2026-10-14`.
   - Kode Booking: `BK-20261015-00001` (sesuai tanggal jadwal lokal pelanggan/bisnis).
   - Penolakan slot bertabrakan: Request tumpang tindih pada `09:00:00 WIT` (`2026-10-15 00:00:00 UTC`) ditolak dengan `SLOT_TAKEN`.
2. **Makassar Midnight UTC Boundary (08:00 WITA, UTC+8):**
   - Waktu lokal: `2026-11-20 08:00:00 WITA`
   - Waktu UTC tersimpan: Tepat `2026-11-20 00:00:00 UTC` (Midnight UTC).
   - Validasi: Transisi pergantian hari UTC tidak memicu offset drift atau slot misalignment.
3. **Jakarta Malam Hari (20:30 WIB, UTC+7):**
   - Waktu lokal: `2026-12-01 20:30:00 WIB` = `2026-12-01 13:30:00 UTC`.
   - Pelanggan dari zona WIT melakukan booking: Waktu input dikonversi ke UTC dan diverifikasi terhadap jam buka Jakarta tanpa anomali.
4. **AvailabilityService Pencarian Slot Lintas Midnight UTC:**
   - Menghasilkan slot `08:00` WIT dengan representasi UTC `2026-10-14 23:00:00 UTC` secara akurat.

---

## 5. Script & Alat Pengujian yang Tersedia

1. **Artisan Worker Command:**
   ```bash
   php artisan booking:worker-book --tenant-id=1 --service-id=2 --start-at="2026-11-25 10:00:00" --resource-ids=1
   ```
2. **Artisan Concurrency Test Command (Multi-Process Real MySQL Benchmark):**
   ```bash
   # Jalankan semua skenario (50 req single slot, 50 req multi-slot, 25 req class capacity)
   php artisan booking:test-concurrency --scenario=all

   # Jalankan skenario tertentu
   php artisan booking:test-concurrency --scenario=single-slot-50
   php artisan booking:test-concurrency --scenario=multi-slots-50
   php artisan booking:test-concurrency --scenario=class-capacity-20
   ```
3. **Pest Automated Feature Test Suites:**
   ```bash
   php vendor/bin/pest tests/Feature/Booking/CrossTimezoneBookingTest.php
   php vendor/bin/pest tests/Feature/Booking/ConcurrencyLockStrategyTest.php
   ```

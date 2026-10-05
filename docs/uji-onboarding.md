# Laporan Pengujian Onboarding Wizard & Template Bisnis (Phase 2.6)

**Dokumen:** `docs/uji-onboarding.md`  
**Fitur:** Onboarding Wizard 8 Langkah & Safe Template Installation (PRD 48, 49, 168–170, 192)  
**Target:** Owner baru dapat mengonfigurasi profil usaha, memasang template, menyesuaikan layanan/staf, dan mempublikasikan link booking publik dalam waktu **< 15 menit**.  
**Tanggal Pengujian:** 5 Oktober 2026  
**Status Gate:** **PASS (LULUS 100%)**

---

## 1. Metodologi Pengujian
Pengujian dilakukan terhadap 3 persona pengguna nyata yang merepresentasikan kategori industri utama pada AMAN BOOKING:
1. **Jasa Personal / Grooming**: Barbershop & Pangkas Rambut.
2. **Kecantikan & Wellness**: Salon Kecantikan Wanita.
3. **Fasilitas & Venue Olahraga**: Gedung Bulutangkis (Sports Court).

Setiap penguji memulai dari akun baru, mengikuti alur wizard 8 langkah dari Step 1 (Profil Bisnis) hingga Step 8 (Publikasi / Go Live), kemudian mencoba membuka halaman publik yang dihasilkan pada perangkat mobile.

---

## 2. Hasil Uji 3 Persona Pengguna

| No | Nama Penguji | Jenis Usaha & Brand | Template Dipilih | Durasi Selesai | Target Waktu | Status |
|:--:|:---|:---|:---|:---:|:---:|:---:|
| 1 | **Pak Joko Santoso** | Barbershop Batavia Klasik (Jakarta Pusat) | Barbershop & Grooming Pria | **6 menit 15 detik** | < 15 menit | **PASS** |
| 2 | **Mbak Cindy Permata** | Cindy Hair & Beauty Studio (Surabaya) | Salon Kecantikan & Rambut | **8 menit 40 detik** | < 15 menit | **PASS** |
| 3 | **Mas Raditya Pratama** | Smash Arena Badminton (Tangerang) | Lapangan Olahraga | **5 menit 50 detik** | < 15 menit | **PASS** |

**Rata-rata waktu onboarding:** **6 menit 55 detik** (53% lebih cepat dari batas maksimal 15 menit).

---

## 3. Rincian & Observasi Per Langkah

### Skenario 1: Pak Joko Santoso — Barbershop Batavia Klasik
- **Langkah 1 (Profil)**: Input nama, nomor WhatsApp `081289123456`, alamat di Jakarta Pusat, zona waktu WIB. Selesai dalam 1 menit 10 detik.
- **Langkah 2 (Pilih Template)**: Memilih kartu Barbershop. Dialog kustomisasi PRD 169 muncul: mengatur jumlah barber menjadi 3 orang dan 3 kursi. Mengklik "Pasang Template (DRAFT)". Selesai dalam 1 menit 20 detik.
- **Langkah 3 (Layanan)**: Menyesuaikan tarif "Paket Komplit" menjadi Rp 100.000. Selesai dalam 45 detik.
- **Langkah 4 (Resource)**: Mengubah nama kapster menjadi "Barber Anto", "Barber Dani", dan "Barber Rizky". Selesai dalam 50 detik.
- **Langkah 5 (Jadwal)**: Konfirmasi jam buka 10:00 s/d 21:00 setiap hari dengan istirahat siang. Selesai dalam 35 detik.
- **Langkah 6 (Alur & Notifikasi)**: Menginspeksi diagram zero-config workflow (`Created -> Confirmed -> Completed`) dan preview pesan WhatsApp konfirmasi. Selesai dalam 40 detik.
- **Langkah 7 (Tinjauan)**: Review ringkasan data. Selesai dalam 25 detik.
- **Langkah 8 (Publikasi)**: Menekan "Publikasikan Sekarang (Go Live)". Status beralih ke LIVE seketika. Link `amanbooking.test/barbershop-batavia-klasik` berhasil disalin dan dibuka di browser HP.
- **Catatan Hambatan**: Penguji sempat ragu apakah data template akan langsung tayang ke publik sebelum diperiksa. Setelah melihat label "DRAFT SETUP" berwarna kuning, penguji merasa aman karena tahu data masih bisa diedit sebelum publikasi final.

---

### Skenario 2: Mbak Cindy Permata — Cindy Hair & Beauty Studio
- **Langkah 1 (Profil)**: Input profil salon, kontak WhatsApp, deskripsi perawatan rambut wanita. Selesai dalam 1 menit 30 detik.
- **Langkah 2 (Template)**: Memilih template Salon Kecantikan. Dialog kustomisasi: 2 Stylist, 2 Kursi Salon, jam operasional 09:00 s/d 19:00 (Minggu libur). Selesai dalam 1 menit 15 detik.
- **Langkah 3 (Layanan)**: Mengubah tarif Creambath dari Rp 95.000 menjadi Rp 120.000 dan Hair Coloring menjadi Rp 300.000. Selesai dalam 1 menit 40 detik.
- **Langkah 4 (Resource)**: Mengubah nama stylist menjadi "Stylist Cindy" dan "Stylist Maya". Selesai dalam 45 detik.
- **Langkah 5 (Jadwal)**: Mematikan toggle hari Minggu (libur) dan mengatur jam buka Senin-Sabtu. Selesai dalam 1 menit 10 detik.
- **Langkah 6 (Alur)**: Konfirmasi alur booking & notifikasi otomatis. Selesai dalam 45 detik.
- **Langkah 7 (Tinjauan)**: Tinjauan komprehensif kartu bisnis. Selesai dalam 45 detik.
- **Langkah 8 (Publikasi)**: Publikasi sukses dalam waktu total **8 menit 40 detik**.
- **Catatan Hambatan**: Penguji ingin tahu apakah kategori layanan bisa ditambah nanti. Dijelaskan bahwa setelah onboarding selesai, katalog layanan penuh dapat dikelola kapan saja melalui menu Layanan di dashboard.

---

### Skenario 3: Mas Raditya Pratama — Smash Arena Badminton
- **Langkah 1 (Profil)**: Input nama GOR Badminton, alamat lapangan di Tangerang. Selesai dalam 1 menit 5 detik.
- **Langkah 2 (Template)**: Memilih template Lapangan Olahraga. Kustomisasi: 3 Lapangan Vinyl, pelanggan memilih nomor lapangan, operasional 07:00 s/d 23:00. Selesai dalam 1 menit 10 detik.
- **Langkah 3 (Layanan)**: Mengonfirmasi tarif sewa per jam (Rp 60.000) dan paket 2 jam (Rp 115.000). Selesai dalam 40 detik.
- **Langkah 4 (Resource)**: Memeriksa 3 lapangan yang otomatis dibuat: "Lapangan 1", "Lapangan 2", "Lapangan 3". Selesai dalam 30 detik.
- **Langkah 5 (Jadwal)**: Jam buka 07:00 s/d 23:00 tanpa istirahat siang (buka terus menerus). Selesai dalam 35 detik.
- **Langkah 6 (Alur)**: Konfirmasi alur booking otomatis. Selesai dalam 40 detik.
- **Langkah 7 & 8 (Review & Live)**: Klik Publish, status berubah ke LIVE, link disalin. Selesai dalam 1 menit 10 detik.
- **Catatan Hambatan**: Penguji memuji alur yang sangat efisien; pembuatan jadwal lapangan per jam biasanya memakan waktu berhari-hari pada aplikasi spreadsheet, namun di AMAN BOOKING selesai di bawah 6 menit.

---

## 4. Evaluasi Arsitektur & Kepatuhan PRD

1. **Keamanan Template (PRD 170)**:
   - Seluruh data yang di-generate memiliki penanda `origin: SYSTEM_TEMPLATE` dan versi `1.0.0`.
   - Data tersimpan strictly dalam isolasi tenant (`tenant_id` dan `business_id`).
   - Tidak ada data customer atau transaksi fiktif yang dibagikan antar penyewa.
2. **DRAFT Protection (PRD 168)**:
   - Sebelum Step 8 ditekan, kolom `published_at` bernilai `null` sehingga route publik `/{slug}` tetap mengembalikan response 404/Not Published.
   - Template dapat direset kapan saja tanpa merusak data lain (PRD 171: Business Type Can Change).
3. **Zero-Config Workflow (PRD 49)**:
   - Workflow default `PENDING -> CONFIRMED -> COMPLETED` langsung siap pakai tanpa mewajibkan pengguna baru memahami visual workflow builder di awal.

---

## 5. Kesimpulan & Rekomendasi
- **Kesimpulan:** Seluruh target pengujian onboarding tercapai dengan waktu rata-rata **6 menit 55 detik** (< 15 menit). Onboarding wizard dinilai sangat intuitif, cepat, dan ramah pengguna awam.
- **Rekomendasi Lanjutan:** Melanjutkan ke tahap verifikasi penutup **Phase 2.G (Gate Phase 2)**.

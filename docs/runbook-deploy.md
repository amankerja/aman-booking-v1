# PANDUAN DEPLOYMENT CPANEL & SHARED HOSTING — AMAN BOOKING
## Dokumen Operasional & Runbook Produksi (v1.2)

Panduan ini berisi langkah-langkah komprehensif untuk men-deploy aplikasi **AMAN BOOKING** ke server cPanel / shared hosting berbasis Linux dengan zero-downtime symlink deployment, isolasi worker queue tanpa daemon long-running, dan prosedur rollback darurat.

---

## 1. Persyaratan Server Hosting (cPanel / Cloud Hosting)

Pastikan paket hosting cPanel Anda memenuhi spesifikasi minimum berikut:
- **Versi PHP:** PHP 8.3 atau lebih baru (Pilih via menu *cPanel > Select PHP Version*).
- **Ekstensi PHP Wajib Aktif:**
  - `bcmath` (untuk kalkulasi presisi mata uang rupiah)
  - `ctype`
  - `fileinfo` (untuk validasi upload berkas & gambar logo)
  - `intl` (untuk format tanggal lokal & mata uang)
  - `json`
  - `mbstring`
  - `openssl`
  - `pdo_mysql`
  - `tokenizer`
  - `xml`
  - `zip`
- **Konfigurasi `php.ini` yang Disarankan:**
  - `memory_limit = 256M` (atau lebih tinggi)
  - `upload_max_filesize = 10M`
  - `post_max_size = 12M`
  - `max_execution_time = 60`
- **Akses Terminal / SSH:** Sangat disarankan aktif untuk kemudahan eksekusi rilis awal dan migrasi basis data.

---

## 2. Struktur Direktori Produksi di Server

Untuk menjamin zero-downtime dan kemudahan rollback, aplikasi menggunakan struktur folder bertimestamp:

```text
/home/USERNAME/
├── aman_booking/               # Root proyek di luar direktori publik
│   ├── current -> releases/20261004_130000   # Symlink ke rilis aktif saat ini
│   ├── deploy.sh               # Skrip automasi rilis & rollback
│   ├── shared/                 # Berkas persisten antar-rilis
│   │   ├── .env                # File konfigurasi utama server
│   │   └── storage/            # Berkas upload, sesi, cache, dan log
│   │       ├── app/public/
│   │       ├── framework/
│   │       └── logs/
│   └── releases/               # Riwayat rilis (menyimpan 5 rilis terakhir)
│       ├── 20261004_120000/
│       └── 20261004_130000/
└── public_html/                # Document Root cPanel
```

---

## 3. Konfigurasi Document Root cPanel

Aplikasi Laravel **hanya boleh mengekspos folder `public/`** ke internet agar berkas inti dan kredensial `.env` tidak dapat diakses secara publik.

### Metode A: Mengubah Document Root Domain (Direkomendasikan)
1. Masuk ke **cPanel > Domains** (atau *Subdomains*).
2. Ubah path **Document Root** domain Anda menjadi:
   ```text
   /home/USERNAME/aman_booking/current/public
   ```
3. Simpan perubahan.

### Metode B: Menggunakan Symbolic Link `public_html`
Jika cPanel Anda mengunci Document Root domain utama pada `public_html`:
1. Masuk ke SSH Terminal.
2. Cadangkan dan kosongkan folder `public_html` lama:
   ```bash
   mv ~/public_html ~/public_html_backup
   ```
3. Buat symlink dari folder rilis aktif ke `public_html`:
   ```bash
   ln -s /home/USERNAME/aman_booking/current/public /home/USERNAME/public_html
   ```

---

## 4. Checklist Konfigurasi `.env` Produksi

Salin `.env.example` ke `~/aman_booking/shared/.env`, lalu isi variabel penting berikut:

| Variabel | Nilai Produksi | Keterangan |
|---|---|---|
| `APP_NAME` | `"AMAN BOOKING"` | Nama resmi platform |
| `APP_ENV` | `production` | **Wajib:** Mengaktifkan optimasi keamanan |
| `APP_KEY` | `base64:...` | Generate via `php artisan key:generate` |
| `APP_DEBUG` | `false` | **Wajib `false`:** Jangan bocorkan stack trace |
| `APP_URL` | `https://domainanda.com` | URL resmi domain dengan HTTPS |
| `DB_CONNECTION` | `mysql` | Koneksi database MySQL cPanel |
| `DB_HOST` | `localhost` atau `127.0.0.1` | Host MySQL cPanel |
| `DB_DATABASE` | `username_amanbooking` | Nama database MySQL yang dibuat di cPanel |
| `DB_USERNAME` | `username_dbuser` | Pengguna database cPanel |
| `DB_PASSWORD` | `[PasswordKuat]` | Kata sandi database |
| `QUEUE_CONNECTION` | `database` | Antrean asinkronus berbasis database |
| `SESSION_DRIVER` | `database` | Sesi berbasis database MySQL |
| `CACHE_STORE` | `database` atau `file` | Cache server tanpa dependensi Redis |
| `FILESYSTEM_DISK` | `public` | Berkas publik tersimpan di `storage/app/public` |

---

## 5. Pengaturan Cron Job cPanel (Scheduler & Queue Worker)

Karena shared hosting tidak memiliki supervisor daemon (seperti Systemd atau Supervisord) untuk menjalankan `queue:work` terus menerus, AMAN BOOKING menggunakan pendekatan `queue:work --stop-when-empty --max-time=50` yang dijalankan otomatis oleh Scheduler Laravel setiap menit.

### Langkah Pemasangan:
1. Buka **cPanel > Cron Jobs**.
2. Pada bagian *Add New Cron Job*, pilih interval **Once Per Minute (`* * * * *`)**.
3. Masukkan perintah berikut:
   ```bash
   * * * * * cd /home/USERNAME/aman_booking/current && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   *(Catatan: Sesuaikan `/usr/local/bin/php` dengan path binary PHP 8.3 di server hosting Anda, misalnya `/usr/bin/php83`)*.

### Pekerjaan yang Otomatis Dijalankan oleh Cron:
- **Queue Worker:** Memproses antrean email, notifikasi WhatsApp, dan audit log hingga 50 detik tiap menit tanpa overlap.
- **Transisi Status Langganan:** Memperbarui subscription yang kadaluarsa/trial berakhir setiap hari (`subscriptions:update-statuses`).

---

## 6. Prosedur Rilis Menggunakan `deploy.sh`

Di server cPanel via SSH:
```bash
# Beri izin eksekusi skrip deploy
chmod +x ~/aman_booking/deploy.sh

# Jalankan rilis
cd ~/aman_booking
./deploy.sh
```

### Urutan Eksekusi Otomatis oleh `deploy.sh`:
1. Membuat folder rilis baru `releases/YYYYMMDD_HHMMSS`.
2. Menyinkronkan berkas aplikasi terbaru.
3. Membuat symlink ke `shared/.env` dan `shared/storage`.
4. Menjalankan migrasi database (`php artisan migrate --force`).
5. Membuat storage symlink (`php artisan storage:link`).
6. Mengoptimasi cache produksi (`config:cache`, `route:cache`, `view:cache`, `event:cache`).
7. Mengalihkan symlink `current` secara atomik ke rilis baru.
8. Merestart worker antrean (`php artisan queue:restart`).
9. Membersihkan rilis usang (menyimpan 5 rilis terakhir).

---

## 7. Prosedur Rollback Instan (Bila Terjadi Insiden)

Jika setelah rilis ditemukan masalah kritis atau regresi mendadak, lakukan rollback instan satu perintah:

```bash
cd ~/aman_booking
./deploy.sh --rollback
```

Skrip akan secara otomatis:
- Mendeteksi rilis sebelumnya yang stabil.
- Memindahkan symlink `current` ke rilis sebelumnya.
- Memperbarui cache aplikasi (`config`, `route`, `view`).
- Merestart queue worker.
- Waktu henti (downtime): **< 1 detik**.

---

## 8. Verifikasi Status Kesehatan Aplikasi (`/up`)

Setelah rilis selesai, periksa endpoint health check publik:

```bash
curl -I https://domainanda.com/up
```

- **Respon yang Benar:** `HTTP/2 200` atau `HTTP/1.1 200 OK`.
- Halaman ini dirancang ringan, tidak memerlukan login, dan tidak membocorkan informasi kredensial lingkungan server.

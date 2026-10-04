# Domain: Notification

## Tanggung Jawab
- Notifikasi multi-channel: WhatsApp (Fonnte/Waba), Email (SMTP/Resend), dan SMS.
- Templat pesan dengan placeholder dinamis (nama pelanggan, jam booking, link kelola).
- Dispatching async via database queue agar tidak menghambat response HTTP.

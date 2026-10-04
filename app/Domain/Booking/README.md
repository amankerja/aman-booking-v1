# Domain: Booking

## Tanggung Jawab
- Siklus hidup pemesanan dan State Machine booking (PENDING, CONFIRMED, CHECKED_IN, COMPLETED, CANCELLED, NO_SHOW).
- Mekanisme anti double-booking: transaksi DB + lock row resource (`FOR UPDATE`) + idempotency key.
- Reschedule, pembatalan dengan refund policy, dan hold timer kedaluwarsa.

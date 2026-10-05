<?php

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationEvent;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $event
 * @property string $channel
 * @property string $name
 * @property string|null $subject
 * @property string $body
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 */
class NotificationTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_templates';

    protected $fillable = [
        'tenant_id',
        'event',
        'channel',
        'name',
        'subject',
        'body',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get system default template definitions.
     *
     * @return array<string, array{name: string, subject?: string|null, body: string}>
     */
    public static function getDefaultTemplates(): array
    {
        return [
            NotificationEvent::BOOKING_CREATED->value.'_'.NotificationChannel::WHATSAPP->value => [
                'name' => 'WhatsApp - Booking Dibuat',
                'subject' => null,
                'body' => "Halo {{customer.name}}, reservasi Anda di {{business.name}} telah kami terima!\n\nKode Booking: {{booking.code}}\nLayanan: {{service.name}}\nJadwal: {{booking.date}}, {{booking.time}}\nPetugas: {{staff.name}}\nTotal: {{booking.total}}\n\nUntuk mengelola atau mengubah jadwal reservasi Anda, silakan buka tautan berikut:\n{{manage_booking_url}}\n\nTerima kasih!",
            ],
            NotificationEvent::BOOKING_CREATED->value.'_'.NotificationChannel::EMAIL->value => [
                'name' => 'Email - Booking Dibuat',
                'subject' => 'Reservasi Anda di {{business.name}} [{{booking.code}}]',
                'body' => "Halo {{customer.name}},\n\nTerima kasih telah melakukan reservasi di {{business.name}}. Jadwal Anda telah kami catat dengan rincian sebagai berikut:\n\n• Kode Booking: {{booking.code}}\n• Layanan: {{service.name}}\n• Tanggal & Waktu: {{booking.date}}, {{booking.time}}\n• Petugas: {{staff.name}}\n• Lokasi: {{business.address}}\n• Total Biaya: {{booking.total}}\n• Status Pembayaran: {{payment.status}}\n\nKelola atau ubah jadwal reservasi Anda kapan saja melalui tautan:\n{{manage_booking_url}}\n\nSalam hangat,\n{{business.name}}",
            ],
            NotificationEvent::BOOKING_CONFIRMED->value.'_'.NotificationChannel::WHATSAPP->value => [
                'name' => 'WhatsApp - Booking Dikonfirmasi',
                'subject' => null,
                'body' => "Halo {{customer.name}}, kabar baik! Reservasi Anda [{{booking.code}}] untuk {{service.name}} pada {{booking.date}} pukul {{booking.time}} telah DIKONFIRMASI oleh {{business.name}}.\n\nPetugas: {{staff.name}}\nLokasi: {{business.address}}\n\nKelola reservasi: {{manage_booking_url}}\n\nSampai jumpa di lokasi!",
            ],
            NotificationEvent::BOOKING_CONFIRMED->value.'_'.NotificationChannel::EMAIL->value => [
                'name' => 'Email - Booking Dikonfirmasi',
                'subject' => 'Konfirmasi Reservasi: {{booking.code}} - {{business.name}}',
                'body' => "Halo {{customer.name}},\n\nReservasi Anda dengan kode {{booking.code}} telah resmi DIKONFIRMASI.\n\nLayanan: {{service.name}}\nWaktu: {{booking.date}}, pukul {{booking.time}}\nStaf: {{staff.name}}\nAlamat: {{business.address}}\n\nAnda dapat melihat detail reservasi atau check-in via tautan berikut:\n{{manage_booking_url}}\n\nTerima kasih telah mempercayakan layanan Anda kepada kami.",
            ],
            NotificationEvent::BOOKING_RESCHEDULED->value.'_'.NotificationChannel::WHATSAPP->value => [
                'name' => 'WhatsApp - Perubahan Jadwal (Reschedule)',
                'subject' => null,
                'body' => "Halo {{customer.name}}, jadwal reservasi Anda [{{booking.code}}] di {{business.name}} berhasil diubah.\n\nJadwal Baru: {{booking.date}}, {{booking.time}}\nLayanan: {{service.name}}\nPetugas: {{staff.name}}\n\nKelola jadwal: {{manage_booking_url}}\n\nTerima kasih atas konfirmasinya!",
            ],
            NotificationEvent::BOOKING_RESCHEDULED->value.'_'.NotificationChannel::EMAIL->value => [
                'name' => 'Email - Perubahan Jadwal (Reschedule)',
                'subject' => 'Perubahan Jadwal Berhasil: {{booking.code}} - {{business.name}}',
                'body' => "Halo {{customer.name}},\n\nPerubahan jadwal reservasi Anda telah berhasil disimpan.\n\n• Kode Booking: {{booking.code}}\n• Layanan: {{service.name}}\n• Jadwal Baru: {{booking.date}}, {{booking.time}}\n• Petugas: {{staff.name}}\n• Lokasi: {{business.address}}\n\nLihat rincian lengkap: {{manage_booking_url}}\n\nSalam,\n{{business.name}}",
            ],
            NotificationEvent::BOOKING_CANCELLED->value.'_'.NotificationChannel::WHATSAPP->value => [
                'name' => 'WhatsApp - Pembatalan Booking',
                'subject' => null,
                'body' => "Halo {{customer.name}}, reservasi Anda [{{booking.code}}] di {{business.name}} untuk {{service.name}} pada {{booking.date}} telah DIBATALKAN.\n\nJika ini adalah kekeliruan atau Anda ingin membuat jadwal baru, silakan kunjungi website kami.\n\nTerima kasih.",
            ],
            NotificationEvent::BOOKING_CANCELLED->value.'_'.NotificationChannel::EMAIL->value => [
                'name' => 'Email - Pembatalan Booking',
                'subject' => 'Pembatalan Reservasi: {{booking.code}} - {{business.name}}',
                'body' => "Halo {{customer.name}},\n\nKami mengonfirmasi bahwa reservasi Anda dengan kode {{booking.code}} di {{business.name}} telah DIBATALKAN.\n\nAlokasi waktu dan staf telah dilepaskan. Jika Anda membutuhkan reservasi di lain waktu, Anda dapat memesan kembali secara langsung.\n\nTerima kasih,\n{{business.name}}",
            ],
            NotificationEvent::BOOKING_REMINDER_H1->value.'_'.NotificationChannel::WHATSAPP->value => [
                'name' => 'WhatsApp - Pengingat Jadwal (H-1)',
                'subject' => null,
                'body' => "Halo {{customer.name}}, mengingatkan bahwa besok Anda memiliki jadwal reservasi di {{business.name}}!\n\nLayanan: {{service.name}}\nWaktu: {{booking.date}}, {{booking.time}}\nPetugas: {{staff.name}}\nLokasi: {{business.address}}\n\nHarap tiba 10 menit lebih awal. Tunjukkan kode booking {{booking.code}} saat tiba.\nDetail: {{manage_booking_url}}\n\nSampai jumpa besok!",
            ],
            NotificationEvent::BOOKING_REMINDER_H1->value.'_'.NotificationChannel::EMAIL->value => [
                'name' => 'Email - Pengingat Jadwal (H-1)',
                'subject' => 'Pengingat Reservasi Besok: {{service.name}} di {{business.name}}',
                'body' => "Halo {{customer.name}},\n\nIni adalah pengingat ramah untuk jadwal reservasi Anda besok di {{business.name}}:\n\n• Kode Booking: {{booking.code}}\n• Layanan: {{service.name}}\n• Waktu: {{booking.date}}, {{booking.time}}\n• Petugas: {{staff.name}}\n• Lokasi: {{business.address}}\n\nDetail lengkap & panduan lokasi: {{manage_booking_url}}\n\nSampai jumpa besok!",
            ],
        ];
    }
}

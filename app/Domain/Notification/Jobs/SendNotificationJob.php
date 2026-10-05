<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Business\Models\Business;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Mail\GenericNotificationMail;
use App\Domain\Notification\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public NotificationLog $notificationLog
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Don't re-send if already marked SENT
        if ($this->notificationLog->status === NotificationStatus::SENT) {
            return;
        }

        try {
            if ($this->notificationLog->channel === NotificationChannel::EMAIL) {
                $this->sendEmail();
            } elseif ($this->notificationLog->channel === NotificationChannel::WHATSAPP) {
                $this->sendWhatsApp();
            }

            $this->notificationLog->markSent();
        } catch (Throwable $e) {
            Log::warning("Notification failed for ID {$this->notificationLog->id}: " . $e->getMessage());
            $this->notificationLog->recordFailure($e->getMessage());
        }
    }

    protected function sendEmail(): void
    {
        $recipient = trim($this->notificationLog->recipient);
        if (empty($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Alamat email penerima tidak valid: '{$recipient}'");
        }

        $business = Business::withoutGlobalScopes()
            ->where('tenant_id', $this->notificationLog->tenant_id)
            ->first();

        // Extract action url from body if present
        $actionUrl = null;
        if (preg_match('/https?:\/\/[^\s]+/i', $this->notificationLog->body, $matches)) {
            $actionUrl = $matches[0];
        }

        $mailable = new GenericNotificationMail(
            subjectLine: $this->notificationLog->subject ?: 'Notifikasi Reservasi',
            bodyContent: $this->notificationLog->body,
            actionUrl: $actionUrl,
            businessName: $business?->name ?: 'Aman Booking',
        );

        Mail::to($recipient)->send($mailable);
    }

    protected function sendWhatsApp(): void
    {
        $recipient = trim($this->notificationLog->recipient);
        if (empty($recipient)) {
            throw new \InvalidArgumentException('Nomor WhatsApp penerima kosong.');
        }

        // WhatsApp dispatch stub for Phase 2.4 (wa.me notification logging)
        Log::info("WhatsApp notification sent to {$recipient} for event {$this->notificationLog->event->value}");
    }
}

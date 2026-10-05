<?php

namespace App\Domain\Notification\Services;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationEvent;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Jobs\SendNotificationJob;
use App\Domain\Notification\Models\NotificationLog;
use App\Domain\Notification\Models\NotificationTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function __construct(
        protected TemplateParser $templateParser
    ) {}

    /**
     * Dispatch notification for a booking event across all enabled channels.
     * Wrapped in a fail-safe try-catch so notification failures NEVER break booking business operations.
     */
    public function dispatchBookingNotification(
        Booking $booking,
        NotificationEvent $event,
        ?string $rawManageToken = null
    ): void {
        try {
            // Eager load relationships if needed
            if (! $booking->relationLoaded('customer')) {
                $booking->load('customer');
            }

            $variables = $this->templateParser->extractBookingVariables($booking, $rawManageToken);

            foreach ([NotificationChannel::EMAIL, NotificationChannel::WHATSAPP] as $channel) {
                $this->dispatchChannelNotification($booking, $event, $channel, $variables);
            }
        } catch (Throwable $e) {
            // Non-blocking failure: Log warning but do not propagate exception
            Log::error("Failed to dispatch booking notification for Booking ID {$booking->id}: " . $e->getMessage(), [
                'exception' => $e,
                'booking_id' => $booking->id,
                'event' => $event->value,
            ]);
        }
    }

    /**
     * Dispatch a single channel notification for a booking.
     *
     * @param  array<string, string>  $variables
     */
    protected function dispatchChannelNotification(
        Booking $booking,
        NotificationEvent $event,
        NotificationChannel $channel,
        array $variables
    ): void {
        // Resolve recipient
        $recipient = match ($channel) {
            NotificationChannel::EMAIL => $booking->customer?->email,
            NotificationChannel::WHATSAPP => $booking->customer?->phone_e164 ?: $booking->customer?->phone,
        };

        if (empty($recipient)) {
            return;
        }

        // Fetch or resolve template
        $template = NotificationTemplate::withoutGlobalScopes()
            ->where('tenant_id', $booking->tenant_id)
            ->where('event', $event->value)
            ->where('channel', $channel->value)
            ->first();

        // If template exists but is explicitly disabled, do not send
        if ($template && ! $template->is_active) {
            return;
        }

        // Get template content (either from DB or default)
        $defaultKey = $event->value . '_' . $channel->value;
        $defaults = NotificationTemplate::getDefaultTemplates();
        $defaultData = $defaults[$defaultKey] ?? null;

        $subjectTemplate = $template?->subject ?? ($defaultData['subject'] ?? null);
        $bodyTemplate = $template?->body ?? ($defaultData['body'] ?? null);

        if (empty($bodyTemplate)) {
            return;
        }

        $hydratedSubject = $subjectTemplate !== null ? $this->templateParser->parse($subjectTemplate, $variables) : null;
        $hydratedBody = $this->templateParser->parse($bodyTemplate, $variables);

        // Create log record
        /** @var NotificationLog $log */
        $log = NotificationLog::withoutGlobalScopes()->create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'channel' => $channel,
            'event' => $event,
            'recipient' => $recipient,
            'subject' => $hydratedSubject,
            'body' => $hydratedBody,
            'status' => NotificationStatus::PENDING,
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        // Dispatch queued job
        SendNotificationJob::dispatch($log);
    }

    /**
     * Manually retry a failed or dead-letter notification log.
     */
    public function retry(NotificationLog $log): bool
    {
        $log->update([
            'status' => NotificationStatus::PENDING,
            'next_retry_at' => null,
            'last_error' => null,
        ]);

        SendNotificationJob::dispatch($log);

        return true;
    }

    /**
     * Process due automatic retries for failed notifications.
     */
    public function processDueRetries(): int
    {
        $dueLogs = NotificationLog::withoutGlobalScopes()
            ->where('status', NotificationStatus::FAILED->value)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', Carbon::now())
            ->where('attempts', '<', 5)
            ->get();

        $count = 0;
        foreach ($dueLogs as $log) {
            $log->update([
                'status' => NotificationStatus::PENDING,
                'next_retry_at' => null,
            ]);

            SendNotificationJob::dispatch($log);
            $count++;
        }

        return $count;
    }

    /**
     * Send H-1 reminders for upcoming bookings tomorrow.
     * Idempotent: Skips if notification log already exists for BOOKING_REMINDER_H1.
     */
    public function sendReminders(): int
    {
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();

        // Fetch bookings scheduled for tomorrow with active status
        $upcomingBookings = Booking::withoutGlobalScopes()
            ->whereIn('status_category', [
                BookingStatusCategory::CONFIRMED->value,
                BookingStatusCategory::PENDING->value,
            ])
            ->whereBetween('start_at', [$tomorrowStart, $tomorrowEnd])
            ->with(['customer', 'service', 'allocations.resource.resourceType'])
            ->get();

        $count = 0;
        foreach ($upcomingBookings as $booking) {
            // Check idempotency: already dispatched H-1 reminder for this booking?
            $alreadyReminded = NotificationLog::withoutGlobalScopes()
                ->where('booking_id', $booking->id)
                ->where('event', NotificationEvent::BOOKING_REMINDER_H1->value)
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            $this->dispatchBookingNotification($booking, NotificationEvent::BOOKING_REMINDER_H1);
            $count++;
        }

        return $count;
    }

    /**
     * Ensure all default templates are initialized in DB for a tenant.
     */
    public function ensureTenantTemplates(int $tenantId): void
    {
        $defaults = NotificationTemplate::getDefaultTemplates();

        foreach ($defaults as $key => $data) {
            [$eventVal, $channelVal] = explode('_', $key, 2);

            NotificationTemplate::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event' => $eventVal,
                    'channel' => $channelVal,
                ],
                [
                    'name' => $data['name'],
                    'subject' => $data['subject'] ?? null,
                    'body' => $data['body'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Reset a notification template back to system default.
     */
    public function resetTemplateToDefault(NotificationTemplate $template): NotificationTemplate
    {
        $defaults = NotificationTemplate::getDefaultTemplates();
        $key = $template->event . '_' . $template->channel;

        if (isset($defaults[$key])) {
            $default = $defaults[$key];
            $template->update([
                'name' => $default['name'],
                'subject' => $default['subject'] ?? null,
                'body' => $default['body'],
                'is_active' => true,
            ]);
        }

        return $template;
    }
}

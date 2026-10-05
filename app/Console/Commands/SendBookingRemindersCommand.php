<?php

namespace App\Console\Commands;

use App\Domain\Notification\Services\NotificationService;
use Illuminate\Console\Command;

class SendBookingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send H-1 reminder notifications for upcoming bookings tomorrow (idempotent)';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Checking upcoming bookings for tomorrow H-1 reminders...');

        $count = $notificationService->sendReminders();

        $this->info("Dispatched {$count} H-1 booking reminder notification(s).");

        return Command::SUCCESS;
    }
}

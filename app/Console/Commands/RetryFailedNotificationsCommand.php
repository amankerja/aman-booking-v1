<?php

namespace App\Console\Commands;

use App\Domain\Notification\Services\NotificationService;
use Illuminate\Console\Command;

class RetryFailedNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:retry-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process automatic exponential-backoff retries for failed notifications';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Checking for due failed notifications to retry...');

        $count = $notificationService->processDueRetries();

        $this->info("Processed and redispatched {$count} due failed notification(s).");

        return Command::SUCCESS;
    }
}

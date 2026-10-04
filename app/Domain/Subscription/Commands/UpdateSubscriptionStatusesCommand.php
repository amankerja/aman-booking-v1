<?php

namespace App\Domain\Subscription\Commands;

use App\Domain\Subscription\Jobs\UpdateSubscriptionStatusesJob;
use Illuminate\Console\Command;

class UpdateSubscriptionStatusesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate subscription dates and transition statuses (TRIAL, PAST_DUE, GRACE_PERIOD, EXPIRED)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Evaluating subscription statuses...');

        UpdateSubscriptionStatusesJob::dispatchSync();

        $this->info('Subscription statuses updated successfully.');

        return Command::SUCCESS;
    }
}

<?php

namespace App\Domain\Subscription\Jobs;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateSubscriptionStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now = now();

        // 1. Expire past TRIAL subscriptions
        $expiredTrials = Subscription::withoutGlobalScopes()
            ->where('status', 'TRIAL')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        foreach ($expiredTrials as $subscription) {
            if ($subscription->grace_ends_at && $subscription->grace_ends_at > $now) {
                $subscription->update(['status' => 'GRACE_PERIOD']);
            } else {
                $subscription->update(['status' => 'EXPIRED']);
            }
        }

        // 2. Transition overdue ACTIVE subscriptions
        $overdueActives = Subscription::withoutGlobalScopes()
            ->where('status', 'ACTIVE')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->get();

        foreach ($overdueActives as $subscription) {
            if ($subscription->grace_ends_at && $subscription->grace_ends_at > $now) {
                $subscription->update(['status' => 'GRACE_PERIOD']);
            } elseif ($subscription->grace_ends_at && $subscription->grace_ends_at <= $now) {
                $subscription->update(['status' => 'EXPIRED']);
            } else {
                $subscription->update(['status' => 'PAST_DUE']);
            }
        }

        // 3. Expire overdue GRACE_PERIOD subscriptions
        $expiredGraces = Subscription::withoutGlobalScopes()
            ->where('status', 'GRACE_PERIOD')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', $now)
            ->get();

        foreach ($expiredGraces as $subscription) {
            $subscription->update(['status' => 'EXPIRED']);
        }

        // 4. Expire overdue PAST_DUE subscriptions if grace_ends_at passed
        $expiredPastDues = Subscription::withoutGlobalScopes()
            ->where('status', 'PAST_DUE')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', $now)
            ->get();

        foreach ($expiredPastDues as $subscription) {
            $subscription->update(['status' => 'EXPIRED']);
        }
    }
}

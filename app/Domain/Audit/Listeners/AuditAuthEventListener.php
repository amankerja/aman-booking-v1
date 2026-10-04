<?php

namespace App\Domain\Audit\Listeners;

use App\Domain\Identity\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AuditAuthEventListener
{
    /**
     * Handle user login event.
     */
    public function handleLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        Audit::record([
            'action' => 'user.login',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'before' => null,
            'after' => [
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Handle user logout event.
     */
    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        Audit::record([
            'action' => 'user.logout',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'before' => null,
            'after' => [
                'email' => $user->email,
            ],
        ]);
    }
}

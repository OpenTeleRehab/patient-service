<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Auth;

/**
 * Class LoginEvent
 * @package App\Listeners
 */
class LoginEvent
{

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle()
    {
        $user = Auth::user();

        $user->update([
            'last_login' => now(),
            'daily_logins' => now()->diffInDays($user->last_login) === 1 ? $user->daily_logins + 1 : 1
        ]);
    }
}

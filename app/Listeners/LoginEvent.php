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

        if (now()->diffInDays($user->last_login) === 0 && $user->init_daily_logins > 0) {
            $init_daily_logins = $user->init_daily_logins;
        } else if (now()->diffInDays($user->last_login) === 1) {
            $init_daily_logins = $user->init_daily_logins + 1;
        } else {
            $init_daily_logins = 1;
        }

        $user->update([
            'last_login' => now(),
            'init_daily_logins' => $init_daily_logins,
            'daily_logins' => $init_daily_logins > $user->daily_logins ? $init_daily_logins : $user->daily_logins,
        ]);
    }
}

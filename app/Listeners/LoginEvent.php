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
        ]);
    }
}

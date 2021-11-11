<?php

namespace App\Listeners;

use App\Models\Activity;
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
        $init_daily_tasks = $user->init_daily_tasks;
        $init_daily_answers = $user->init_daily_answers;

        if (now()->diffInDays($user->last_login) === 0 && $user->init_daily_logins > 0) {
            $init_daily_logins = $user->init_daily_logins;
        } elseif (now()->diffInDays($user->last_login) === 1) {
            $init_daily_logins = $user->init_daily_logins + 1;
        } else {
            $init_daily_logins = 1;
        }

        $lastTaskSubmitted = Activity::where('type', '<>', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->orderBy('submitted_date', 'DESC')
            ->first();

        if (!empty($lastTaskSubmitted) && now()->diffInDays($lastTaskSubmitted->submitted_date->format('Y-m-d')) > 1) {
            $init_daily_tasks = 0;
        }

        $lastAnswerSubmitted = Activity::where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->orderBy('submitted_date', 'DESC')
            ->first();

        if (!empty($lastAnswerSubmitted) && now()->diffInDays($lastAnswerSubmitted->submitted_date->format('Y-m-d')) > 1) {
            $init_daily_answers = 0;
        }

        $user->update([
            'last_login' => now(),
            'init_daily_logins' => $init_daily_logins,
            'init_daily_tasks' => $init_daily_tasks,
            'init_daily_answers' => $init_daily_answers,
            'daily_logins' => $init_daily_logins > $user->daily_logins ? $init_daily_logins : $user->daily_logins,
        ]);
    }
}

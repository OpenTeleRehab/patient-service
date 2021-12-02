<?php

namespace App\Listeners;

use App\Models\Activity;
use Carbon\Carbon;
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
        $init_daily_logins = $user->init_daily_logins;
        $init_daily_tasks = $user->init_daily_tasks;
        $init_daily_answers = $user->init_daily_answers;

        $now = Carbon::now();
        $lastTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $now)
            ->orderBy('start_date', 'DESC')
            ->first();

        $hasTaskToday = $this->hasUncompletedTask($user);
        $hasUncompletedTaskLastLogin = $this->hasUncompletedTask($user, $user->last_login);
        if ($hasUncompletedTaskLastLogin) {
            $init_daily_logins = 1;
        } elseif (now()->diffInDays(Carbon::parse($user->last_login)) > 0 && $hasTaskToday) {
            $init_daily_logins = $user->init_daily_logins + 1;
        }

        $lastTaskSubmitted = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)
            ->where('type', '<>', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->orderBy('submitted_date', 'DESC')
            ->first();
        if ($lastTaskSubmitted) {
            $hasUncompletedTask = $this->hasUncompletedTask($user, $lastTaskSubmitted->submitted_date);
            if (now()->diffInDays($lastTaskSubmitted->submitted_date->format('Y-m-d')) > 0 && $hasUncompletedTask) {
                $init_daily_tasks = 0;
            }
        }

        $lastAnswerSubmitted = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)
            ->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->orderBy('submitted_date', 'DESC')
            ->first();
        if ($lastAnswerSubmitted) {
            $hasUncompletedAnswer = $this->hasUncompletedTask($user, $lastAnswerSubmitted->submitted_date);
            if (now()->diffInDays($lastAnswerSubmitted->submitted_date->format('Y-m-d')) > 0 && $hasUncompletedAnswer) {
                $init_daily_answers = 0;
            }
        }

        $user->update([
            'last_login' => now(),
            'init_daily_logins' => $init_daily_logins,
            'init_daily_tasks' => $init_daily_tasks,
            'init_daily_answers' => $init_daily_answers,
            'daily_logins' => $init_daily_logins > $user->daily_logins ? $init_daily_logins : $user->daily_logins,
        ]);
    }

    /**
     * @param $lastDay
     * @param $user
     * @return bool
     */
    public function hasUncompletedTask($user, $lastDay = null) {
        $now = Carbon::now();
        $lastTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $now)
            ->orderBy('start_date', 'DESC')
            ->first();
        $hasUncompletedTask = false;
        if ($lastTreatmentPlan) {
            $uncompletedTasks = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)->where('completed', 0)->get();
            foreach ($uncompletedTasks as $activity) {
                $numberDay = $activity->day + (($activity->week - 1) * 7);
                $numberDay -= 1;
                $taskDate = Carbon::parse($lastTreatmentPlan->start_date)->addDays($numberDay);
                if (!is_null($lastDay)) {
                    $lastDay = Carbon::parse($lastDay);
                    if ($taskDate < $now && $taskDate > $lastDay) {
                        $hasUncompletedTask = true;
                        break;
                    }
                } else {
                    if ($taskDate->startOfDay() == $now->startOfDay()) {
                        $hasUncompletedTask = true;
                        break;
                    }
                }

            }
        }
        return $hasUncompletedTask;
    }
}

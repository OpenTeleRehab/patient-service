<?php

namespace App\Listeners;

use App\Events\LoginEvent;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Class InitBadge
 * @package App\Listeners
 */
class InitBadge
{
    /**
     * Handle the event.
     *
     * @param LoginEvent $loginEvent
     *
     * @return void
     */
    public function handle(LoginEvent $loginEvent)
    {
        $user = Auth::user();
        $init_daily_logins = $user->init_daily_logins;
        $init_daily_tasks = $user->init_daily_tasks;
        $init_daily_answers = $user->init_daily_answers;
        $timezone = $loginEvent->request['timezone'] ?? config('app.timezone', 'UTC');
        $nowLocal = Carbon::now($timezone);
        $nowUTC = Carbon::now(config('app.timezone'));
        $lastLogin = Carbon::parse($user->last_login, $timezone)
            ->setTimezone($timezone)
            ->format('Y-m-d');

        $hasTaskSinceLastLogin = $this->hasTaskSinceLastLogin($user, $nowLocal, $timezone);
        $hasTaskToday = $this->hasTaskToday($user, $nowLocal);
        if ($nowLocal->diffInDays($lastLogin) > 1 && $hasTaskSinceLastLogin) {
            $init_daily_logins = 1;
        } elseif ($nowLocal->diffInDays($lastLogin) > 0 && $hasTaskToday) {
            $init_daily_logins = $user->init_daily_logins + 1;
        }

        $lastTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $nowLocal)
            ->orderBy('start_date', 'DESC')
            ->first();
        if ($lastTreatmentPlan) {
            $lastTaskSubmitted = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)
                ->where('type', '<>', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                ->where('completed', 1)
                ->orderBy('submitted_date', 'DESC')
                ->first();
            if ($lastTaskSubmitted) {
                $lastSubmittedDate = Carbon::parse($lastTaskSubmitted->submitted_date, config('app.timezone'))
                    ->setTimezone($timezone);
                $hasUncompletedTask = $this->hasUncompletedTask($user, $nowLocal, '<>', $lastSubmittedDate);
                if ($nowLocal->diffInDays($lastSubmittedDate->format('Y-m-d')) > 0 && $hasUncompletedTask) {
                    $init_daily_tasks = 0;
                }
            }

            $lastAnswerSubmitted = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)
                ->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                ->where('completed', 1)
                ->orderBy('submitted_date', 'DESC')
                ->first();
            if ($lastAnswerSubmitted) {
                $lastSubmittedDate = Carbon::parse($lastAnswerSubmitted->submitted_date, config('app.timezone'))
                    ->setTimezone($timezone);
                $hasUncompletedAnswer = $this->hasUncompletedTask($user, $nowLocal, '=', $lastSubmittedDate);
                if ($nowLocal->diffInDays($lastSubmittedDate->format('Y-m-d')) > 0 && $hasUncompletedAnswer) {
                    $init_daily_answers = 0;
                }
            }
        }

        $user->update([
            'last_login' => $nowUTC,
            'init_daily_logins' => $init_daily_logins,
            'init_daily_tasks' => $init_daily_tasks,
            'init_daily_answers' => $init_daily_answers,
            'daily_logins' => $init_daily_logins > $user->daily_logins ? $init_daily_logins : $user->daily_logins,
        ]);
    }

    /**
     * @param \App\Models\User $user
     * @param \Illuminate\Support\Facades\Date $nowLocal
     * @param string $isQuestionnaire
     * @param \Illuminate\Support\Facades\Date $lastDay
     * @return bool
     */
    public function hasUncompletedTask($user, $nowLocal, $isQuestionnaire, $lastDay)
    {
        $lastTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $nowLocal)
            ->orderBy('start_date', 'DESC')
            ->first();
        $hasUncompletedTask = false;
        if ($lastTreatmentPlan) {
            $taskFromLastLogin = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)
                ->where('type', $isQuestionnaire, Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                ->whereDate('submitted_date', '>=', $lastDay)
                ->get();
            $completedTask = [];
            foreach ($taskFromLastLogin as $task) {
                $numberDay = $task->day + (($task->week - 1) * 7);
                $numberDay -= 1;
                $taskDate = Carbon::parse($lastTreatmentPlan->start_date)->addDays($numberDay)->format('Y-m-d');
                if (!isset($completedTask[$taskDate])) {
                    $completedTask[$taskDate] = 0;
                }
                if ($task->completed == 1) {
                    $completedTask[$taskDate] += 1;
                } else {
                    $completedTask[$taskDate] += 0;
                }
            }
            if (in_array(0, $completedTask)) {
                $hasUncompletedTask = true;
            }
        }
        return $hasUncompletedTask;
    }

    /**
     * @param \App\Models\User $user
     * @param \Illuminate\Support\Facades\Date $nowLocal
     * @param \Illuminate\Support\Facades\Date $timezone
     * @return bool
     */
    public function hasTaskSinceLastLogin($user, $nowLocal, $timezone)
    {
        $lastTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $nowLocal)
            ->orderBy('start_date', 'DESC')
            ->first();

        $hasTask = false;

        if ($lastTreatmentPlan) {
            $tasks = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)
                ->whereDate('submitted_date', '>=', Carbon::parse($user->last_login)->setTimezone($timezone))
                ->whereDate('submitted_date', '<=', $nowLocal)
                ->get();
            if ($tasks->count() > 0) {
                $hasTask = true;
            }
        }
        return $hasTask;
    }

    /**
     * @param \App\Models\User $user
     * @param \Illuminate\Support\Facades\Date $nowLocal
     * @return bool
     */
    public function hasTaskToday($user, $nowLocal)
    {
        $lastTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $nowLocal)
            ->orderBy('start_date', 'DESC')
            ->first();

        $hasTask = false;

        if ($lastTreatmentPlan) {
            $tasks = Activity::where('treatment_plan_id', $lastTreatmentPlan->id)->get();
            foreach ($tasks as $task) {
                $numberDay = $task->day + (($task->week - 1) * 7);
                $numberDay -= 1;
                $taskDate = Carbon::parse($lastTreatmentPlan->start_date)->addDays($numberDay);
                if ($taskDate >= $nowLocal->startOfDay() && $taskDate <= $nowLocal->endOfDay()) {
                    $hasTask = true;
                    break;
                }
            }
        }

        return $hasTask;
    }
}

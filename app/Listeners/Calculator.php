<?php

namespace App\Listeners;

use App\Events\PodcastCalculatorEvent;
use App\Models\Activity;
use App\Models\TreatmentPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Calculator
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  PodcastCalculatorEvent $event
     *
     * @return void
     */
    public function handle(PodcastCalculatorEvent $event)
    {
        $painThresholdLimit = Http::get(env('ADMIN_SERVICE_URL') . '/api/setting/library-limit', [
            'type' => TreatmentPlan::PAIN_THRESHOLD_LIMIT,
        ]);

        $activity = Activity::find($event->activity['id']);

        $ongoingTreatmentPlan = TreatmentPlan::find($activity->treatment_plan_id);

        $dateDiff = intval(date_diff(date_create($ongoingTreatmentPlan->start_date), date_create(Carbon::now()->format('Y-m-d')), true)->format('%d'));
        $totalPainThreshold = 0;
        $totalCompleted = 0;

        $activities = $ongoingTreatmentPlan->activities()
            ->whereRaw('day + ((week - 1) * 7) <= ?', [$dateDiff])
            ->orderByDesc('week')
            ->orderByDesc('day')
            ->get();

        $day = null;
        $week = null;
        $totalDay = 0;

        foreach ($activities as $activity) {
            if ($activity->day !== $day && $activity->week !== $week) {
                $day = $activity->day;
                $week = $activity->week;
                $totalDay += 1;
            }

            $totalCompleted += $activity->completed ? 1 : 0;

            if ($totalDay >= 3) {
                exit();
            }
        }

        $day = null;
        $week = null;
        $totalDay = 0;

        $exerciseActivities = $ongoingTreatmentPlan->activities()
            ->where('type', Activity::ACTIVITY_TYPE_EXERCISE)
            ->whereRaw('day + ((week - 1) * 7) <= ?', [$dateDiff])
            ->orderByDesc('week')
            ->orderByDesc('day')
            ->get();

        foreach ($exerciseActivities as $exerciseActivity) {
            if ($activity->day !== $day && $activity->week !== $week) {
                $day = $activity->day;
                $week = $activity->week;
                $totalDay += 1;
            }

            if ($activity->pain_level > (int)$painThresholdLimit->body()) {
                $totalPainThreshold += 1;
            }

            if ($totalDay >= 3) {
                exit();
            }
        }

        $totalCompletedPercent = $activities->count() > 0 ? ($totalCompleted / $activities->count()) * 100 : 0;

        User::find($ongoingTreatmentPlan->patient_id)->update(['completed_percent' => $totalCompletedPercent, 'total_pain_threshold' => $totalPainThreshold]);
    }
}

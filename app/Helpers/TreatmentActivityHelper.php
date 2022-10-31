<?php

namespace App\Helpers;

use App\Http\Resources\QuestionnaireAnswerResource;
use App\Models\Activity;
use App\Models\Forwarder;
use App\Models\Goal;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

class TreatmentActivityHelper
{
    /**
     * @param \App\Models\TreatmentPlan $treatmentPlan
     * @param \Illuminate\Http\Request $request
     * @param false $includedGoals
     *
     * @return array
     */
    public static function getActivities(TreatmentPlan $treatmentPlan, Request $request, $includedGoals = false)
    {
        $result = [];
        $activities = $treatmentPlan->activities->sortBy(function ($activity) {
            return [$activity->week, $activity->day];
        });

        $previousActivity = $activities ? $activities->first() : null;
        foreach ($activities as $key => $activity) {
            $date = $treatmentPlan->start_date->modify('+' . ($activity->week - 1) . ' week')
                ->modify('+' . ($activity->day - 1) . ' day')
                ->format(config('settings.defaultTimestampFormat'));

            $activityObj = [];
            $response = null;
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);

            if ($activity->type === Activity::ACTIVITY_TYPE_EXERCISE) {
                $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/exercise/list/by-ids', [
                    'exercise_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang'),
                    'therapist_id' => $request->get('therapist_id')
                ]);
            } elseif ($activity->type === Activity::ACTIVITY_TYPE_MATERIAL) {
                $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/education-material/list/by-ids', [
                    'material_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang'),
                    'therapist_id' => $request->get('therapist_id')
                ]);
            } elseif ($activity->type === Activity::ACTIVITY_TYPE_QUESTIONNAIRE) {
                $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/questionnaire/list/by-ids', [
                    'questionnaire_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang'),
                    'therapist_id' => $request->get('therapist_id')
                ]);
            } else {
                $goal = Goal::find($activity->activity_id);
                if ($goal) {
                    $activityObj = [
                        'id' => $activity->id,
                        'title' => $goal->title,
                        'frequency' => $goal->frequency,
                    ];
                }
            }

            if (!empty($response) && $response->successful()) {
                if ($response->json()['data']) {
                    $activityObj = $response->json()['data'][0];
                    $activityObj['id'] = $activity->id;

                    // Custom Sets/Reps in Treatment.
                    if ($activity->sets !== null) {
                        $activityObj['sets'] = $activity->sets;
                    }
                    if ($activity->reps !== null) {
                        $activityObj['reps'] = $activity->reps;
                    }
                } else {
                    continue;
                }
            }

            $result[] = array_merge([
                'date' => $date,
                'created_by' => $activity->created_by,
                'activity_id' => $activity->activity_id,
                'completed' => $activity->completed,
                'pain_level' => $activity->pain_level,
                'completed_sets' => $activity->completed_sets,
                'completed_reps' => $activity->completed_reps,
                'satisfaction' => $activity->satisfaction,
                'type' => $activity->type,
                'submitted_date' => $activity->submitted_date,
                'answers' => QuestionnaireAnswerResource::collection($activity->answers),
                'week' => $activity->week,
                'day' => $activity->day,
                'additional_information' => $activity->additional_information,
            ], $activityObj);

            if ($includedGoals) {
                $dailyGoals = $treatmentPlan->goals->where('frequency', 'daily')->all();
                $weeklyGoals = $treatmentPlan->goals->where('frequency', 'weekly')->all();
            }

            // Add daily goals.
            if (!empty($dailyGoals)) {
                if ($previousActivity->day !== $activity->day || $previousActivity->week !== $activity->week) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($previousActivity->week - 1) . ' week')
                        ->modify('+' . ($previousActivity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    self::addGoals($dailyGoals, $activities, $previousActivity, $previousDate, 'daily', $result);
                }

                if ($key === array_key_last($activities->toArray())) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($activity->week - 1) . ' week')
                        ->modify('+' . ($activity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    self::addGoals($dailyGoals, $activities, $activity, $previousDate, 'daily', $result);
                }
            }

            // Add weekly goals.
            if (!empty($weeklyGoals)) {
                if ($previousActivity->week !== $activity->week) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($previousActivity->week - 1) . ' week')
                        ->modify('+' . ($previousActivity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    self::addGoals($weeklyGoals, $activities, $previousActivity, $previousDate, 'weekly', $result);
                }

                if ($key === array_key_last($activities->toArray())) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($activity->week - 1) . ' week')
                        ->modify('+' . ($activity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    self::addGoals($weeklyGoals, $activities, $activity, $previousDate, 'weekly', $result);
                }
            }
            $previousActivity = clone $activity;
        }

        return $result;
    }

    /**
     * @param array $goals
     * @param array $activities
     * @param array $previousActivity
     * @param Date $previousDate
     * @param string  $frequency
     * @param array $result
     *
     * @return void
     */
    private static function addGoals($goals, $activities, $previousActivity, $previousDate, $frequency, &$result)
    {
        foreach ($goals as $goal) {
            $completed = $activities->where('type', Activity::ACTIVITY_TYPE_GOAL)
                ->where('activity_id', $goal->id)
                ->where('week', $previousActivity->week)
                ->where('day', $previousActivity->day)
                ->count();
            if (!$completed) {
                $result[] = [
                    'date' => $previousDate,
                    'activity_id' => $goal->id,
                    'title' => $goal->title,
                    'completed' => false,
                    'type' => Activity::ACTIVITY_TYPE_GOAL,
                    'frequency' => $frequency,
                    'week' => $previousActivity->week,
                    'day' => $previousActivity->day,
                    'treatment_plan_id' => $previousActivity->treatment_plan_id,
                ];
            }
        }
    }
}

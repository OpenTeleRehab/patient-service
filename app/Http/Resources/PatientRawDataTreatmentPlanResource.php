<?php

namespace App\Http\Resources;

use App\Models\Activity;
use App\Models\Goal;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientRawDataTreatmentPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        // Get all activities once and organize them efficiently
        $activities = $this->activities;

        // Calculate last day of treatment once
        $lastDayOfTreatment = $activities
            ->where('week', $this->total_of_weeks)
            ->where('treatment_plan_id', $this->id)
            ->max('day');

        // Pre-filter activities by type to avoid repeated filtering
        $exerciseActivities = $activities->where('type', 'exercise');
        $goalActivities = $activities->where('type', 'goal');
        $questionnaireActivities = $activities->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE);

        // Get goal IDs once
        $dailyGoalIds = $this->goals->where('frequency', Goal::FREQUENCY_DAILY)->pluck('id');
        $weeklyGoalIds = $this->goals->where('frequency', Goal::FREQUENCY_WEEKLY)->pluck('id');

        // Pre-filter goal activities by type
        $dailyGoalActivities = $goalActivities->whereIn('activity_id', $dailyGoalIds);
        $weeklyGoalActivities = $goalActivities->whereIn('activity_id', $weeklyGoalIds);

        // Calculate initial values (week 1, day 1)
        $initialExercises = $exerciseActivities->where('day', 1)->where('week', 1);
        $initialDailyGoals = $dailyGoalActivities->where('day', 1)->where('week', 1);
        $initialWeeklyGoals = $weeklyGoalActivities->where('week', 1);

        // Calculate final values (last week, last day)
        $finalExercises = $exerciseActivities
            ->where('day', $lastDayOfTreatment)
            ->where('week', $this->total_of_weeks);
        $finalDailyGoals = $dailyGoalActivities
            ->where('day', $lastDayOfTreatment)
            ->where('week', $this->total_of_weeks);
        $finalWeeklyGoals = $weeklyGoalActivities->where('week', $this->total_of_weeks);

        // Process questionnaires efficiently
        $questionnaires = $questionnaireActivities
            ->where('completed', 1)
            ->sortBy(['week', 'day'])
            ->map(function ($questionnaire) {
                return [
                    'id' => $questionnaire->activity_id,
                    'week' => $questionnaire->week,
                    'day' => $questionnaire->day,
                    'answer' => QuestionnaireAnswerResource::collection($questionnaire->answers),
                ];
            });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'patient_id' => $this->patient_id,
            'start_date' => $this->start_date?->format(config('settings.date_format')) ?? '',
            'end_date' => $this->end_date?->format(config('settings.date_format')) ?? '',
            'status' => $this->status,
            'total_of_weeks' => $this->total_of_weeks,
            'created_by' => $this->created_by,
            'disease_id' => $this->disease_id,
            'questionnaires' => $questionnaires,

            // Average calculations using pre-filtered collections
            'averageWeeklyGoal' => $weeklyGoalActivities->avg('satisfaction'),
            'averageDailyGoal' => $dailyGoalActivities->avg('satisfaction'),

            // Initial values
            'initialAverageAdherence' => $initialExercises->avg('completed'),
            'initialAveragePainLevel' => $initialExercises->avg('pain_level'),
            'initialAverageDailyGoal' => $initialDailyGoals->avg('satisfaction'),
            'initialAverageWeeklyGoal' => $initialWeeklyGoals->avg('satisfaction'),

            // Final values
            'finalAverageAdherence' => $finalExercises->avg('completed'),
            'finalAveragePainLevel' => $finalExercises->avg('pain_level'),
            'finalAverageDailyGoal' => $finalDailyGoals->avg('satisfaction'),
            'finalAverageWeeklyGoal' => $finalWeeklyGoals->avg('satisfaction'),
        ];
    }
}

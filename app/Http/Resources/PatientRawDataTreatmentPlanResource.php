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
        $lastDayOfTreatment = $this->activities()
        ->where('week', $this->total_of_weeks)
        ->where('treatment_plan_id', $this->id)
        ->max('day');

        $initialAverageAdherence = $this->activities()
        ->where('type', 'exercise')
        ->where('day', 1)
        ->where('week', 1)
        ->avg('completed');

        $initialAveragePainLevel = $this->activities()
        ->where('type', 'exercise')
        ->where('day', 1)
        ->where('week', 1)
        ->avg('pain_level');

        $finalAverageAdherence = $this->activities()
        ->where('type', 'exercise')
        ->where('day', $lastDayOfTreatment)
        ->where('week', $this->total_of_weeks)
        ->avg('completed');

        $finalAveragePainLevel = $this->activities()
        ->where('type', 'exercise')
        ->where('day', $lastDayOfTreatment)
        ->where('week', $this->total_of_weeks)
        ->avg('pain_level');
        
        $dailyGoalIds = $this->goals()->where('frequency', Goal::FREQUENCY_DAILY)->pluck('id')->toArray();
        $weeklyGoalIds = $this->goals()->where('frequency', Goal::FREQUENCY_WEEKLY)->pluck('id')->toArray();

        $initialAverageDailyGoal = $this->activities()
        ->where('type', 'goal')
        ->whereIn('activity_id', $dailyGoalIds)
        ->where('day', 1)
        ->where('week', 1)
        ->avg('satisfaction');

        $finalAverageDailyGoal = $this->activities()
        ->where('type', 'goal')
        ->whereIn('activity_id', $dailyGoalIds)
        ->where('day', $lastDayOfTreatment)
        ->where('week', $this->total_of_weeks)
        ->avg('satisfaction');

        $initialAverageWeeklyGoal = $this->activities()
        ->where('type', 'goal')
        ->whereIn('activity_id', $weeklyGoalIds)
        ->where('week', 1)
        ->avg('satisfaction');

        $finalAverageWeeklyGoal = $this->activities()
        ->where('type', 'goal')
        ->whereIn('activity_id', $weeklyGoalIds)
        ->where('week', $this->total_of_weeks)
        ->avg('satisfaction');

        $averageWeeklyGoal = $this->activities()
        ->where('type', 'goal')
        ->whereIn('activity_id', $weeklyGoalIds)
        ->avg('satisfaction');

        $averageDailyGoal = $this->activities()
        ->where('type', 'goal')
        ->whereIn('activity_id',$dailyGoalIds)
        ->avg('satisfaction');

        $questionnaires = $this->activities()
            ->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->orderBy('week')
            ->orderBy('day')
            ->get();

        $questionnaires = $questionnaires->map(function ($questionnaire) {
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
            'start_date' => $this->start_date ? $this->start_date->format(config('settings.date_format')) : '',
            'end_date' => $this->end_date ? $this->end_date->format(config('settings.date_format')) : '',
            'status' => $this->status,
            'averageWeeklyGoal' => $averageWeeklyGoal,
            'averageDailyGoal' => $averageDailyGoal,
            'total_of_weeks' => $this->total_of_weeks,
            'created_by' => $this->created_by,
            'disease_id' => $this->disease_id,
            'questionnaires' => $questionnaires,
            'initialAverageAdherence' => $initialAverageAdherence,
            'initialAveragePainLevel' => $initialAveragePainLevel,
            'finalAverageAdherence' => $finalAverageAdherence,
            'finalAveragePainLevel' => $finalAveragePainLevel,
            'initialAverageDailyGoal' => $initialAverageDailyGoal,
            'initialAverageWeeklyGoal' => $initialAverageWeeklyGoal,
            'finalAverageDailyGoal' => $finalAverageDailyGoal,
            'finalAverageWeeklyGoal' => $finalAverageWeeklyGoal,
        ];
    }
}

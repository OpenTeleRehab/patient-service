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
        $activities = $this->activities()
        ->selectRaw('
            day,
            week,
            COUNT(*) as number_of_exercise, 
            SUM(completed) as number_of_completed_exercise, 
            SUM(pain_level) as total_pain_level,
            SUM(CASE WHEN pain_level IS NOT NULL THEN 1 ELSE 0 END) as number_of_submitted_pain_level
        ')
        ->where('type', 'exercise')
        ->groupBy('day')
        ->groupBy('week')
        ->get();
        
        $dailyGoalIds = $this->goals()->where('frequency', Goal::FREQUENCY_DAILY)->pluck('id')->toArray();
        $weeklyGoalIds = $this->goals()->where('frequency', Goal::FREQUENCY_WEEKLY)->pluck('id')->toArray();
        $weeklyGoals = $this->activities()
        ->selectRaw('
            week,
            ANY_VALUE(satisfaction) as satisfaction,
            SUM(completed) as number_of_submitted_goal
        ')
        ->where('type', 'goal')
        ->whereIn('activity_id', $weeklyGoalIds)
        ->groupBy('week')
        ->get();

        $dailyGoals = $this->activities()
        ->selectRaw('
            day,
            ANY_VALUE(satisfaction) as satisfaction,
            SUM(completed) as number_of_submitted_goal
        ')
        ->where('type', 'goal')
        ->whereIn('activity_id',$dailyGoalIds)
        ->groupBy('day')
        ->get();

        $questionnaires = $this->activities()
            ->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->get();

        $questionnaires = $questionnaires->map(function ($questionnaire) {
            return [
                'id' => $questionnaire->activity_id,
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
            'weeklyGoals' => $weeklyGoals,
            'dailyGoals' => $dailyGoals,
            'activities' => $activities,
            'total_of_weeks' => $this->total_of_weeks,
            'created_by' => $this->created_by,
            'disease_id' => $this->disease_id,
            'questionnaires' => $questionnaires,
        ];
    }
}

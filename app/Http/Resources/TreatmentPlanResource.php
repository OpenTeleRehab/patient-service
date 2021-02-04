<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $activities = Activity::select('week', 'day')
            ->selectRaw('ANY_VALUE(activities.id) AS id')
            ->selectRaw('GROUP_CONCAT(CASE WHEN type = "exercise" THEN activity_id END) AS exercises')
            ->selectRaw('GROUP_CONCAT(CASE WHEN type = "material" THEN activity_id END) AS materials')
            ->where('treatment_plan_id', $this->id)
            ->groupBy('treatment_plan_id')
            ->groupBy('week')
            ->groupBy('day')
            ->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'patient_id' => $this->patient_id,
            'start_date' => $this->start_date ? $this->start_date->format(config('settings.date_format')) : '',
            'end_date' => $this->end_date ? $this->end_date->format(config('settings.date_format')) : '',
            'status' => $this->status,
            'activities' => ActivityResource::collection($activities),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'patient_id' => $this->patient_id,
            'disease_id' => $this->disease_id,
            'start_date' => $this->start_date ? $this->start_date->format(config('settings.date_format')) : '',
            'end_date' => $this->end_date ? $this->end_date->format(config('settings.date_format')) : '',
            'status' => $this->status,
        ];
    }
}

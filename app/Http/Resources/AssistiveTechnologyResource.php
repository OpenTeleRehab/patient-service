<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistiveTechnologyResource extends JsonResource
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
            'assistive_technology_id' => $this->assistive_technology_id,
            'patient_id' => $this->patient_id,
            'provision_date' => $this->provision_date ? $this->provision_date->format(config('settings.date_format')) : '',
            'follow_up_date' => $this->follow_up_date ? $this->follow_up_date->format(config('settings.date_format')) : '',
            'appointment' => $this->appointment,
        ];
    }
}

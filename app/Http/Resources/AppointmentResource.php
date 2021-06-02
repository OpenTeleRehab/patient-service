<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AppointmentResource extends JsonResource
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
            'therapist_id' => $this->therapist_id,
            'patient_id' => $this->patient_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'created_at' => Carbon::parse($this->created_at)->toDateTimeString(),
            'patient' => $this->patient,
            'therapist_status' => $this->therapist_status,
            'patient_status' => $this->patient_status,
            'note' => $this->note,
            'created_by_therapist' => $this->created_by_therapist,
        ];
    }
}

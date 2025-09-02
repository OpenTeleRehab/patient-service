<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientRawDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'enabled' => $this->enabled,
            'location' => $this->location,
            'therapist_id' => $this->therapist_id,
            'secondary_therapists' => $this->secondary_therapists ? : [],
            'treatmentPlans' => PatientRawDataTreatmentPlanResource::collection($this->treatmentPlans),
            'assistiveTechnologies' => AssistiveTechnologyResource::collection($this->assistiveTechnologies),
            'call' => $this->call_histories_count,
        ];

        return $responseData;
    }
}

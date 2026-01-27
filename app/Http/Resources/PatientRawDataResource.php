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
            'phc_service_id' => $this->phc_service_id,
            'country_id' => $this->country_id,
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'enabled' => $this->enabled,
            'location' => $this->location,
            'therapist_id' => $this->therapist_id,
            'secondary_therapists' => $this->secondary_therapists ? : [],
            'phc_worker_id' => $this->phc_worker_id,
            'supplementary_phc_workers' => $this->supplementary_phc_workers ? : [],
            'treatmentPlans' => PatientRawDataTreatmentPlanResource::collection($this->treatmentPlans),
            'assistiveTechnologies' => AssistiveTechnologyResource::collection($this->assistiveTechnologies),
            'call' => $this->call_histories_count,
        ];

        return $responseData;
    }
}

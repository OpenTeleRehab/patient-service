<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $upcomingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('end_date', '>', Carbon::now())
            ->orderBy('start_date')
            ->first();

        // Get last treatment if there is no upcoming
        if (!$upcomingTreatmentPlan) {
            $upcomingTreatmentPlan = $this->treatmentPlans()
                ->orderBy('end_date', 'desc')
                ->first();
        }

        return [
            'id' => $this->id,
            'identity' => $this->identity,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'date_of_birth' => $this->date_of_birth,
            'note' => $this->note,
            'gender' => $this->gender,
            'therapist_id' => $this->therapist_id,
            'enabled' => $this->enabled,
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientListMobileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $upcomingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('end_date', '>', Carbon::now())
            ->orderBy('start_date')
            ->first();

        $ongoingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now())
            ->get();

        $lastTreatmentPlan = $this->treatmentPlans()
            ->orderBy('end_date', 'desc')
            ->first();

        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'location' => $this->location,
            'dial_code' => $this->dial_code,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'phc_service_id' => $this->phc_service_id,
            'date_of_birth' => $this->date_of_birth,
            'enabled' => $this->enabled,
            'therapist_id' => $this->therapist_id,
            'phc_worker_id' => $this->phc_worker_id,
            'secondary_therapists' => $this->secondary_therapists ?: [],
            'supplementary_phc_workers' => $this->supplementary_phc_workers ?: [],
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
            'ongoingTreatmentPlan' => $ongoingTreatmentPlan,
            'lastTreatmentPlan' => $lastTreatmentPlan,
            'referral_status' => $this->whenLoaded('lastReferral', fn() => $this->lastReferral?->status),
            'referral_request_reason'=> $this->whenLoaded('lastReferral', fn() => $this->lastReferral?->request_reason),
            'referral_reject_reason'=> $this->whenLoaded('lastReferral', fn() => $this->lastReferral?->reject_reason),
            'referral_therapists' => $this->referral_therapists,
            'interviewed_questionnaires' => $this->interviewed_questionnaires,
            'completed_percent' => $this->completed_percent,
            'total_pain_threshold' => $this->total_pain_threshold,
            'chat_user_id' => $this->chat_user_id,
            'invited_appointment_count' => $this->appointments()
                ->where('start_date', '>', Carbon::now())
                ->where('therapist_status', Appointment::STATUS_INVITED)
                ->where('patient_status', Appointment::STATUS_ACCEPTED)
                ->orderBy('start_date')
                ->count(),
            'unread_appointment_count' => $this->unread_appointments_count,
        ];

        return $responseData;
    }
}

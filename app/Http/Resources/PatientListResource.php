<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\AdminService;

class PatientListResource extends JsonResource
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

        $treatmentPlans = $this->treatmentPlans;
        $groupIds = $treatmentPlans->pluck('health_condition_group_id')->filter()->unique()->all();
        $conditionIds = $treatmentPlans->pluck('health_condition_id')->filter()->unique()->all();

        $adminData = app(AdminService::class)->getHealthConditions($groupIds, $conditionIds);

        // Map IDs to titles
        $healthConditionGroups = $treatmentPlans->pluck('health_condition_group_id')
            ->map(fn($id) => $adminData['groups'][$id]['title'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $healthConditions = $treatmentPlans->pluck('health_condition_id')
            ->map(fn($id) => $adminData['conditions'][$id]['title'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'phc_service_id' => $this->phc_service_id,
            'date_of_birth' => $this->date_of_birth,
            'enabled' => $this->enabled,
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
            'ongoingTreatmentPlan' => $ongoingTreatmentPlan,
            'lastTreatmentPlan' => $lastTreatmentPlan,
            'referred_by' => $this->referred_by,
            'referral_status' => $this->whenLoaded('lastReferral', fn() => $this->lastReferral?->status),
            'referral_therapists' => $this->referral_therapists,
            'healthConditionGroups' => implode(',', $healthConditionGroups),
            'healthConditions' => implode(',', $healthConditions),
            'completed_percent' => $this->completed_percent,
        ];

        if ($request->get('type') !== User::ADMIN_GROUP_GLOBAL_ADMIN) {
            $responseData = array_merge($responseData, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'gender' => $this->gender,
                'therapist_id' => $this->therapist_id,
                'phc_worker_id' => $this->phc_worker_id,
                'secondary_therapists' => $this->secondary_therapists ?: [],
                'supplementary_phc_workers' => $this->supplementary_phc_workers ?: [],
                'lead_and_supplementary_phc_workers' => $this->lead_and_supplementary_phc_workers,
                'lead_and_supplementary_therapists' => $this->lead_and_supplementary_therapists,
                'invited_appointment_count' => $this->appointments()
                    ->where('start_date', '>', Carbon::now())
                    ->where('therapist_status', '>', Appointment::STATUS_INVITED)
                    ->where('patient_status', '>', Appointment::STATUS_ACCEPTED)
                    ->orderBy('start_date')
                    ->count(),
                'unread_appointment_count' => $this->unread_appointments_count,
            ]);
        }

        return $responseData;
    }
}

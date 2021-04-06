<?php

namespace App\Http\Resources;

use App\Models\User;
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

        $ongoingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now())
            ->get();

        // Get last treatment if there is no upcoming
        if (!$upcomingTreatmentPlan) {
            $upcomingTreatmentPlan = $this->treatmentPlans()
                ->orderBy('end_date', 'desc')
                ->first();
        }

        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'date_of_birth' => $this->date_of_birth,
            'enabled' => $this->enabled,
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
            'ongoingTreatmentPlan' => $ongoingTreatmentPlan,
        ];
        if ($request->get('type') !== User::ADMIN_GROUP_GLOBAL_ADMIN) {
            $responseData = array_merge($responseData, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone,
                'gender' => $this->gender,
                'chat_user_id' => $this->chat_user_id,
                'chat_rooms' => $this->chat_rooms ?: [],
                'therapist_id' => $this->therapist_id,
                'note' => $this->note,
            ]);
        }

        return $responseData;
    }
}

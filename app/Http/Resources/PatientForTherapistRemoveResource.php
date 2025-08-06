<?php

namespace App\Http\Resources;
use App\Helpers\TreatmentActivityHelper;
use App\Models\TreatmentPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Http;


class PatientForTherapistRemoveResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'therapist_id' => $this->therapist_id,
            'secondary_therapists' => $this->secondary_therapists ? : [],
            'is_secondary_therapist' => $this->isSecondaryTherapist($this->secondary_therapists, $request)
        ];

        return $responseData;
    }

    /**
     * @param $secondary_therapists
     * @param $request
     * @return bool
     */
    private function isSecondaryTherapist($secondary_therapists, $request)
    {
        $isSecondaryTherapist = false;
        if (!empty($secondary_therapists) && in_array($request->get('therapist_id'), $secondary_therapists)) {
            $isSecondaryTherapist = true;
        }

        return $isSecondaryTherapist;
    }
}

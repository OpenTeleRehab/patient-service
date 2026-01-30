<?php

namespace App\Http\Resources;

use App\Models\ReferralAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_identity' => $this->patient->identity,
            'patient_id'=>$this->patient->id,
            'first_name' => $this->patient->first_name,
            'last_name' => $this->patient->last_name,
            'date_of_birth' => $this->patient->date_of_birth,
            'lead_and_supplementary_phc' => $this->lead_and_supplementary_phc,
            'referred_by' => $this->referred_by,
            'status' => $this->referralAssignments()->latest()->first()?->status,
            'request_reason' => $this->request_reason,
            'therapist_reason' => $this->referralAssignments()->latest()->first()?->reason,
        ];
    }
}

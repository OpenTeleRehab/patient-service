<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralAssignmentResource extends JsonResource
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
            'date_of_birth' => $this->referral->patient->date_of_birth,
            'lead_and_supplementary_phc' => $this->lead_and_supplementary_phc,
            'referred_by' => $this->referred_by,
            'reason' => $this->reason
        ];
    }
}

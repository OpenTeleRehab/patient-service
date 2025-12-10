<?php

namespace App\Http\Resources;

use App\Models\Referral;
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
            'date_of_birth' => $this->patient->date_of_birth,
            'lead_and_supplementary_phc' => $this->lead_and_supplementary_phc,
            'referred_by' => $this->referred_by,
            'status' => $this->referralAssignments->where('status', Referral::STATUS_INVITED)->first()?->status,
        ];
    }
}

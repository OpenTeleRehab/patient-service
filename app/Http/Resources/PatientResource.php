<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->makeHidden(['chat_password', 'lastReferral']);

        return [
            ...parent::toArray($request),
            'referral_status' => $this->whenLoaded('lastReferral', fn() => $this->lastReferral?->status),
        ];
    }
}

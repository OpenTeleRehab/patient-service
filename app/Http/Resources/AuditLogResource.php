<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use carbon\Carbon;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $changes = $this->changes;
        $beforeChanged = isset($changes['old']) ? $changes['old'] : [];
        $afterChanged = isset($changes['attributes']) ? $changes['attributes'] : [];
        $subjectType = last(explode('\\', $this->subject_type));
        unset($beforeChanged['auto_translated']);
        unset($afterChanged['auto_translated']);
        if (isset($beforeChanged['created_at'])) {
            $beforeChanged['created_at'] = Carbon::parse($beforeChanged['created_at'])->format('Y-m-d');
        }
        if (isset($beforeChanged['updated_at'])) {
            $beforeChanged['updated_at'] = Carbon::parse($beforeChanged['updated_at'])->format('Y-m-d');
        }
        if (isset($afterChanged['created_at'])) {
            $afterChanged['created_at'] = Carbon::parse($afterChanged['created_at'])->format('Y-m-d');
        }
        if (isset($afterChanged['updated_at'])) {
            $afterChanged['updated_at'] = Carbon::parse($afterChanged['updated_at'])->format('Y-m-d');
        }

        return [
            'id' => $this->id,
            'resource' => $this->log_name,
            'type_of_changes' => $this->description,
            'who' => $this->causer_name,
            'country' => $this->country,
            'region' => $this->region,
            'province' => $this->province,
            'clinic' => $this->clinic,
            'phc_service' => $this->phc_service,
            'user_group' => $this->causer_group,
            'date_time' => $this->created_at,
            'subject_type' => $subjectType,
            'before_changed' => $beforeChanged,
            'after_changed' => $afterChanged
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'week' => $this->week,
            'day' => $this->day,
            'created_by' => $this->created_by,
            'exercises' => $this->exercises ? array_map('intval', explode(',', $this->exercises)): [],
            'materials' => $this->materials ? array_map('intval', explode(',', $this->materials)) : [],
            'questionnaires' => $this->questionnaires ? array_map('intval', explode(',', $this->questionnaires)) : [],
        ];
    }
}

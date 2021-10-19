<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'identity' => $this->identity,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'dial_code' => $this->dial_code,
            'phone' => $this->phone,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'date_of_birth' => $this->date_of_birth,
            'note' => $this->note,
            'gender' => $this->gender,
            'therapist_id' => $this->therapist_id,
            'enabled' => $this->enabled,
            'language_id' => $this->language_id,
            'term_and_condition_id' => $this->term_and_condition_id,
            'chat_user_id' => $this->chat_user_id,
            'chat_password' => $this->chat_password,
            'chat_rooms' => $this->chat_rooms,
            'secondary_therapists' => $this->secondary_therapists,
            'privacy_and_policy_id' => $this->privacy_and_policy_id,
            'kid_theme' => $this->kid_theme,
        ];
    }
}

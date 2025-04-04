<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ExternalPatientResource extends JsonResource
{
    protected $countries;

    public function __construct($resource, $countries = [])
    {
        parent::__construct($resource);
        $this->countries = $countries;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'use' => 'usual',
                    'value' => $this->id,
                ],
            ],
            'active' => $this->enabled,
            'name' => [
                [
                    'family' => $this->last_name,
                    'given' => [$this->first_name],
                ],
            ],
            'telecom' => [
                [
                    'system' => 'phone',
                    'value' => $this->phone,
                    'use' => 'mobile',
                ],
            ],
            'gender' => $this->gender,
            'birthDate' => $this->date_of_birth ? Carbon::parse($this->date_of_birth)->format('Y-m-d') : '',
            'communication' => $this->getCountry($this->language_id) ? [
                [
                    'language' => [
                        'coding' => [
                            [
                                'system' => 'urn:ietf:bcp:47',
                                'code' => $this->getCountry($this->language_id)['iso_code'],
                            ],
                        ],
                        'text' => $this->getCountry($this->language_id)['name'],
                    ],
                    'preferred' => true,
                ],
            ] : [],
        ];
    }

    private function getCountry($countryId)
    {
        return collect($this->countries)->firstWhere('id', (string) $countryId) ?? null;
    }
}

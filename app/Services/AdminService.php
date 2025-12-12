<?php

namespace App\Services;
use App\Models\Forwarder;

use Illuminate\Support\Facades\Http;

class AdminService
{
    public function getHealthConditions(array $groupIds, array $conditionIds): array
    {
        $groups = [];
        $conditions = [];

        $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
        if (!empty($groupIds)) {
            $response = Http::withToken($access_token)
                ->get(env('ADMIN_SERVICE_URL') . '/health-condition-groups/find', [
                    'ids' => implode(',', $groupIds)
                ]);

            if ($response->successful()) {
                $groups = collect($response->json()['data'])->keyBy('id');
            }
        }

        if (!empty($conditionIds)) {
            $response = Http::withToken($access_token)
                ->get(env('ADMIN_SERVICE_URL') . '/health-conditions/find', [
                    'ids' => implode(',', $conditionIds)
                ]);

            if ($response->successful()) {
                $conditions = collect($response->json()['data'])->keyBy('id');
            }
        }

        return [
            'groups' => $groups,
            'conditions' => $conditions,
        ];
    }
}
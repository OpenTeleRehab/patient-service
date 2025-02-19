<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use App\Helpers\KeycloakHelper;
use App\Models\Forwarder;
use App\Models\User;

class AuditLogHelper {

    /**
     * @param Activity $activityLogger
     * @param User $user
     *
     * @return mixed|null
     */
    public static function store(Activity $activityLogger, User $user) {
        $token = KeycloakHelper::getAdminKeycloakAccessToken();
        $userGroups = KeycloakHelper::getUserGroup($token);
        $changes = $activityLogger->changes;
        $submitData = [
            'log_name' => 'patient_service',
            'type' => $activityLogger->description,
            'user_id' => $user->id,
            'user_full_name' => $user->full_name,
            'user_email' => $user->email,
            'user_groups' => $userGroups,
            'properties' => $changes
        ];

        $url = env('ADMIN_SERVICE_URL') . '/audit-logs';
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE))->post($url, $submitData);
        return $response;
    }
}

<?php

namespace App\Helpers;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

define("GADMIN_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('GADMIN_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("ADMIN_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('ADMIN_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("THERAPIST_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('THERAPIST_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("KEYCLOAK_GROUPS_URL", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('ADMIN_KEYCLOAK_REAMLS_NAME') . '/groups');

/**
 * Class KeycloakHelper
 * @package App\Helpers
 */
class KeycloakHelper
{
    const GADMIN_ACCESS_TOKEN = 'gadmin_access_token';
    const ADMIN_ACCESS_TOKEN = 'admin_access_token';
    const THERAPIST_ACCESS_TOKEN = 'therapist_access_token';

    /**
     * @return mixed|null
     */
    public static function getGAdminKeycloakAccessToken()
    {
        $access_token = Cache::get(self::GADMIN_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp > $token_exp_at) {
                return self::generateKeycloakToken(GADMIN_KEYCLOAK_TOKEN_URL, env('GADMIN_KEYCLOAK_BACKEND_SECRET'), self::GADMIN_ACCESS_TOKEN);
            }

            return $access_token;
        }

        return self::generateKeycloakToken(GADMIN_KEYCLOAK_TOKEN_URL, env('GADMIN_KEYCLOAK_BACKEND_SECRET'), self::GADMIN_ACCESS_TOKEN);
    }

    /**
     * @return mixed|null
     */
    public static function getAdminKeycloakAccessToken()
    {
        $access_token = Cache::get(self::ADMIN_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp > $token_exp_at) {
                return self::generateKeycloakToken(ADMIN_KEYCLOAK_TOKEN_URL, env('ADMIN_KEYCLOAK_BACKEND_SECRET'), self::ADMIN_ACCESS_TOKEN);
            }

            return $access_token;
        }

        return self::generateKeycloakToken(ADMIN_KEYCLOAK_TOKEN_URL, env('ADMIN_KEYCLOAK_BACKEND_SECRET'), self::ADMIN_ACCESS_TOKEN);
    }

    /**
     * @return mixed|null
     */
    public static function getTherapistKeycloakAccessToken()
    {
        $access_token = Cache::get(self::THERAPIST_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp > $token_exp_at) {
                return self::generateKeycloakToken(THERAPIST_KEYCLOAK_TOKEN_URL, env('THERAPIST_KEYCLOAK_BACKEND_SECRET'), self::THERAPIST_ACCESS_TOKEN);
            }

            return $access_token;
        }

        return self::generateKeycloakToken(THERAPIST_KEYCLOAK_TOKEN_URL, env('THERAPIST_KEYCLOAK_BACKEND_SECRET'), self::THERAPIST_ACCESS_TOKEN);
    }

    /**
     * @param string $url
     * @param string $client_secret
     * @param string $cache_key
     *
     * @return void
     */
    private static function generateKeycloakToken($url, $client_secret, $cache_key)
    {
        $response = Http::asForm()->post($url, [
            'grant_type' => 'password',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => $client_secret,
            'username' => env('KEYCLOAK_BACKEND_USERNAME'),
            'password' => env('KEYCLOAK_BACKEND_PASSWORD')
        ]);

        if ($response->successful()) {
            $result = $response->json();

            Cache::forever($cache_key, $result['access_token']);

            return $result['access_token'];
        }

        return null;
    }

    /**
     * @param string $token
     *
     * @return array
     */
    public static function getUserGroup($token)
    {
        $response = Http::withToken($token)->get(KEYCLOAK_GROUPS_URL);
        $userGroups = [];
        if ($response->successful()) {
            $groups = $response->json();
            foreach ($groups as $group) {
                $userGroups[$group['name']] = $group['id'];
            }
        }

        return $userGroups;
    }
}

<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

define('ROCKET_CHAT_LOGIN_URL', env('ROCKET_CHAT_URL') . '/api/v1/login');
define('ROCKET_CHAT_LOGOUT_URL', env('ROCKET_CHAT_URL') . '/api/v1/logout');
define('ROCKET_CHAT_CREATE_USER_URL', env('ROCKET_CHAT_URL') . '/api/v1/users.create');
define('ROCKET_CHAT_UPDATE_USER_URL', env('ROCKET_CHAT_URL') . '/api/v1/users.update');
define('ROCKET_CHAT_DELETE_USER_URL', env('ROCKET_CHAT_URL') . '/api/v1/users.delete');
define('ROCKET_CHAT_CREATE_ROOM_URL', env('ROCKET_CHAT_URL') . '/api/v1/im.create');

class RocketChatHelper
{
    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/authentication/login
     * @return array
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function login($username, $password)
    {
        $response = Http::asJson()->post(ROCKET_CHAT_LOGIN_URL, [
            'user' => $username,
            'password' => $password
        ]);
        if ($response->successful()) {
            $result = $response->json();
            return [
                'userId' => $result['data']['userId'],
                'authToken' => $result['data']['authToken'],
            ];
        }
        $response->throw();
    }

    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/authentication/logout
     * @param string $userId
     * @param string $authToken
     *
     * @return bool
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function logout($userId, $authToken)
    {
        $response = Http::asJson()->withHeaders([
            'X-Auth-Token' => $authToken,
            'X-User-Id' => $userId,
        ])->asJson()->post(ROCKET_CHAT_LOGOUT_URL);
        if ($response->successful()) {
            $result = $response->json();
            return $result['status'] === 'success';
        }

        $response->throw();
    }

    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/users/create
     * @param array $payload
     *
     * @return mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function createUser($payload)
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => getenv('ROCKET_CHAT_ADMIN_AUTH_TOKEN'),
            'X-User-Id' => getenv('ROCKET_CHAT_ADMIN_USER_ID'),
        ])->asJson()->post(ROCKET_CHAT_CREATE_USER_URL, $payload);

        if ($response->successful()) {
            $result = $response->json();
            return $result['success'] ? $result['user']['_id'] : null;
        }

        $response->throw();
    }

    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/users/update
     * @param string $userId
     * @param array $data
     *
     * @return mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function updateUser($userId, $data)
    {
        $payload = [
            'userId' => $userId,
            'data' => $data
        ];
        $response = Http::withHeaders([
            'X-Auth-Token' => getenv('ROCKET_CHAT_ADMIN_AUTH_TOKEN'),
            'X-User-Id' => getenv('ROCKET_CHAT_ADMIN_USER_ID'),
            'X-2fa-Code' => hash('sha256', getenv('ROCKET_CHAT_ADMIN_PASSWORD')),
            'X-2fa-Method' => 'password'
        ])->asJson()->post(ROCKET_CHAT_UPDATE_USER_URL, $payload);

        if ($response->successful()) {
            $result = $response->json();
            return $result['success'];
        }

        $response->throw();
    }

    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/users/delete
     * @param string $userId
     *
     * @return bool|mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function deleteUser($userId)
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => getenv('ROCKET_CHAT_ADMIN_AUTH_TOKEN'),
            'X-User-Id' => getenv('ROCKET_CHAT_ADMIN_USER_ID'),
            'X-2fa-Code' => hash('sha256', getenv('ROCKET_CHAT_ADMIN_PASSWORD')),
            'X-2fa-Method' => 'password'
        ])->asJson()->post(ROCKET_CHAT_DELETE_USER_URL, ['userId' => $userId, 'confirmRelinquish' => true]);

        if ($response->successful()) {
            $result = $response->json();
            return $result['success'];
        }

        $response->throw();
    }

    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/im/messages
     * @param string $therapist The therapist identity
     * @param string $patient The patient identity
     *
     * @return mixed|null
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function createChatRoom($therapist, $patient)
    {
        $therapistAuth = self::login($therapist, $therapist . 'PWD');
        $authToken = $therapistAuth['authToken'];
        $userId = $therapistAuth['userId'];
        $response = Http::withHeaders([
            'X-Auth-Token' => $authToken,
            'X-User-Id' => $userId,
        ])->asJson()->post(ROCKET_CHAT_CREATE_ROOM_URL, ['usernames' => $patient]);

        // always logout to clear local login token on completion
        self::logout($userId, $authToken);

        if ($response->successful()) {
            $result = $response->json();
            return $result['success'] ? $result['room']['rid'] : null;
        }

        $response->throw();
    }
}

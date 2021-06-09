<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

define('ROCKET_CHAT_LOGIN_URL', env('ROCKET_CHAT_URL') . '/api/v1/login');
define('ROCKET_CHAT_LOGOUT_URL', env('ROCKET_CHAT_URL') . '/api/v1/logout');
define('ROCKET_CHAT_CREATE_USER_URL', env('ROCKET_CHAT_URL') . '/api/v1/users.create');
define('ROCKET_CHAT_UPDATE_USER_URL', env('ROCKET_CHAT_URL') . '/api/v1/users.update');
define('ROCKET_CHAT_DELETE_USER_URL', env('ROCKET_CHAT_URL') . '/api/v1/users.delete');
define('ROCKET_CHAT_DELETE_MESSAGE_URL', env('ROCKET_CHAT_URL') . '/api/v1/rooms.cleanHistory');
define('ROCKET_CHAT_CREATE_ROOM_URL', env('ROCKET_CHAT_URL') . '/api/v1/im.create');
define('ROCKET_CHAT_GET_MESSAGES_URL', env('ROCKET_CHAT_URL') . '/api/v1/im.messages');
define('ROCKET_CHAT_GET_COUNTER_URL', env('ROCKET_CHAT_URL') . '/api/v1/im.counters');
define('ROCKET_CHAT_GET_ROOM_URL', env('ROCKET_CHAT_URL') . '/api/v1/rooms.info');
define('ROCKET_CHAT_GET_USER_URL', env('ROCKET_CHAT_URL') . '/api/v1/users.info');

class RocketChatHelper
{
    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/authentication/login
     * @param string $username
     * @param string $password
     *
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
     * @param string $chat_room
     *
     * @return mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function deleteMessages($chat_room)
    {
        $payload = [
            'roomId' => $chat_room,
            'latest' => Carbon::now()->subYears(1),
            'oldest' => Carbon::now()->subYears(5)
        ];

        $response = Http::withHeaders([
            'X-Auth-Token' => getenv('ROCKET_CHAT_ADMIN_AUTH_TOKEN'),
            'X-User-Id' => getenv('ROCKET_CHAT_ADMIN_USER_ID'),
        ])->asJson()->post(ROCKET_CHAT_DELETE_MESSAGE_URL, $payload);

        if ($response->successful()) {
            $result = $response->json();
            return $result['success'];
        }

        $response->throw();
    }

    /**
     * @see https://docs.rocket.chat/api/rest-api/methods/im/messages
     * @param string $therapist The therapist identity
     * @param string $patient   The patient identity
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

        // Always logout to clear local login token on completion.
        self::logout($userId, $authToken);

        if ($response->successful()) {
            $result = $response->json();
            return $result['success'] ? $result['room']['rid'] : null;
        }

        $response->throw();
    }

    /**
     * @param \App\Models\User $user
     * @param string $chat_room
     *
     * @return mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function getMessages($user, $chat_room)
    {
        $userAuth = self::login($user->identity, $user->identity . 'PWD');
        $authToken = $userAuth['authToken'];
        $userId = $userAuth['userId'];

        $response = Http::withHeaders([
            'X-Auth-Token' => $authToken,
            'X-User-Id' => $userId
        ])->asJson()->get(ROCKET_CHAT_GET_MESSAGES_URL, ['roomId' => $chat_room]);

        // Always logout to clear local login token on completion.
        self::logout($userId, $authToken);

        if ($response->successful()) {
            $result = $response->json();
            return $result['messages'];
        }

        $response->throw();
    }

    /**
     * @param string $authToken
     * @param string $userId
     * @param string $roomId
     *
     * @return mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function getUnreadMessages($authToken, $userId, $roomId)
    {
        $unreads = 0;
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $authToken,
                'X-User-Id' => $userId
            ])->asJson()->get(ROCKET_CHAT_GET_COUNTER_URL, ['roomId' => $roomId]);

            if ($response->successful()) {
                $result = $response->json();
                return $result['unreads'];
            }
        } catch (\Exception $e) {
            $response->throw();
        }

        return $unreads;
    }

    /**
     * @param \App\Models\User $user
     * @param string $room_id
     *
     * @return mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function getRoom($user, $room_id)
    {
        $userAuth = self::login($user->identity, $user->identity . 'PWD');
        $authToken = $userAuth['authToken'];
        $userId = $userAuth['userId'];

        $response = Http::withHeaders([
            'X-Auth-Token' => $authToken,
            'X-User-Id' => $userId,
        ])->asJson()->get(ROCKET_CHAT_GET_ROOM_URL, ['roomId' => $room_id]);

        // Always logout to clear local login token on completion.
        self::logout($userId, $authToken);

        if ($response->successful()) {
            $usernames = $response->json()['room']['usernames'];

            return [
                'patient_username' => strpos($usernames[0], 'P') !== false ? $usernames[0] : $usernames[1],
                'therapist_username' => strpos($usernames[0], 'T') !== false ? $usernames[0] : $usernames[1]
            ];
        }

        $response->throw();
    }

    /**
     * @param string $username
     *
     * @return mixed
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function getUser($username)
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => getenv('ROCKET_CHAT_ADMIN_AUTH_TOKEN'),
            'X-User-Id' => getenv('ROCKET_CHAT_ADMIN_USER_ID'),
        ])->asJson()->get(ROCKET_CHAT_GET_USER_URL, ['username' => $username]);

        if ($response->successful()) {
            $result = $response->json();
            return $result['user'];
        }

        $response->throw();
    }
}

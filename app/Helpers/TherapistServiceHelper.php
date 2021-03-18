<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class TherapistServiceHelper
{
    /**
     * @param string $accessToken
     * @param string $chatRoomId
     */
    public static function AddNewChatRoom($accessToken, $chatRoomId)
    {
        Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken
        ])->asJson()->put(
            env('THERAPIST_SERVICE_URL') . '/api/user/add-new-chatroom',
            ['chat_room_id' => $chatRoomId]
        );
    }
}

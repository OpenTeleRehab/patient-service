<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class TherapistServiceHelper
{
    /**
     * @param string $accessToken
     * @param string $chatRoomId
     *
     * @return void
     */
    public static function AddNewChatRoom($accessToken, $chatRoomId, $therapisId)
    {
        Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken
        ])->asJson()->put(
            env('THERAPIST_SERVICE_URL') . '/api/user/add-new-chatroom', [
                'chat_room_id' => $chatRoomId,
                'therapist_id' => $therapisId
            ]
        );
    }
}

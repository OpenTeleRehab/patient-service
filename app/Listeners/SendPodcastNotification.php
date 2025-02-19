<?php

namespace App\Listeners;

use App\Events\PodcastNotificationEvent;
use App\Models\Message;
use Google\Auth\Credentials\ServiceAccountCredentials;

class SendPodcastNotification
{
    /**
     * Handle the event.
     *
     * @param PodcastNotificationEvent $event
     * @return string
     */
    public function handle(PodcastNotificationEvent $event)
    {
        if (str_contains(Message::JITSI_CALL_AUDIO_STARTED, $event->body) || str_contains(Message::JITSI_CALL_VIDEO_STARTED, $event->body) || str_contains(Message::JITSI_CALL_AUDIO_MISSED, $event->body) || str_contains(Message::JITSI_CALL_VIDEO_MISSED, $event->body)) {
            $message = [
                'token' => $event->token,
                'data' => [
                    '_id' => $event->id,
                    'rid' => $event->rid,
                    'title' => $event->title,
                    'body' => $event->body,
                    'channelId' => 'fcm_call_channel',
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'badge' => 1,
                            'content-available' => 1
                        ]
                    ],
                    'headers' => [
                        'apns-push-type' => 'background',
                        'apns-priority' => '5',
                        'apns-topic' => '',
                    ]
                ],
                'android' => [
                    'priority' => 'high',
                ],
            ];
        } else {
            $message = [
                'token' => $event->token,
                'notification' => [
                    'title' => $event->title,
                    'body' => $event->body,
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'badge' => 1,
                        ]
                    ],
                ]
            ];
        }

        $dataString = json_encode(['message' => $message]);

        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        $projectId = env('FIREBASE_PROJECT_ID');
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        return curl_exec($ch);
    }

    private function getAccessToken()
    {
        $keyFilePath = storage_path(env('FIREBASE_SERVICE_ACCOUNT_FILE'));
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountCredentials($scopes, $keyFilePath);

        return $credentials->fetchAuthToken()['access_token'];
    }
}

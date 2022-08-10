<?php

namespace App\Listeners;

use App\Events\PodcastNotificationEvent;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPodcastNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PodcastNotificationEvent  $event
     * @return string
     */
    public function handle(PodcastNotificationEvent $event)
    {
        if (str_contains(Message::JITSI_CALL_AUDIO_STARTED, $event->body) ||
            str_contains(Message::JITSI_CALL_VIDEO_STARTED, $event->body) ||
            str_contains(Message::JITSI_CALL_AUDIO_MISSED, $event->body) ||
            str_contains(Message::JITSI_CALL_VIDEO_MISSED, $event->body)
        ) {
            $message = [
                'to' => $event->token,
                'data' => [
                    '_id' => $event->id,
                    'rid' => $event->rid,
                    'title' => $event->title,
                    'body' => $event->body,
                    'channelId' => 'fcm_call_channel',
                ],
                'content_available' => true,
                'priority' => 'high',
                'topic' => 'all',
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'content-available' => 'true'
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '5',
                        'apns-push-type' => 'background',
                        'apns-topic' => ''
                    ]
                ]
            ];
        } else {
            $message = [
                'to' => $event->token,
                'notification' => [
                    'title' => $event->title,
                    'body' => $event->body,
                    'sound' => 'default'
                ],
                'priority' => 'high',
                'topic' => 'all',
                'sound' => 'default',
                'badge' => 1,
                'content_available' => true,
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'badge' => 1,
                            'content-available' => true
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => 5,
                        'apns-push-type' => 'background',
                        'apns-topic' => ''
                    ]
                ]
            ];
        }

        $dataString = json_encode($message);

        $headers = [
            'Authorization: key=' . env('FIREBASE_SERVER_API_KEY'),
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        return curl_exec($ch);
    }
}

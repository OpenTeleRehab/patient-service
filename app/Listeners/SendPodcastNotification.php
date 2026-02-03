<?php

namespace App\Listeners;

use App\Events\PodcastNotificationEvent;
use App\Helpers\FirebaseService;
use App\Models\Message;
use Exception;

class SendPodcastNotification
{
    /**
     * Handle the event.
     *
     * @param PodcastNotificationEvent $event
     * @throws Exception
     */
    public function handle(PodcastNotificationEvent $event)
    {
        if (str_contains(Message::JITSI_CALL_AUDIO_STARTED, $event->body) ||
            str_contains(Message::JITSI_CALL_VIDEO_STARTED, $event->body) ||
            str_contains(Message::JITSI_CALL_AUDIO_MISSED, $event->body) ||
            str_contains(Message::JITSI_CALL_VIDEO_MISSED, $event->body)
        ) {
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
                    'headers' => [
                        'apns-expiration' => (string) (time() + 60), // iOS expire time (unix timestamp)
                        'apns-push-type' => 'background',
                        'apns-priority' => '5',
                        'apns-topic' => '',
                    ],
                    'payload' => [
                        'aps' => [
                            'badge' => 1,
                            'content-available' => 1,
                        ],
                    ],
                ],
                'android' => [
                    'ttl' => '60s', // expires in 60 seconds
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

        FirebaseService::send($message);
    }
}

<?php

namespace App\Listeners;

use App\Events\AppointmentEvent;
use App\Helpers\FirebaseService;
use Exception;

class Appointment
{
    /**
     * Handle the event.
     *
     * @param AppointmentEvent $event
     * @throws Exception
     */
    public function handle(AppointmentEvent $event): void
    {
        $message = [
            'token' => $event->fcmToken,
            'data' => [
                'event_type' => 'appointment',
                'title' => $event->title,
                'start_date' => $event->startDate,
                'end_date' => $event->endDate,
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'badge' => 1,
                    ],
                ],
            ],
            'android' => [
                'priority' => 'high',
            ],
        ];

        FirebaseService::send($message);
    }
}

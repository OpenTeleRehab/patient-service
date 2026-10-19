<?php

namespace App\Listeners;

use App\Events\TreatmentPlanEvent;
use App\Helpers\FirebaseService;
use Exception;
use Illuminate\Support\Facades\Log;

class TreatmentPlan
{
    /**
     * Handle the event.
     *
     * @param TreatmentPlanEvent $event
     */
    public function handle(TreatmentPlanEvent $event): void
    {
        $message = [
            'token' => $event->fcmToken,
            // Data only: the app's background handler shows the notification and marks the treatment plan
            // for a refresh, which a system-shown notification would skip. Nothing is shown while the app is open.
            'data' => [
                'event_type' => 'treatment_plan',
                'action' => $event->action,
                'treatment_plan_id' => (string) $event->treatmentPlanId,
                'title' => $event->title,
                'body' => $event->body,
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                        'content-available' => 1,
                        'mutable-content' => 1,
                    ],
                ],
            ],
            'android' => [
                'priority' => 'high',
            ],
        ];

        try {
            FirebaseService::send($message);
        } catch (Exception $e) {
            // The treatment plan is already saved; the app still picks it up on its next fetch.
            Log::warning('Treatment plan notification failed', [
                'treatment_plan_id' => $event->treatmentPlanId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

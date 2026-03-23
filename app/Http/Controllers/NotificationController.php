<?php

namespace App\Http\Controllers;

use App\Events\PodcastNotificationEvent;
use App\Helpers\TranslationHelper;
use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void|null
     */
    public function pushNotification(Request $request)
    {
        $identity = $request->get('identity');
        $id = $request->get('_id');
        $rid = $request->get('rid');
        $title = $request->get('title');

        if (preg_match('/^PHC[0-9]/', $identity)) {
            Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))->get(env('THERAPIST_SERVICE_URL') . '/push-notification', $request->all());
        }

        if (preg_match('/^P[0-9]/', $identity)) {
            $user = User::where('identity', $identity)->firstOrFail();

            $translations = TranslationHelper::getTranslations($user->language_id);

            if ($user) {
                $token = $user->firebase_token;
                $body = $request->boolean('translatable') ? $translations[$request->get('body')] : $request->get('body');

                return event(new PodcastNotificationEvent($token, $id, $rid, $title, $body));
            }
        }
    }
}

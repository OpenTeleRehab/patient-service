<?php

namespace App\Http\Controllers;

use App\Events\PodcastNotificationEvent;
use App\Helpers\TranslationHelper;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void|null
     */
    public function pushNotification(Request $request)
    {
        $user = User::where('identity', $request->get('identity'))->firstOrFail();

        if ($user) {
            $translations = TranslationHelper::getTranslations($user->language_id);

            $token = $user->firebase_token;
            $id = $request->get('_id');
            $rid = $request->get('rid');
            $title = $request->get('title');
            $body = $request->boolean('translatable') ? $translations[$request->get('body')] : $request->get('body');

            return event(new PodcastNotificationEvent($token, $id, $rid, $title, $body));
        }
    }
}

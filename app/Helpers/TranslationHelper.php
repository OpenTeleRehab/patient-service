<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslationHelper
{
    /**
     * @return array
     */
    public static function getTranslations($language_id = '')
    {
        $translations = [];
        $requestTranslation = Http::get(env('ADMIN_SERVICE_URL') . '/translation/i18n/patient_app', [
            'lang' => Auth::user() ? Auth::user()->language_id : $language_id,
        ]);

        if (!empty($requestTranslation) && $requestTranslation->successful()) {
            $translationData = $requestTranslation->json()['data'];
            foreach ($translationData as $translation) {
                $translations[$translation['key']] = $translation['value'];
            }
        }

        return $translations;
    }
}

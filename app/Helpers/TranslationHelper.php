<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TranslationHelper
{
    /**
     * @return array
     */
    public static function getTranslations()
    {
        $translations = [];
        $requestTranslation = Http::get(env('ADMIN_SERVICE_URL') . '/api/translation/i18n/patient_app', [
            'lang' => Auth::user() ? Auth::user()->language_id : '',
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

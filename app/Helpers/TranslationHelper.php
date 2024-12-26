<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TranslationHelper
{
    /**
     * @param integer $language_id
     *
     * @return array
     */
    public static function getTranslations($language_id = '', $plaform = 'patient_app')
    {
        $translations = [];
        $requestTranslation = Http::get(env('ADMIN_SERVICE_URL') . '/translation/i18n/' . $plaform, [
            'lang' => Auth::user()?->language_id ?? $language_id
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

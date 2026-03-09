<?php

namespace App\Helpers;

/**
 * Class UserHelper
 * @package App\Helpers
 */
class UserHelper
{
    public static function getFullName($lastName, $firstName, $languageId)
    {
        $translations = TranslationHelper::getTranslations($languageId);
        $fullNameFormat = $translations['common.user.full_name'] ?? '${lastName} ${firstName}';
        return strtr($fullNameFormat, [
            '${lastName}'  => $lastName,
            '${firstName}' => $firstName,
        ]);
    }
}
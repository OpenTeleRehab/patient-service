<?php

namespace App\Helpers;

/**
 * @package App\Helpers
 */
class SMSHelper
{
    /**
     * @param string $to
     * @param string $locale
     *
     * @return void
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public static function sendCode($to, $locale = 'en')
    {
        $client = new \Twilio\Rest\Client(env('SMS_SID'), env('SMS_TOKEN'));
        $client->verify->v2->services(env('SMS_VERIFY_SERVICE_SID'))
            ->verifications
            ->create($to, 'sms', ['locale' => $locale, 'appHash' => env('MOBILE_APP_HASH')]);
    }

    /**
     * @param string $to
     * @param string $code
     *
     * @return \Twilio\Rest\Verify\V2\Service\VerificationCheckInstance
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public static function verifyCode($to, $code)
    {
        $client = new \Twilio\Rest\Client(env('SMS_SID'), env('SMS_TOKEN'));
        return $client->verify->v2->services(env('SMS_VERIFY_SERVICE_SID'))
            ->verificationChecks
            ->create($code, ['to' => $to]);
    }
}

<?php

namespace App\Helpers;

/**
 * @package App\Helpers
 */
class SMSHelper
{
    /**
     * @param string $to
     * @param string $hash
     * @param string $locale
     *
     * @return void
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public static function sendCode($to, $hash, $locale = 'en')
    {
        // Keep sending SMS for some excluded numbers.
        if (str_contains(env('SMS_VERIFY_EXCLUDE_NUMBERS', ''), $to)) {
            return;
        }

        $to = '+' . $to;
        $client = new \Twilio\Rest\Client(env('SMS_SID'), env('SMS_TOKEN'));
        $options = ['locale' => $locale];
        if ($hash) {
            $options['appHash'] = $hash;
        }
        $client->verify->v2->services(env('SMS_VERIFY_SERVICE_SID'))
            ->verifications
            ->create($to, 'sms', $options);
    }

    /**
     * @param string $to
     * @param string $code
     *
     * @return bool
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public static function verifyCode($to, $code)
    {
        // Make it as valid verification if it is excluded number.
        if (str_contains(env('SMS_VERIFY_EXCLUDE_NUMBERS', ''), $to)) {
            return true;
        }

        $to = '+' . $to;
        $client = new \Twilio\Rest\Client(env('SMS_SID'), env('SMS_TOKEN'));
        return $client->verify->v2->services(env('SMS_VERIFY_SERVICE_SID'))
            ->verificationChecks
            ->create($code, ['to' => $to])
            ->valid;
    }
}

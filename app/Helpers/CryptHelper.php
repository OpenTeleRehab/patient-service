<?php

namespace App\Helpers;

define('CRYPT_SECRET', base64_decode(env('CRYPT_SECRET')));
define('CRYPT_CIPHER', env('CRYPT_CIPHER'));

/**
 * Class CryptHelper
 * @package App\Helpers
 */
class CryptHelper
{
    public static function encrypt($plaintext)
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, CRYPT_CIPHER, CRYPT_SECRET, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($cipherText)
    {
        $raw = base64_decode($cipherText);
        $iv = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);
        return openssl_decrypt($encrypted, CRYPT_CIPHER, CRYPT_SECRET, OPENSSL_RAW_DATA, $iv);
    }

    public static function hash($cipherText)
    {
        $password = self::decrypt($cipherText);
        return hash('sha256', $password);
    }
}
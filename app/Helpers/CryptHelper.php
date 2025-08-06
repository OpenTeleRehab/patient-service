<?php

namespace App\Helpers;

define('CRYPT_SECRET', base64_decode(env('CRYPT_SECRET')));

/**
 * Class CryptHelper
 * @package App\Helpers
 */
class CryptHelper
{
    public static function encrypt($plaintext)
    {
        $iv = random_bytes(16); // 16 bytes for AES-256-CBC
        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', CRYPT_SECRET, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($cipherText)
    {
        $raw = base64_decode($cipherText);
        $iv = substr($raw, 0, 16); // 16 bytes for AES-256-CBC
        $encrypted = substr($raw, 16);
        return openssl_decrypt($encrypted, 'aes-256-cbc', CRYPT_SECRET, 0, $iv);
    }

    public static function hash($cipherText)
    {
        $password = self::decrypt($cipherText);
        return hash('sha256', $password);
    }
}
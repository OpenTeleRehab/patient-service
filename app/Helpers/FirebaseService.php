<?php

namespace App\Helpers;

use Exception;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @package App\Helpers
 */
class FirebaseService
{
    private const TOKEN_CACHE_KEY = 'firebase_access_token';
    private const TOKEN_CACHE_TTL = 3000; // 50 minutes (tokens valid for 1 hour)

    /**
     * @param array $message
     *
     * @return void
     * @throws Exception
     */
    public static function send(array $message)
    {
        $data = json_encode(['message' => $message]);

        $headers = [
            'Authorization: Bearer ' . self::getAccessToken(),
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        $projectId = env('FIREBASE_PROJECT_ID');
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        return curl_exec($ch);
    }

    /**
     * Get Firebase access token with caching
     *
     * @return string
     * @throws Exception
     */
    private static function getAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_CACHE_TTL, function () {
            return self::fetchNewAccessToken();
        });
    }

    /**
     * Fetch a new access token from Firebase
     *
     * @return string
     * @throws Exception
     */
    private static function fetchNewAccessToken(): string
    {
        try {
            $keyPath = self::getServiceAccountPath();

            if (!file_exists($keyPath)) {
                throw new \RuntimeException("Firebase service account file not found at: {$keyPath}");
            }

            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = new ServiceAccountCredentials($scopes, $keyPath);

            $authToken = $credentials->fetchAuthToken();

            if (!isset($authToken['access_token'])) {
                throw new \RuntimeException('Failed to retrieve access token from Firebase');
            }

            return $authToken['access_token'];

        } catch (Exception $e) {
            Log::error('Firebase token generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception('Unable to generate Firebase access token: ' . $e->getMessage());
        }
    }

    /**
     * Get the service account file path
     *
     * @return string
     * @throws Exception
     */
    private static function getServiceAccountPath(): string
    {
        $filename = env('FIREBASE_SERVICE_ACCOUNT_FILE');

        if (empty($filename)) {
            throw new \RuntimeException('FIREBASE_SERVICE_ACCOUNT_FILE environment variable is not set');
        }

        return storage_path($filename);
    }
}

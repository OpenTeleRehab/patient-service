<?php

namespace App\Helpers;

/**
 * @package App\Helpers
 */
class ApiHelper
{
    /**
     * @param string $stage
     * @param string $apiName
     * @param string $subDomain
     * @param string $orgType
     *
     * @return string
     */
    public static function createApiUrl($stage, $apiName, $subDomain, $orgType)
    {
        $apiUrl = '';
        switch ($stage) {
            case 'local':
                $urlString = $orgType == 'hi' ? $stage . '-hi-' . ($apiName == 'websocket' || $apiName == 'chat' ? 'chat' : 'admin') . '.' : ($apiName == 'websocket' || $apiName == 'chat' ? 'chat' : 'admin') . '-';
                if ($apiName == 'websocket') {
                    $apiUrl = 'wss://' . $urlString . env('APP_DOMAIN') . '/websocket';
                    break;
                } elseif ($apiName == 'chat') {
                    $apiUrl = 'https://' . $urlString . env('APP_DOMAIN');
                    break;
                } else {
                    $apiUrl = 'https://' . $urlString . env('APP_DOMAIN') . '/api/' . $apiName;
                    break;
                }
            case 'demo':
                $urlString = $orgType == 'hi' ? $stage . '-' . ($apiName == 'websocket' || $apiName == 'chat' ? 'chat' : 'admin') : ($apiName == 'websocket' || $apiName == 'chat' ? 'chat' : 'admin');
                if ($apiName == 'websocket') {
                    $apiUrl = 'wss://' . $urlString . '-' . env('APP_DOMAIN') . '/websocket';
                    break;
                } elseif ($apiName == 'chat') {
                    $apiUrl = 'https://' . $urlString . '-' . env('APP_DOMAIN');
                    break;
                } else {
                    $apiUrl = 'https://' . $urlString . '-' . env('APP_DOMAIN') . '/api/' . $apiName;
                    break;
                }
            case 'live':
                $urlString = $orgType == 'hi' ? ($apiName == 'websocket' || $apiName == 'chat' ? 'chat' : 'admin') . '.' : ($apiName == 'websocket' || $apiName == 'chat' ? 'chat' : 'admin') . '-';
                if ($apiName == 'websocket') {
                    $apiUrl = 'wss://' . $urlString . env('APP_DOMAIN') . '/websocket';
                    break;
                } elseif ($apiName == 'chat') {
                    $apiUrl = 'https://' . $urlString . env('APP_DOMAIN');
                    break;
                } else {
                    $apiUrl = 'https://' . $urlString . env('APP_DOMAIN') . '/api/' . $apiName;
                    break;
                }
            default:
        }
        return $apiUrl;
    }
}

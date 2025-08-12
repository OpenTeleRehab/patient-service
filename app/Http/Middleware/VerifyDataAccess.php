<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Forwarder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class VerifyDataAccess
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return \Symfony\Component\HttpFoundation\Response
   */
    public function handle(Request $request, Closure $next): Response
    {
        $countryHeader = $request->header('Country');
        $countryId     = $request->get('country_id') ?? $request->get('country');
        $clinicId      = $request->get('clinic_id') ?? $request->get('clinic');
        $therapistId   = $request->get('therapist_id') ?? $request->get('therapist');
        $patientId     = $request->get('patient_id');

        $user = auth()->user();
        $accessDenied = false;

        // Early exit: skip validation if minimal params or backend client
        if (
            (!isset($countryHeader) && !isset($countryId) && !isset($clinicId) && !isset($therapistId)) ||
            ($user && $user->email === env('KEYCLOAK_BACKEND_CLIENT'))
        ) {
            return $next($request);
        }

        // Verify if the auth user belongs to their assigned country
        if ($user && $countryId && (int)$user->country_id !== (int)$countryId) {
            $accessDenied = true;
        }

        // Verify if the auth user belongs to their assigned clinic
        if ($user && $clinicId && (int)$user->clinic_id !== (int)$clinicId) {
            $accessDenied = true;
        }

        // Verify if the auth user therapist is the same as the requested therapist id
        if ($user && $therapistId && (int)$user->therapist_id !== (int)$therapistId) {
            $accessDenied = true;
        }

        // Verify if the auth user is the same as the requested patient id
        if ($user && $patientId && (int)$user->id !== (int)$patientId) {
            $accessDenied = true;
        }

        // Country header check
        if ($countryHeader) {
            $country = Cache::remember("country_iso_code_{$countryHeader}", 86400, function () use ($countryHeader) {
                $accessToken = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
                $response = Http::withToken($accessToken)->get(
                    env('ADMIN_SERVICE_URL') . '/get-country-by-iso-code',
                    ['iso_code' => $countryHeader]
                );

                return $response->successful() ? $response->json('data') : null;
            });

            if (!$country || !isset($country['id'])) {
                return response()->json(['message' => 'Invalid or unrecognized country.'], 404);
            }

            if ($user && (int)$user->country_id !== (int)$country['id']) {
                $accessDenied = true;
            }
        }

        if ($accessDenied) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        return $next($request);
    }
}

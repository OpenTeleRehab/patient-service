<?php

namespace App\Http\Middleware;

use App\Models\Forwarder;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class VerifyDataAccess
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $countryHeader = $request->header('Country');
        $countryId = $request->get('country_id') ?? $request->get('country');
        $clinicId = $request->get('clinic_id') ?? $request->get('clinic');
        $therapistId = $request->get('therapist_id') ?? $request->get('therapist');
        $patientId = $request->get('patient_id');
        $user = Auth::user();

        if ($user->email == env('KEYCLOAK_BACKEND_CLIENT')) {
            if (!empty($request->header('int-country-id'))) {
                $user->country_id = (int)$request->header('int-country-id');
            }

            if (!empty($request->header('int-region-id'))) {
                $user->region_id = (int)$request->header('int-region-id');
            }

            if (!empty($request->header('int-province-id'))) {
                $user->province_id = (int)$request->header('int-province-id');
            }

            if (!empty($request->header('int-therapist-user-id'))) {
                $user->therapist_user_id = (int)$request->header('int-therapist-user-id');
            }

            if (!empty($request->header('int-clinic-id'))) {
                $user->clinic_id = (int)$request->header('int-clinic-id');
            }

            if (!empty($request->header('int-phc-service-id'))) {
                $user->phc_service_id = (int)$request->header('int-phc-service-id');
            }

            if (!empty($request->header('int-user-type'))) {
                $user->user_type = $request->header('int-user-type');
            }

            if (!empty($request->header('int-language-id'))) {
                $user->language_id = (int)$request->header('int-language-id');
            }

            if (!empty($request->header('int-admin-user-id'))) {
                $user->admin_user_id = (int)$request->header('int-admin-user-id');
            }

            if (!empty($request->header('int-region-ids'))) {
                $user->region_ids = $request->header('int-region-ids');
            }
        }

        $deny = fn() => response()->json(['message' => 'Access denied'], 403);

        // Early exit: skip validation if minimal params or backend client
        if ($user && $user->email === env('KEYCLOAK_BACKEND_CLIENT')) {
            if ($patientId) {
                $query = User::withTrashed()->where('id', (int)$patientId);

                // Add filters only if values exist
                if ($countryId) {
                    $query->where('country_id', (int)$countryId);
                }

                if ($user->user_type === User::GROUP_THERAPIST && $clinicId) {
                    $query->where('clinic_id', (int)$clinicId);
                }

                if ($user->user_type === User::GROUP_PHC_WORKER && $user->phc_service_id) {
                    $query->where('phc_service_id', (int)$user->phc_service_id);
                }

                $id = $therapistId ?? $user->therapist_user_id;
                if ($id) {
                    if ($user->user_type === User::GROUP_THERAPIST) {
                        $query->where(function ($q) use ($id) {
                            $q->where('therapist_id', (int)$id)
                                ->orWhereJsonContains('secondary_therapists', (int)$id);
                        });

                    } else if ($user->user_type === User::GROUP_PHC_WORKER) {
                        $query->where(function ($q) use ($id) {
                            $q->where('phc_worker_id', (int)$id)
                                ->orWhereJsonContains('supplementary_phc_workers', (int)$id);
                        });
                    }
                }

                // If no matching user found, deny access
                if (!$query->exists()) {
                    return $deny();
                }
            }

            return $next($request);
        }

        // Verify if the auth user belongs to their assigned country
        if ($user && isset($countryId) && (int)$user->country_id !== (int)$countryId) {
            return $deny();
        }

        // Verify if the auth user belongs to their assigned clinic
        if ($user && isset($clinicId) && (int)$user->clinic_id !== (int)$clinicId) {
            return $deny();
        }

        // Verify if the auth user therapist or phc worker is the same as the requested therapist/phc worker id
        if ($user && isset($therapistId)) {
            // For therapist's patient user
            if ($user->therapist_id && !$user->phc_worker_id && (int)$user->therapist_id !== (int)$therapistId && !in_array((int) $therapistId, $user->secondary_therapists ?? [])) {
                return $deny();
            } else if ($user->therapist_id && $user->phc_worker_id && (int)$user->therapist_id !== (int)$therapistId && (int)$user->phc_worker_id !== (int)$therapistId && !in_array((int) $therapistId, $user->supplementary_phc_workers ?? [])) {
                // For therapist & phc worker's share patient user
                return $deny();
            } else if ($user->phc_worker_id && !$user->therapist_id && (int)$user->phc_worker_id !== (int)$therapistId && !in_array((int) $therapistId, $user->supplementary_phc_workers ?? [])) {
                // For phc worker's patient user
                return $deny();
            } else if ($patientId) {
                $hasAccess = User::where('id', $patientId)
                    ->where(function ($q) use ($therapistId) {
                        $q->where('therapist_id', $therapistId)->orWhere('phc_worker_id', $therapistId)
                            ->orWhereJsonContains('secondary_therapists', (int)$therapistId)
                            ->orWhereJsonContains('supplementary_phc_workers', (int)$therapistId);
                    })
                    ->exists();
                if (!$hasAccess) {
                    return $deny();
                }
            }
        }

        // Verify if the auth user is the same as the requested patient id
        if ($user && isset($patientId) && (int)$user->id !== (int)$patientId) {
            return $deny();
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

            if ($user && $user->country_id !== (int)$country['id']) {
                return $deny();
            }
        }

        return $next($request);
    }
}

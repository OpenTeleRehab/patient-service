<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TherapistController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getOwnTherapists()
    {
        if (Auth::check()) {
            $patient = Auth::user();

            $therapistIds = $patient->secondary_therapists;
            $therapistIds[] = $patient->therapist_id;

            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

            $response = Http::withToken($access_token)
                ->get(env('THERAPIST_SERVICE_URL') . '/patient/therapist-by-ids', [
                    'ids' => json_encode($therapistIds),
                ])
                ->json();

            return response()->json([
                'success' => true,
                ...$response,
            ]);
        }

        return response()->json([
            'error' => 'Unauthorized',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getByIds(Request $request)
    {
        // TODO: Remove after parse access token from patient app
        if ($request->get('ids')) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

            $response = Http::withToken($access_token)
                ->get(env('THERAPIST_SERVICE_URL') . '/patient/therapist-by-ids', [
                    'ids' => $request->get('ids'),
                ])
                ->json();

            return response()->json([
                'success' => true,
                ...$response,
            ]);
        }
    }
}

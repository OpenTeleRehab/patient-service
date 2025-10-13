<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TherapistController extends Controller
{
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
}

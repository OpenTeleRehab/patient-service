<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\Forwarder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use App\Http\Resources\ReferralResource;
use App\Models\User;

class ReferralController extends Controller
{
    /**
     * Display a list of referrals for the authenticated user's clinic.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing the collection of referrals.
     */
    public function index()
    {
        $referrals = Referral::where('to_clinic_id', Auth::user()?->clinic_id)
            ->where('status', Referral::STATUS_INVITED)
            ->get();

        $phcWorkerResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/phc-workers/all')
            ->json('data', []);

        $phcWorkers = collect($phcWorkerResponse)->keyBy('id');

        $referrals->transform(function ($referral) use ($phcWorkers) {
            $phcNames = [];

            $leadWorkerId = $referral->patient->phc_worker_id;
            $leadWorker = $phcWorkers[$leadWorkerId] ?? null;

            if ($leadWorker) {
                $phcNames[] = $leadWorker['first_name'] . ' ' . $leadWorker['last_name'];
            }

            $supplementaryWorkerIds = (array) ($referral->patient->supplementary_phc_workers ?? []);

            $supplementaryPhcWorkerNames = collect($supplementaryWorkerIds)
                ->map(fn($id) => $phcWorkers[$id] ?? null)
                ->filter()
                ->map(fn($worker) => $worker['first_name'] . ' ' . $worker['last_name'])
                ->toArray();

            $referral->lead_and_supplementary_phc = array_merge($phcNames, $supplementaryPhcWorkerNames);

            $referral->referred_by = $referral->lead_and_supplementary_phc[0] ?? null;

            return $referral;
        });

        return response()->json(['data' => ReferralResource::collection($referrals)], 200);
    }

    /**
     * Store a new referral for a patient.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request containing:
     *      - 'patient_id' (int): The ID of the patient to be referred. Must exist in `users` table.
     *      - 'to_clinic_id' (int): The ID of the clinic where the patient is referred.
     *
     * @return \Illuminate\Http\JsonResponse
     *      - 201: success_message.referral.create.
     *      - 422: patient.pending_referral.
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();
        $validatedData = $request->validate([
            'patient_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) use ($authUser) {
                    $query->where('phc_worker_id', $authUser->therapist_user_id);
                }),
            ],
            'to_clinic_id' => 'required|integer|min:0',
            'request_reason' => 'required|string'
        ], [
            'patient_id.exists' => 'this_patient.not_belong_to.you',
        ]);

        $hasPendingReferral = User::findOrFail($validatedData['patient_id'])
            ->referrals()
            ->where('status', Referral::STATUS_INVITED)
            ->exists();

        if ($hasPendingReferral) {
            return response()->json(['message' => 'patient.pending_referral'], 422);
        }

        Referral::create($validatedData);

        return response()->json(['message' => 'success_message.referral.create'], 201);
    }

    /**
     * Decline a specific referral.
     *
    * @param Request $request The incoming HTTP request containing the decline reason.
    * @param int $id The ID of the referral to decline.
    *
    * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the referral with the given ID does not exist.
    * @throws \Illuminate\Validation\ValidationException If the 'reason' field fails validation.
    *
    * @return \Illuminate\Http\JsonResponse JSON response with a success message.
     */
    public function decline(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);

        $validatedData = $request->validate([
            'reject_reason' => 'required|string',
        ]);

        $validatedData['status'] = Referral::STATUS_DECLINED;

        $referral->update($validatedData);

        return response()->json(['message' => 'success_message.referral.decline'], 200);
    }

    /**
     * Count referrals by auth user's clinic
     */
    public function countReferrals()
    {
        $authUser = Auth::user();

        $count = Referral::where('to_clinic_id', $authUser->clinic_id)->where('status', Referral::STATUS_INVITED)->count();

        return response()->json(['data' => $count], 200);
    }
}

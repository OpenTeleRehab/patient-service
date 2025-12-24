<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use Illuminate\Http\Request;
use App\Models\ReferralAssignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\ReferralAssignmentResource;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

class ReferralAssignmentController extends Controller
{
    /**
     * Retrieve all referral assignments for the authenticated therapist.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing the collection of referral assignments.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $pageSize = $request->get('page_size', 10);

        $referralAssignments = ReferralAssignment::where('status', ReferralAssignment::STATUS_INVITED)
            ->where('therapist_id', $user->therapist_user_id)->paginate($pageSize);

        $info = [
            'current_page' => $referralAssignments->currentPage(),
            'total_count' => $referralAssignments->total(),
        ];

        $phcWorkerResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/phc-workers/all')
            ->json('data', []);

        $phcWorkers = collect($phcWorkerResponse)->keyBy('id');

        $referralAssignments->transform(function ($assignment) use ($phcWorkers) {
            $phcNames = [];

            $leadWorkerId = $assignment->referral->patient->phc_worker_id;
            $leadWorker = $phcWorkers[$leadWorkerId] ?? null;

            if ($leadWorker) {
                $phcNames[] = $leadWorker['first_name'] . ' ' . $leadWorker['last_name'];
            }

            $supplementaryWorkerIds = (array) ($assignment->referral->patient->supplementary_phc_workers ?? []);

            $supplementaryPhcWorkerNames = collect($supplementaryWorkerIds)
                ->map(fn($id) => $phcWorkers[$id] ?? null)
                ->filter()
                ->map(fn($worker) => $worker['first_name'] . ' ' . $worker['last_name'])
                ->toArray();

            $assignment->lead_and_supplementary_phc = array_merge($phcNames, $supplementaryPhcWorkerNames);

            $assignment->referred_by = $assignment->lead_and_supplementary_phc[0] ?? null;

            return $assignment;
        });

        return response()->json(['data' => ReferralAssignmentResource::collection($referralAssignments), 'info' => $info], 200);
    }

    /**
     * Create a new referral assignment.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request containing:
     *      - 'therapist_id' (int): The ID of the therapist to assign the referral to.
     *      - 'referral_id' (int): The ID of the referral to be assigned. Must exist in `referrals` table.
     *
     * @return \Illuminate\Http\JsonResponse
     *      - 201: success_message.referral_assignment.create.
     *      - 422: referral_assignment.already_assigned.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'therapist_id' => 'required|integer',
            'referral_id' => 'required|exists:referrals,id',
        ]);

        $exists = ReferralAssignment::where('referral_id', $validatedData['referral_id'])
            ->where('status', ReferralAssignment::STATUS_INVITED)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'referral_assignment.already_assigned'], 422);
        }

        ReferralAssignment::create($validatedData);

        return response()->json(['message' => 'success_message.referral_assignment.create'], 201);
    }

    /**
     * Accept referral that assigned to therapist
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function accept($id)
    {
        $referralAssignment = ReferralAssignment::findOrFail($id);
        $authUser = Auth::user();

        DB::transaction(function () use ($authUser, $referralAssignment) {
            $referralAssignment->referral->patient->update(['therapist_id' => $authUser->therapist_user_id]);
            $referralAssignment->referral()->update(['status' => Referral::STATUS_ACCEPTED]);

            $referralAssignment->update(['status' => ReferralAssignment::STATUS_ACCEPTED]);

            Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                ->post(env('THERAPIST_SERVICE_URL') . '/chat/create-room-for-users', [
                    'therapist_id' => $authUser->therapist_user_id,
                    'phc_worker_id' => $referralAssignment->referral->phc_worker_id,
                ]);
        });

        return response()->json(['message' => 'success_message.referral.accepted'], 200);
    }

    /**
     * Decline referral that assigned to therapist
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function decline(Request $request, $id)
    {
        $validatedData = $request->validate([
            'reason' => 'required|string',
        ]);

        $validatedData['status'] = ReferralAssignment::STATUS_DECLINED;

        $referralAssignment = ReferralAssignment::findOrFail($id);

        $referralAssignment->update($validatedData);

        return response()->json(['message' => 'success_message.referral.declined'], 200);
    }

    /**
     * Count referral assignments that assigned to therapist
     */
    public function countReferralAssignments()
    {
        $authUser = Auth::user();

        $counts = ReferralAssignment::where('therapist_id', $authUser->therapist_user_id)->where('status', ReferralAssignment::STATUS_INVITED)->count();

        return response()->json(['data' => $counts], 200);
    }
}

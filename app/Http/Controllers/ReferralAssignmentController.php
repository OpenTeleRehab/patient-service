<?php

namespace App\Http\Controllers;

use App\Mail\PatientReferralMail;
use App\Models\Forwarder;
use Illuminate\Http\Request;
use App\Models\ReferralAssignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\ReferralAssignmentResource;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Helpers\UserHelper;

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
        $data = $request->all();
        $pageSize = $request->get('page_size', 10);

        $query = ReferralAssignment::where('status', ReferralAssignment::STATUS_INVITED)
            ->whereHas('referral')
            ->where('therapist_id', $user->therapist_user_id);

        if (isset($data['search_value'])) {
            $query->whereHas('referral.patient', function ($q) use ($data) {
                $q->where('identity', 'like', '%' . $data['search_value'] . '%')
                ->orWhere('first_name', 'like', '%' . $data['search_value'] . '%')
                ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%');
            });
        }

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'date_of_birth') {
                        $date = date_create_from_format('d/m/Y', $filterObj->value);
                        $query->whereHas('referral.patient', function ($q) use ($date) {
                            $q->where('date_of_birth', $date->format('Y-m-d'));
                        });
                    } elseif ($filterObj->columnName === 'last_name') {
                        $query->whereHas('referral.patient', function ($q) use ($filterObj) {
                            $q->where('last_name', 'like', '%' . $filterObj->value . '%');
                        });
                    } elseif ($filterObj->columnName === 'identity') {
                        $query->whereHas('referral.patient', function ($q) use ($filterObj) {
                            $q->where('identity', 'like', '%' . $filterObj->value . '%');
                        });
                    } elseif ($filterObj->columnName === 'first_name') {
                        $query->whereHas('referral.patient', function ($q) use ($filterObj) {
                            $q->where('first_name', 'like', '%' . $filterObj->value . '%');
                        });
                    } elseif ($filterObj->columnName === 'request_reason' && $filterObj->value !== '') {
                        $query->whereHas('referral', function ($q) use ($filterObj) {
                            $q->where('request_reason', 'like', '%' . $filterObj->value . '%');
                        });
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        $referralAssignments = $query->paginate($pageSize);

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
            'accepted_by' => 'required|integer',
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
            $referralAssignment->referral->patient->update([
                'therapist_id' => $authUser->therapist_user_id,
                'clinic_id' => $authUser->clinic_id,
            ]);
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

        $counts = ReferralAssignment::where('therapist_id', $authUser->therapist_user_id)
                ->where('status', ReferralAssignment::STATUS_INVITED)
                ->whereHas('referral')
                ->count();

        return response()->json(['data' => $counts], 200);
    }

    /**
     * Counter the latest referral for a given patient.
     *
     * @param int $patientId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function counterReferral($patientId)
    {
        $patient = User::findOrFail($patientId);

        $referralAssignment = $patient->lastReferral?->referralAssignments()
            ->where('status', ReferralAssignment::STATUS_ACCEPTED)
            ->latest()
            ->first();

        if (!$referralAssignment) {
            return response()->json(['success' => false, 'message' => 'counter_referral.not_found'], 404);
        }

        DB::transaction(function () use ($patient) {
            $adminAccessToken = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            $therapistAccessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

            Http::withToken($adminAccessToken)->post(env('ADMIN_SERVICE_URL') . '/notifications/patient-counter-referral', [
                'phc_service_id' => $patient->phc_service_id,
                'therapist_id' => $patient->therapist_id,
            ])->throw();

            $healthcareWorker = Http::withToken($therapistAccessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $patient->phc_worker_id,
            ])->throw();

            $therapist = Http::withToken($therapistAccessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $patient->therapist_id,
            ])->throw();

            if ($healthcareWorker->successful()) {
                $healthcareWorker = $healthcareWorker->json();

                if ($healthcareWorker['notify_email']) {
                    Mail::to($healthcareWorker['email'])->send(
                        new PatientReferralMail(
                            'therapist-counter-refers-a-patient-for-healthcare-worker',
                            UserHelper::getFullName($healthcareWorker['last_name'], $healthcareWorker['first_name'], $healthcareWorker['language_id']),
                            UserHelper::getFullName($therapist['last_name'], $therapist['first_name'], $healthcareWorker['language_id']),
                            $healthcareWorker['language_id'],
                        )
                    );
                }
            }

            $patient->update([
                'therapist_id' => null,
                'clinic_id' => null,
                'secondary_therapists' => null,
            ]);

            $patient->referrals()->forceDelete();
        });

        return response()->json(['message' => 'success_message.counter_referral'], 200);
    }
}

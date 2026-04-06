<?php

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
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
     *
     * @param Request $request The incoming HTTP request.
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $authUser = Auth::user();
        $query = Referral::where('to_clinic_id', $authUser?->clinic_id)->where('status', Referral::STATUS_INVITED);

        if (isset($data['search_value'])) {
            $query->whereHas('patient', function ($q) use ($data) {
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
                        $dates = explode(' - ', $filterObj->value);
                        $startDate = date_create_from_format('d/m/Y', $dates[0]);
                        $endDate = date_create_from_format('d/m/Y', $dates[1]);
                        $query->whereHas('patient', function ($q) use ($startDate, $endDate) {
                            $q->whereBetween('date_of_birth', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                        });
                    } elseif ($filterObj->columnName === 'referral_status') {
                        $query->whereHas('latestReferralAssignment', function ($q) use ($filterObj) {
                            $q->where('status', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'last_name') {
                        $query->whereHas('patient', function ($q) use ($filterObj) {
                            $q->where('last_name', 'like', '%' . $filterObj->value . '%');
                        });
                    } elseif ($filterObj->columnName === 'first_name') {
                        $query->whereHas('patient', function ($q) use ($filterObj) {
                            $q->where('first_name', 'like', '%' . $filterObj->value . '%');
                        });
                    } elseif ($filterObj->columnName === 'request_reason' && $filterObj->value !== '') {
                        $query->where('request_reason', 'like', '%' . $filterObj->value . '%');
                    } elseif ($filterObj->columnName === 'therapist_reason' && $filterObj->value !== '') {
                        $query->whereHas('latestReferralAssignment', function ($q) use ($filterObj) {
                            $q->where('reason', 'like', '%' . $filterObj->value . '%');
                        });
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        $referrals = $query->paginate($data['page_size']);
        $info = [
            'current_page' => $referrals->currentPage(),
            'last_page' => $referrals->lastPage(),
            'total_count' => $referrals->total(),
        ];

        $phcWorkerResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/phc-workers/all')
            ->json('data', []);

        $phcWorkers = collect($phcWorkerResponse)->keyBy('id');

        $referralsCollection = collect($referrals->items());

        $mappedReferrals = collect($referralsCollection)->map(function ($referral) use ($phcWorkers, $authUser) {
            $phcNames = [];

            $leadWorkerId = $referral->patient->phc_worker_id;
            $leadWorker = $phcWorkers[$leadWorkerId] ?? null;

            if ($leadWorker) {
                $phcNames[] = UserHelper::getFullName($leadWorker['last_name'], $leadWorker['first_name'], $authUser?->language_id);
            }

            $supplementaryWorkerIds = (array) ($referral->patient->supplementary_phc_workers ?? []);

            $supplementaryPhcWorkerNames = collect($supplementaryWorkerIds)
                ->map(fn($id) => $phcWorkers[$id] ?? null)
                ->filter()
                ->map(fn($worker) => UserHelper::getFullName($worker['last_name'], $worker['first_name'], $authUser?->language_id))
                ->toArray();

            $referral->lead_and_supplementary_phc = array_merge($phcNames, $supplementaryPhcWorkerNames);

            $referral->referred_by = $referral->lead_and_supplementary_phc[0] ?? null;

            return $referral;
        });

        return response()->json(['data' => ReferralResource::collection($mappedReferrals), 'info' => $info], 200);
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
            'to_region_id' => 'required|integer|min:0',
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

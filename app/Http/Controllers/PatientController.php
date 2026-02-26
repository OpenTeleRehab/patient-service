<?php

namespace App\Http\Controllers;

use App\Exports\ChatExport;
use App\Exports\PatientProfileExport;
use App\Exports\TreatmentPlanExport;
use App\Helpers\RocketChatHelper;
use App\Helpers\TherapistServiceHelper;
use App\Http\Resources\PatientForTherapistRemoveResource;
use App\Http\Resources\PatientOfRemovePhcWorkerResource;
use App\Http\Resources\PatientRawDataResource;
use App\Http\Resources\PatientList2Resource;
use App\Http\Resources\PatientListResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\UserChatResource;
use App\Http\Resources\PatientListMobileResource;
use App\Models\Activity;
use App\Models\Forwarder;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Mpdf\Output\Destination;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;
use App\Helpers\CryptHelper;
use App\Models\Appointment;

class PatientController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/patient",
     *     tags={"Patient"},
     *     summary="Patient list",
     *     operationId="patientList",
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();

        $query = User::query();

        if ($user->user_type === User::GROUP_THERAPIST) {
            $query->with('lastReferral')->where(function ($query) use ($user) {
                $query->where('therapist_id', $user->therapist_user_id)->orWhereJsonContains('secondary_therapists', intval($user->therapist_user_id));
            });
        }

        if ($user->user_type === User::GROUP_PHC_WORKER) {
            $query->with('lastReferral')->where(function ($query) use ($user) {
                $query->where('phc_worker_id', $user->therapist_user_id)->orWhereJsonContains('supplementary_phc_workers', intval($user->therapist_user_id));
            });
        }

        if (isset($data['enabled'])) {
            $query->where('enabled', boolval($data['enabled']));
        }

        if (isset($data['search_value'])) {
            if ($request->get('type') === User::ADMIN_GROUP_GLOBAL_ADMIN) {
                $query->where(function ($query) use ($data) {
                    $query->where('identity', 'like', '%' . $data['search_value'] . '%');
                });
            } else {
                $query->where(function ($query) use ($data) {
                    $query->where('identity', 'like', '%' . $data['search_value'] . '%')
                        ->orWhere('first_name', 'like', '%' . $data['search_value'] . '%')
                        ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%')
                        ->orWhereHas('appointments', function ($query) use ($data) {
                            $query->where('start_date', '>', Carbon::now())
                                ->limit(1);
                        })->whereHas('appointments', function (Builder $query) use ($data) {
                            $query->whereDate('start_date', 'like', '%' . $data['search_value'] . '%');
                        })->orWhereHas('treatmentPlans', function (Builder $query) use ($data) {
                            $query->where('name', 'like', '%' . $data['search_value'] . '%');
                        });
                });
            }
        }

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $therapist_id = $data['therapist_id'] ?? '';
            $phcWorkerId = $user->user_type === User::GROUP_PHC_WORKER ? $user->therapist_user_id : '';
            $query->where(function ($query) use ($filters, $therapist_id, $phcWorkerId) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'date_of_birth') {
                        $dateOfBirth = date_create_from_format('d/m/Y', $filterObj->value);
                        $query->where('date_of_birth', date_format($dateOfBirth, config('settings.defaultTimestampFormat')));
                    } elseif (($filterObj->columnName === 'region' || $filterObj->columnName === 'clinic') && $filterObj->value !== '') {
                        $query->where('clinic_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'country' && $filterObj->value !== '') {
                        $query->where('country_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'phc_service' && $filterObj->value !== '') {
                        $query->where('phc_service_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'treatment_status') {
                        if ($filterObj->value == User::FINISHED_TREATMENT_PLAN) {
                            $query->whereHas('treatmentPlans', function (Builder $query) {
                                $query->whereDate('end_date', '<', Carbon::now());
                            })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                $query->whereDate('end_date', '>', Carbon::now());
                            })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                $query->whereDate('start_date', '<=', Carbon::now())
                                    ->whereDate('end_date', '>=', Carbon::now());
                            });
                        } elseif ($filterObj->value == User::PLANNED_TREATMENT_PLAN) {
                            $query->whereHas('treatmentPlans', function (Builder $query) {
                                $query->whereDate('end_date', '>', Carbon::now());
                            })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                $query->whereDate('start_date', '<=', Carbon::now())
                                    ->whereDate('end_date', '>=', Carbon::now());
                            });
                        } else {
                            $query->whereHas('treatmentPlans', function (Builder $query) {
                                $query->whereDate('start_date', '<=', Carbon::now())
                                    ->whereDate('end_date', '>=', Carbon::now());
                            });
                        }
                    } elseif ($filterObj->columnName === 'age') {
                        $query->whereRaw('YEAR(NOW()) - YEAR(date_of_birth) = ? OR ABS(MONTH(date_of_birth) - MONTH(NOW())) = ?  OR ABS(DAY(date_of_birth) - DAY(NOW())) = ?', [$filterObj->value, $filterObj->value, $filterObj->value]);
                    } elseif ($filterObj->columnName === 'ongoing_treatment_plan') {
                        $query->whereHas('treatmentPlans', function (Builder $query) use ($filterObj) {
                            $query->where('name', 'like', '%' .  $filterObj->value . '%');
                        });
                    } elseif ($filterObj->columnName === 'secondary_therapist') {
                        if ($filterObj->value == User::SECONDARY_TERAPIST) {
                            $query->where(function ($query) use ($therapist_id) {
                                $query->whereJsonContains('secondary_therapists', intval($therapist_id));
                            });
                        } else {
                            $query->where(function ($query) use ($therapist_id) {
                                $query->where('secondary_therapists',  'like', '%[]%');
                            });
                        }
                    } elseif ($filterObj->columnName === 'supplementary_phc_worker') {
                        if ($filterObj->value == User::SUPPLEMENTARY_PHC_WORKER) {
                            $query->where(function ($query) use ($phcWorkerId) {
                                $query->whereJsonContains('supplementary_phc_workers', intval($phcWorkerId));
                            });
                        }
                    } elseif ($filterObj->columnName === 'next_appointment' && $filterObj->value !== '') {
                        $nexAppointment = date_create_from_format('d/m/Y', $filterObj->value);
                        $query->whereHas('appointments', function (Builder $query) use ($filterObj) {
                            $query->where('start_date', '>', Carbon::now())
                                ->limit(1);
                        })->whereHas('appointments', function (Builder $query) use ($nexAppointment) {
                            $query->whereDate('start_date', '=', date_format($nexAppointment, config('settings.defaultTimestampFormat')));
                        });
                    } elseif ($filterObj->columnName === 'transfer') {
                        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                            ->get(env('THERAPIST_SERVICE_URL') . '/transfer/retrieve', [
                                'user_id' => $therapist_id ?: $phcWorkerId,
                                'status' => $filterObj->value,
                                'therapist_type' => 'lead',
                            ]);

                        if ($response->successful()) {
                            $transferPatients = $response->json();
                            $data = $transferPatients['data'] ?? [];
                            $patientIds = array_unique(array_column($data, 'patient_id'));
                            $query->whereIn('id', $patientIds);
                        }
                    } elseif ($filterObj->columnName === 'referral_status') {
                        $query->whereHas('lastReferral', function (Builder $query) use ($filterObj) {
                            $query->where('status', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'date_of_birth_range') {
                        $dateOfBirthFrom = date_create_from_format('d/m/Y', $filterObj->from);
                        $dateOfBirthTo = date_create_from_format('d/m/Y', $filterObj->to);
                        $query->whereBetween('date_of_birth', [date_format($dateOfBirthFrom, config('settings.defaultTimestampFormat')), date_format($dateOfBirthTo, config('settings.defaultTimestampFormat'))]);
                    } elseif ($filterObj->columnName === 'health_condition_groups' && $filterObj->value !== '') {
                        // Fetch ID from AdminService
                        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
                            ->get(env('ADMIN_SERVICE_URL') . '/health-condition-groups/find', [
                                'title' => $filterObj->value
                            ]);
                        if ($response->successful()) {
                            $ids = collect($response->json()['data'])->pluck('id')->toArray();
                            $query->whereHas('treatmentPlans', function (Builder $q) use ($ids) {
                                $q->whereIn('health_condition_group_id', $ids);
                            });
                        }
                    } elseif ($filterObj->columnName === 'health_conditions' && $filterObj->value !== '') {
                        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
                            ->get(env('ADMIN_SERVICE_URL') . '/health-conditions/find', [
                                'title' => $filterObj->value
                            ]);

                        if ($response->successful()) {
                            $ids = collect($response->json()['data'])->pluck('id')->toArray();
                            $query->whereHas('treatmentPlans', function (Builder $q) use ($ids) {
                                $q->whereIn('health_condition_id', $ids);
                            });
                        }
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        // For global admin.
        if (isset($data['order_by'])) {
            $query->withTrashed()->orderBy($data['order_by']);
        }

        $patients = $query->paginate($data['page_size']);
        $info = [
            'current_page' => $patients->currentPage(),
            'last_page' => $patients->lastPage(),
            'total_count' => $patients->total(),
        ];

        $patientsCollection = collect($patients->items());

        $phcWorkerIds = $patientsCollection->flatMap(fn($p) => [$p->phc_worker_id, ...((array) $p->supplementary_phc_workers)])
            ->filter()
            ->unique()
            ->values();

        $therapistIds = $patientsCollection->flatMap(fn($p) => [$p->therapist_id, ...((array) $p->secondary_therapists)])
            ->filter()
            ->unique()
            ->values();

        $phcWorkersResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/phc-workers/by-ids', [
                'ids' => json_encode($phcWorkerIds),
                'user_type' => 'phc_service_admin',
            ])
            ->json('data', []);

        $therapistResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-ids', [
                'ids' => json_encode($therapistIds),
                'user_type' => 'clinic_admin',
            ])
            ->json('data', []);

        $phcWorkers = collect($phcWorkersResponse)->keyBy('id');
        $therapists = collect($therapistResponse)->keyBy('id');

        $therapistUnreadAppointmentCountByPatient = Appointment::where('therapist_id', $user->therapist_user_id)
            ->whereIn('patient_status', [
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_REJECTED,
                Appointment::STATUS_ACCEPTED
            ])
            ->where('unread', true)
            ->selectRaw('patient_id, COUNT(*) as total')
            ->groupBy('patient_id')
            ->get()
            ->keyBy('patient_id');

        $mapped = collect($patientsCollection)->map(function ($patient) use ($phcWorkers, $therapists, $therapistUnreadAppointmentCountByPatient) {
            $leadPhcWorker = $phcWorkers[$patient->phc_worker_id] ?? null;
            $leadTherapist = $therapists[$patient->therapist_id] ?? null;
            $leadPhcWorkerData = [];
            $leadTherapistData = [];

            if ($leadPhcWorker) {
                $leadPhcWorkerData[] =  [
                    'id' => $leadPhcWorker['id'],
                    'first_name' => $leadPhcWorker['first_name'],
                    'last_name'  => $leadPhcWorker['last_name'],
                    'type'      => 'lead',
                ];
            }

            if ($leadTherapist) {
                $leadTherapistData[] =  [
                    'id' => $leadTherapist['id'],
                    'first_name' => $leadTherapist['first_name'],
                    'last_name'  => $leadTherapist['last_name'],
                    'type'      => 'lead',
                ];
            }

            $supplementaryPhcWorkerIds = (array) ($patient->supplementary_phc_workers ?? []);
            $supplementaryTherapistIds = (array) ($patient->secondary_therapists ?? []);

            $supplementaryPhcWorkers = collect($supplementaryPhcWorkerIds)
                ->map(fn($id) => $phcWorkers[$id] ?? null)
                ->filter()
                ->map(fn($phcWorker) => [
                    'id' => $phcWorker['id'],
                    'first_name' => $phcWorker['first_name'],
                    'last_name'  => $phcWorker['last_name'],
                    'type'      => 'supplementary',
                ])
                ->toArray();

            $supplementaryTherapists = collect($supplementaryTherapistIds)
                ->map(fn($id) => $therapists[$id] ?? null)
                ->filter()
                ->map(fn($therapist) => [
                    'id' => $therapist['id'],
                    'first_name' => $therapist['first_name'],
                    'last_name'  => $therapist['last_name'],
                    'type'      => 'supplementary',
                ])
                ->toArray();

            $patient->referral_therapists = array_merge($leadTherapistData, $supplementaryTherapists);
            $patient->lead_and_supplementary_phc_workers = array_merge($leadPhcWorkerData, $supplementaryPhcWorkers);
            $patient->lead_and_supplementary_therapists = array_merge($leadTherapistData, $supplementaryTherapists);

            if (isset($leadPhcWorkerData[0])) {
                $patient->referred_by =
                    ($leadPhcWorkerData[0]['first_name'] ?? '') . ' ' .
                    ($leadPhcWorkerData[0]['last_name'] ?? '');
            }

            $patient->unread_appointments_count = $therapistUnreadAppointmentCountByPatient[$patient->id]?->total ?? 0;

            return $patient;
        });

        return ['success' => true, 'data' => PatientListResource::collection($mapped), 'info' => $info];
    }

    public function listForChatroom()
    {
        $user = Auth::user();
        $query = User::where('enabled', true);

        if ($user->user_type === User::GROUP_PHC_WORKER) {
            $query->where(function ($query) use ($user) {
                $query->where('phc_worker_id', $user->therapist_user_id)->orWhereJsonContains('supplementary_phc_workers', $user->therapist_user_id);
            });
        } else {
            $query->where(function ($query) use ($user) {
                $query->where('therapist_id', $user->therapist_user_id)->orWhereJsonContains('secondary_therapists', $user->therapist_user_id);
            });
        }

        return ['success' => true, 'data' => UserChatResource::collection($query->get())];
    }

    /**
     * @OA\Post(
     *     path="/api/patient",
     *     tags={"Patient"},
     *     summary="Create patient",
     *     operationId="createPatient",
     *     @OA\Parameter(
     *         name="therapist_identity",
     *         in="query",
     *         description="Therapist identity",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="Phone",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="dial_code",
     *         in="query",
     *         description="Dial code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="gender",
     *         in="query",
     *         description="Gender",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="clinic_identity",
     *         in="query",
     *         description="Clinic identity",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_of_birth",
     *         in="query",
     *         description="Date of birth",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date(dd/mm/yyyy)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="note",
     *         in="query",
     *         description="Note",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="secondary_therapist[]",
     *         in="query",
     *         description="Secondary therapist ids",
     *         required=false,
     *         @OA\Schema(
     *              type="array",
     *              @OA\Items( type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param Request $request
     * @return array|void
     * @throws \Exception
     */
    public function store(Request  $request)
    {
        DB::beginTransaction();
        $data = $request->all();
        $authUser = Auth::user();
        $dateOfBirth = null;
        if ($data['date_of_birth']) {
            $dateOfBirth = date_create_from_format('d/m/Y', $data['date_of_birth']);
            $dateOfBirth = date_format($dateOfBirth, config('settings.defaultTimestampFormat'));
        }

        $countUserByPhone = User::where('phone', $data['phone'])->count();
        if ($countUserByPhone > 0) {
            return abort(409, 'error_message.phone_exists');
        }

        $user = User::create([
            'therapist_id' => $authUser->user_type === User::GROUP_THERAPIST ? $authUser->therapist_user_id : null,
            'phone' => $data['phone'],
            'dial_code' => $data['dial_code'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'country_id' => $authUser->country_id,
            'gender' => $data['gender'],
            'clinic_id' => $authUser->clinic_id ?: null,
            'date_of_birth' => $dateOfBirth,
            'note' => $data['note'],
            'secondary_therapists' => [],
            'enabled' => true,
            'location' => $data['location'],
            'region_id' => $authUser?->region_id,
            'province_id' => $authUser?->province_id,
            'phc_service_id' => $authUser->phc_service_id ?: null,
            'phc_worker_id' => $authUser->user_type === User::GROUP_PHC_WORKER ? $authUser->therapist_user_id : null,
            'supplementary_phc_workers' => [],
        ]);

        Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->post(env('THERAPIST_SERVICE_URL') . '/therapist/new-patient-notification', [
                'therapist_ids' => $request->get('secondary_therapists') ?: $request->get('supplementary_phc_workers') ?: [],
                'patient_first_name' => $user->first_name,
                'patient_last_name' => $user->last_name,
            ]);

        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE))
            ->get(env('GADMIN_SERVICE_URL') . '/get-organization', ['sub_domain' => env('APP_NAME')]);

        if ($response->successful()) {
            $organization = $response->json();
        } else {
            return ['success' => false, 'message' => 'error_message.organization_not_found'];
        }

        // Add to phone service db.
        Http::post(env('PHONE_SERVICE_URL') . '/phone', [
            'phone' => $data['phone'],
            'org_name' => $organization['name'],
            'patient_api_url' => env('PHONE_SERVICE_PATIENT_API_URL'),
            'admin_api_url' => env('PHONE_SERVICE_ADMIN_API_URL'),
            'therapist_api_url' => env('PHONE_SERVICE_THERAPIST_API_URL'),
            'chat_api_url' => env('PHONE_SERVICE_CHAT_API_URL'),
            'chat_websocket_url' => env('PHONE_SERVICE_CHAT_WEBSOCKET_URL'),
            'clinic_id' => $authUser?->phc_service_id ?: $authUser?->clinic_id,
            'service_type' => $authUser->user_type === User::GROUP_PHC_WORKER ? User::PHC_SERVICE : User::REHAB_SERVICE,
            'sub_domain' => $organization['sub_domain_name'],
        ]);

        // Create unique identity.
        $clinicIdentity = $data['clinic_identity'] ?? '';
        $phcServiceIdentity = $data['phc_service_identity'];
        $orgIdentity = str_pad($organization['id'], 4, '0', STR_PAD_LEFT);
        $identity = 'P' . $orgIdentity . ($clinicIdentity ?: $phcServiceIdentity) .
            str_pad($user->id, 5, '0', STR_PAD_LEFT);

        // Create chat user.
        $updateData = $this->createChatUser($identity, $data['last_name'] . ' ' . $data['first_name']);

        // Create chat room.
        $chatRoomId = RocketChatHelper::createChatRoom($authUser->therapist_user_id, $identity);
        TherapistServiceHelper::AddNewChatRoom(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE), $chatRoomId, $authUser->therapist_user_id);

        // Invite secondary therapist.
        foreach (($request->get('secondary_therapists') ?: $request->get('supplementary_phc_workers') ?: []) as $therapistId) {
            Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                ->post(env('THERAPIST_SERVICE_URL') . '/transfer', [
                    'patient_id' => $user->id,
                    'clinic_id' => $user->clinic_id ?: null,
                    'phc_service_id' => $user->phc_service_id ?: null,
                    'from_therapist_id' => $user->therapist_id ?: $user->phc_worker_id,
                    'to_therapist_id' => $therapistId,
                    'therapist_type' => 'supplementary',
                    'status' => 'invited',
                ]);
        }

        $updateData['identity'] = $identity;
        $updateData['chat_rooms'] = [$chatRoomId];

        $user->fill($updateData);
        $user->save();

        if (!$user) {
            DB::rollBack();
            return abort(500);
        }

        DB::commit();
        return ['success' => true, 'message' => 'success_message.user_add', 'data' => $user];
    }

    /**
     * @OA\Put(
     *     path="/api/patient/{id}",
     *     tags={"Patient"},
     *     summary="Update patient",
     *     operationId="updatePatient",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="Phone",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="dial_code",
     *         in="query",
     *         description="Dial code",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="gender",
     *         in="query",
     *         description="Gender",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_of_birth",
     *         in="query",
     *         description="Date of birth",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date(dd/mm/yyyy)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="note",
     *         in="query",
     *         description="Note",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="secondary_therapist[]",
     *         in="query",
     *         description="Secondary therapist ids",
     *         required=false,
     *         @OA\Schema(
     *              type="array",
     *              @OA\Items( type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $data = $request->all();
            $dataUpdate = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
            ];

            if (isset($data['location'])) {
                $dataUpdate['location'] = $data['location'];
            }

            if (isset($data['phone'])) {
                $phoneExist = User::where('phone', $data['phone'])
                    ->whereNotIn('id', [$id])
                    ->first();

                if ($phoneExist) {
                    // Todo: message will be replaced.
                    return abort(409, 'error_message.phone_exists');
                }
            }

            if (isset($data['dial_code'])) {
                $dataUpdate['dial_code'] = $data['dial_code'];
            }

            if (isset($data['phone'])) {
                $dataUpdate['phone'] = $data['phone'];
            }

            if (isset($data['note'])) {
                $dataUpdate['note'] = $data['note'];
            }

            if (isset($data['date_of_birth'])) {
                $dateOfBirth = date_create_from_format('d/m/Y', $data['date_of_birth']);
                $dataUpdate['date_of_birth'] = date_format($dateOfBirth, config('settings.defaultTimestampFormat'));
            } else {
                $dataUpdate['date_of_birth'] = null;
            }

            if (isset($data['language_id'])) {
                $dataUpdate['language_id'] = $data['language_id'];
            }

            if (isset($data['secondary_therapists'])) {
                $newTherapistIds = array_diff($data['secondary_therapists'], $user->secondary_therapists);

                if ($newTherapistIds) {
                    foreach ($newTherapistIds as $therapistId) {
                        Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                            ->post(env('THERAPIST_SERVICE_URL') . '/transfer', [
                                'patient_id' => $user->id,
                                'clinic_id' => $user->clinic_id,
                                'from_therapist_id' => $user->therapist_id,
                                'to_therapist_id' => $therapistId,
                                'therapist_type' => 'supplementary',
                                'status' => 'invited',
                            ]);
                    }

                    Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                        ->post(env('THERAPIST_SERVICE_URL') . '/therapist/new-patient-notification', [
                            'therapist_ids' => $newTherapistIds,
                            'patient_first_name' => $user->first_name,
                            'patient_last_name' => $user->last_name,
                        ]);
                } else {
                    $dataUpdate['secondary_therapists'] = $data['secondary_therapists'];
                }
            }

            if (isset($data['supplementary_phc_workers'])) {
                $newPhcWorkerIds = array_diff($data['supplementary_phc_workers'], $user->supplementary_phc_workers);

                if ($newPhcWorkerIds) {
                    foreach ($newPhcWorkerIds as $phcWorkerId) {
                        Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                            ->post(env('THERAPIST_SERVICE_URL') . '/transfer', [
                                'patient_id' => $user->id,
                                'phc_service_id' => $user->phc_service_id,
                                'from_therapist_id' => $user->phc_worker_id,
                                'to_therapist_id' => $phcWorkerId,
                                'therapist_type' => 'supplementary',
                                'status' => 'invited',
                            ]);
                    }

                    Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                        ->post(env('THERAPIST_SERVICE_URL') . '/therapist/new-patient-notification', [
                            'therapist_ids' => $newPhcWorkerIds,
                            'patient_first_name' => $user->first_name,
                            'patient_last_name' => $user->last_name,
                        ]);
                } else {
                    $dataUpdate['supplementary_phc_workers'] = $data['supplementary_phc_workers'];
                }
            }

            // Update phone in phone service.
            if (isset($data['phone'])) {
                $response = Http::get(env('PHONE_SERVICE_URL') . '/get-phone-by-org', [
                    'sub_domain' => env('APP_NAME'),
                    'phone' => $user->phone,
                ]);

                if (!empty($response['data']) && $response->successful()) {
                    $phone = $response->json()['data'];
                    Http::put(env('PHONE_SERVICE_URL') . '/phone/' . $phone['id'], [
                        'phone' => $data['phone'],
                    ]);
                }
            }

            $dataUpdate['chat_rooms'] = $user->chat_rooms;
            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }

    /**
     * @param string $username
     * @param string $name
     * @return array
     * @throws \Exception
     */
    private function createChatUser($username, $name)
    {
        $password = bin2hex(random_bytes(16));
        $chatUser = [
            'name' => $name,
            'email' => $username . '@hi.org',
            'username' => $username,
            'password' => $password,
            'joinDefaultChannels' => false,
            'verified' => true,
            'active' => false
        ];
        $chatUserId = RocketChatHelper::createUser($chatUser);
        if (is_null($chatUserId)) {
            throw new \Exception('error_message.create_chat_user');
        }
        return [
            'chat_user_id' => $chatUserId,
            'chat_password' => CryptHelper::encrypt($password)
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/patient/list/by-therapist-ids",
     *     tags={"Patient"},
     *     summary="Patient list by therapist ids",
     *     operationId="patientListByTherapistIds",
     *     @OA\Parameter(
     *         name="therapist_ids[]",
     *         in="query",
     *         description="Therapist ids",
     *         required=true,
     *         @OA\Schema(
     *              type="array",
     *              @OA\Items( type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByTherapistIds(Request $request)
    {
        $therapistIds = $request->get('therapist_ids', []);
        $patients = User::whereIn('therapist_id', $therapistIds)->get();
        return PatientListResource::collection($patients);
    }

    /**
     * @OA\Get(
     *     path="/api/patient/list/by-phc-worker-ids",
     *     tags={"Patient"},
     *     summary="Patient list by PHC worker ids",
     *     operationId="patientListByPhcWorkerIds",
     *     @OA\Parameter(
     *         name="phc_worker_ids[]",
     *         in="query",
     *         description="PHC worker ids",
     *         required=true,
     *         @OA\Schema(
     *              type="array",
     *              @OA\Items(type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByPhcWorkerIds(Request $request)
    {
        $phcWorkerIds = $request->get('phc_worker_ids', []);

        $patients = User::whereIn('phc_worker_id', $phcWorkerIds)->get();

        return response()->json(['data' => PatientListResource::collection($patients)]);
    }


    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTherapistIdsByPhcWorkerId(int $id)
    {
        $therapistIds = User::where(function ($q) use ($id) {
            $q->where('phc_worker_id', $id)
                ->orWhereJsonContains('supplementary_phc_workers', $id);
        })
            ->whereNotNull('therapist_id')
            ->select('therapist_id', 'secondary_therapists')
            ->get()
            ->flatMap(fn($user) => [$user->therapist_id, ...Arr::wrap($user->secondary_therapists)])
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $therapistIds,
        ]);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPhcWorkerIdsByTherapistId(int $id)
    {
        $phcWorkerIds = User::where(function ($q) use ($id) {
            $q->where('therapist_id', $id)
                ->orWhereJsonContains('secondary_therapists', $id);
        })
            ->whereNotNull('phc_worker_id')
            ->select('phc_worker_id', 'supplementary_phc_workers')
            ->get()
            ->flatMap(fn($user) => [$user->phc_worker_id, ...Arr::wrap($user->supplementary_phc_workers)])
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $phcWorkerIds,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/patient/list/by-therapist-id",
     *     tags={"Patient"},
     *     summary="Patient list by therapist id",
     *     operationId="patientListByTherapistId",
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
     *         required=true,
     *         @OA\Schema(
     *              type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByTherapistId(Request $request)
    {
        $data = $request->all();
        $query = User::where('therapist_id', $data['therapist_id']);
        if (isset($data['enabled'])) {
            $query->where('enabled', $data['enabled']);
        }
        $patients = $query->get();
        return ['success' => true, 'data' => PatientList2Resource::collection($patients)];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getPatientForTherapistRemove(Request $request)
    {
        $therapistId = $request->get('therapist_id');

        $query = User::query();

        if ($therapistId) {
            $query->where('therapist_id', $therapistId)->orWhereJsonContains('secondary_therapists', $therapistId);
        }

        $patients = $query->get();

        return ['success' => true, 'data' => PatientForTherapistRemoveResource::collection($patients)];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getPatientOfRemovePhcWorker(Request $request)
    {
        $phcWorkerId = $request->get('phc_worker_id');

        $patients = User::where('phc_worker_id', $phcWorkerId)->orWhereJsonContains('supplementary_phc_workers', $phcWorkerId)->get();

        return ['success' => true, 'data' => PatientOfRemovePhcWorkerResource::collection($patients)];
    }

    /**
     * @OA\Post(
     *     path="/api/patient/activateDeactivateAccount/{user}",
     *     tags={"Patient"},
     *     summary="Activate deactivate patient account",
     *     operationId="patientActivateDeactivateAccount",
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User id",
     *         required=true,
     *         @OA\Schema(
     *              type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="enabled",
     *         in="query",
     *         description="Enabled",
     *         required=true,
     *         @OA\Schema(
     *              type="boolean"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param Request $request
     * @param \App\Models\User $user
     * @return array
     */
    public function activateDeactivateAccount(Request $request, User $user)
    {
        $enabled = $request->boolean('enabled');
        $user->update(['enabled' => $enabled]);

        return ['success' => true, 'message' => 'success_message.activate_deactivate_account', 'enabled' => $enabled];
    }

    /**
     * @OA\Post(
     *     path="/api/patient/deleteAccount/{user}",
     *     tags={"Patient"},
     *     summary="Delete user account",
     *     operationId="deleteUserAccount",
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User id",
     *         required=true,
     *         @OA\Schema(
     *              type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param $id
     *
     * @return array
     * @throws \Exception
     */
    public function deleteAccount(Request $request, $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $hardDelete = $request->boolean('hard_delete');

        // Remove all active requests of patient transfer
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->delete(env('THERAPIST_SERVICE_URL') . '/transfer/delete/by-patient', [
                'patient_id' => $user->id,
            ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'fail_message.patient_transfer_delete',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        // Delete phone in phone service.
        $response = Http::get(env('PHONE_SERVICE_URL') . '/get-phone-by-org', [
            'sub_domain' => env('APP_NAME'),
            'phone' => $user->phone,
        ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'fail_message.patient_phone_delete',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        if (!empty($response['data']) && $response->successful()) {
            $phone = $response->json()['data'];
            Http::delete(env('PHONE_SERVICE_URL') . '/phone/' . $phone['id']);
        }

        if ($hardDelete) {
            $user->forceDelete();
        } else {
            $this->obfuscatedUserData($user);
            $user->referrals()->delete();
            $user->delete();
        }

        return ['success' => true, 'message' => 'success_message.deleted_account'];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteByClinicId(Request $request)
    {
        $clinicId = $request->get('clinic_id');
        $users = User::where('clinic_id', $clinicId)->get();
        if (count($users) > 0) {
            foreach ($users as $user) {
                $this->obfuscatedUserData($user);
                $user->delete();
            }
        }

        // Remove phone numbers from phone service.
        Http::post(env('PHONE_SERVICE_URL') . '/phone/delete/by-clinic', [
            'clinic_id' => $clinicId,
        ]);

        return ['success' => true, 'message' => 'success_message.deleted_account'];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteByTherapistId(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $hardDelete = $request->boolean('hard_delete');

        $users = User::where('therapist_id', $therapistId)->get();

        if (count($users) > 0) {
            foreach ($users as $user) {
                // Delete phone in phone service.
                $response = Http::get(env('PHONE_SERVICE_URL') . '/get-phone-by-org', [
                    'sub_domain' => env('APP_NAME'),
                    'phone' => $user->phone,
                ]);

                if (!empty($response['data']) && $response->successful()) {
                    $phone = $response->json()['data'];
                    Http::delete(env('PHONE_SERVICE_URL') . '/phone/' . $phone['id']);
                }

                $this->obfuscatedUserData($user);

                if ($hardDelete) {
                    $user->forceDelete();
                } else {
                    $user->delete();
                }
            }
        }

        return ['success' => true, 'message' => 'success_message.deleted_account'];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteByPhcWorkerId(Request $request)
    {
        $phcWorkerId = $request->get('phc_worker_id');
        $hardDelete = $request->boolean('hard_delete');

        $users = User::where('phc_worker_id', $phcWorkerId)->get();

        if (count($users) > 0) {
            foreach ($users as $user) {
                // Delete phone in phone service.
                $response = Http::get(env('PHONE_SERVICE_URL') . '/get-phone-by-org', [
                    'sub_domain' => env('APP_NAME'),
                    'phone' => $user->phone,
                ]);

                if (!empty($response['data']) && $response->successful()) {
                    $phone = $response->json()['data'];
                    Http::delete(env('PHONE_SERVICE_URL') . '/phone/' . $phone['id']);
                }

                $this->obfuscatedUserData($user);

                if ($hardDelete) {
                    $user->forceDelete();
                } else {
                    $user->delete();
                }
            }
        }

        return ['success' => true, 'message' => 'success_message.deleted_account'];
    }

    /**
     * @param Request $request
     * @param User $user
     * @return array
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function transferToTherapist(Request $request, User $user)
    {
        $therapistId = $request->get('therapist_id');
        $oldTherapistChatRooms = $request->get('chat_rooms');
        $newTherapistChatRooms = $request->get('new_chat_rooms');
        $authUserType = $request->get('auth_user_type');
        $chatRooms = $user->chat_rooms;
        $isGlobalAdmin = Auth::user()->user_type === User::GROUP_ORGANIZATION_ADMIN || Auth::user()->user_type === User::ADMIN_GROUP_COUNTRY_ADMIN || Auth::user()->user_type === User::GROUP_REGIONAL_ADMIN || Auth::user()->user_type === User::GROUP_PHC_SERVICE_ADMIN;

        if ($request->get('therapist_type') === 'supplementary') {
            if ($user->phc_worker_id && $authUserType === User::GROUP_PHC_WORKER) {
                $updateData['supplementary_phc_workers'] = array_unique(array_merge($user->supplementary_phc_workers, [$therapistId]));
            } else {
                $updateData['secondary_therapists'] = array_unique(array_merge($user->secondary_therapists, [$therapistId]));
            }

            $chatRoomId = RocketChatHelper::createChatRoom($therapistId, $user->identity);
            TherapistServiceHelper::AddNewChatRoom(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE), $chatRoomId, $therapistId);

            $updateData['chat_rooms'] = array_values(array_unique(array_merge($chatRooms, [$chatRoomId])));
        } else {
            // Remove chat room of previous therapist.
            $intersectChatRooms = array_intersect($oldTherapistChatRooms, $chatRooms);
            if (($key = array_search(reset($intersectChatRooms), $chatRooms)) !== false) {
                unset($chatRooms[$key]);
            }

            // Remove secondary therapists if transfered therapist.
            $secondaryTherapists = $user->phc_worker_id && ($authUserType === User::GROUP_PHC_WORKER || $isGlobalAdmin) ? $user->supplementary_phc_workers : $user->secondary_therapists;
            if (($key = array_search($therapistId, $secondaryTherapists)) !== false) {
                unset($secondaryTherapists[$key]);
            }

            // Update own activities.
            $ongoingTreatmentPlan = $user->treatmentPlans()
                ->whereDate('start_date', '<=', Carbon::now())
                ->whereDate('end_date', '>=', Carbon::now())
                ->get();
            $plannedTreatmentPlans = $user->treatmentPlans()
                ->whereDate('end_date', '>', Carbon::now())
                ->orderBy('start_date')
                ->get();

            if (count($plannedTreatmentPlans) > 0) {
                foreach ($plannedTreatmentPlans as $treatmentPlan) {
                    Activity::where('treatment_plan_id', $treatmentPlan['id'])->update(['created_by' => $therapistId]);

                    TreatmentPlan::where('id', $treatmentPlan['id'])->update(['created_by' => $therapistId]);
                }
            }

            if (count($ongoingTreatmentPlan) > 0) {
                Activity::where('treatment_plan_id', $ongoingTreatmentPlan[0]->id)->update(['created_by' => $therapistId]);

                TreatmentPlan::where('id', $ongoingTreatmentPlan[0]->id)->update(['created_by' => $therapistId]);
            }

            // Check if chat rooms of new therapist exist.
            $chatRoomOfNewTherapists = array_intersect($newTherapistChatRooms, $chatRooms);
            if (!$chatRoomOfNewTherapists) {
                // Create chat room for new therapist and patient.
                $chatRoomId = RocketChatHelper::createChatRoom($therapistId, $user->identity);
                TherapistServiceHelper::AddNewChatRoom(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE), $chatRoomId, $therapistId);
                $newChatRooms = array_merge($chatRooms, [$chatRoomId]);
            } else {
                $newChatRooms = $chatRooms;
            }

            // Update user chatrooms.
            $updateData['chat_rooms'] = array_values(array_unique($newChatRooms));
            if ($user->phc_worker_id && ($authUserType === User::GROUP_PHC_WORKER || $isGlobalAdmin)) {
                $updateData['phc_worker_id'] = $therapistId;
                $updateData['supplementary_phc_workers'] = $secondaryTherapists;
            } else {
                $updateData['therapist_id'] = $therapistId;
                $updateData['secondary_therapists'] = $secondaryTherapists;
            }
        }

        $user->update($updateData);
        $user->save();

        // Remove all active requests of patient transfer
        Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->delete(env('THERAPIST_SERVICE_URL') . '/transfer/delete/by-patient', [
                'patient_id' => $user->id,
            ]);

        return ['success' => true, 'message' => 'success_message.transfered_account'];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function delete()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->obfuscatedUserData($user);
        $user->delete();

        return ['success' => true, 'message' => 'success_message.deleted_account'];
    }

    /**
     * @param \App\Models\User $user
     *
     * @return void
     */
    private function obfuscatedUserData(User $user)
    {
        $user->update([
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
        ]);
    }

    public function transfer(Request $request)
    {
        $this->validate($request, [
            'patient_id' => 'required|exists:users,id',
            'therapist_id' => 'required',
            'therapist_type' => 'required|in:lead,supplementary',
        ]);

        $user = User::find($request->get('patient_id'));

        if ($request->get('therapist_type') === 'lead') {
            $user->update(['therapist_id' => $request->get('therapist_id')]);
        }

        return ['success' => true, 'message' => 'success_message.transfer_accept'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \Mpdf\MpdfException
     */
    public function export(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $timezone = $request->get('timezone');

        $zip = new \ZipArchive();
        $fileName = storage_path('app/' . $user->id . '_patient_data.zip');

        if ($zip->open($fileName, \ZipArchive::CREATE) === TRUE) {
            $patientProfileExport = new PatientProfileExport($user);
            $zip->addFromString('profile.pdf', $patientProfileExport->Output('profile.pdf', Destination::STRING_RETURN));

            foreach ($user->treatmentPlans()->get() as $treatmentPlan) {
                $treatmentPlanExport = new TreatmentPlanExport($treatmentPlan, $request);
                $zip->addFromString(
                    $treatmentPlan->name . '_' . $treatmentPlan->start_date->format('Y-m-d') . '.pdf',
                    $treatmentPlanExport->Output('treatment_plan.pdf', Destination::STRING_RETURN)
                );
            }

            foreach ($user->chat_rooms as $room_id) {
                $messages = RocketChatHelper::getMessages($user, $room_id);
                $room_usernames = RocketChatHelper::getRoom($user, $room_id);
                $patient = RocketChatHelper::getUser($room_usernames['patient_username']);
                $therapist = RocketChatHelper::getUser($room_usernames['therapist_username']);

                foreach ($messages as $message) {
                    if (isset($message['file'])) {
                        $download_file = file_get_contents(env('ROCKET_CHAT_URL') . '/file-upload/' . $message['file']['_id'] . '/' . $message['file']['_id'] . '?rc_uid=' . env('ROCKET_CHAT_ADMIN_USER_ID') . '&rc_token=' . env('ROCKET_CHAT_ADMIN_AUTH_TOKEN'));
                        $zip->addFromString($message['file']['name'], $download_file);
                    }
                }

                $chatExport = new ChatExport($messages, $patient, $therapist, $timezone);
                $file = 'chat-' . strtolower(str_replace(' ', '-', $therapist['name'])) . '.pdf';
                $zip->addFromString($file, $chatExport->Output($file, Destination::STRING_RETURN));
            }

            $zip->close();
        }

        return response()->download($fileName, $user->last_name . $user->first_name . '.zip')->deleteFileAfterSend();
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteChatRoomById(Request $request)
    {
        $chatRoomId = $request->get('chat_room_id');
        $patientId = $request->get('patient_id');

        $patient = User::where('id', $patientId)->first();
        $chatRooms = $patient['chat_rooms'];
        if (($key = array_search($chatRoomId, $chatRooms)) !== false) {
            unset($chatRooms[$key]);
        }

        $updateData['chat_rooms'] = $chatRooms;
        $patient->fill($updateData);
        $patient->save();

        return ['success' => true, 'message' => 'success_message.deleted_chat_rooms'];
    }

    /**
     * @param Request $request
     * @return int
     */
    public function getPatientByPhone(Request $request)
    {
        $phone = $request->get('phone');
        $patientId = $request->get('patientId');
        if ($patientId) {
            $patient = User::where('phone', $phone)->whereNotIn('id', [$patientId])
                ->count();
        } else {
            $patient = User::where('phone', $phone)
                ->count();
        }


        return $patient ? $patient : 0;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getPatientAchievements(Request $request)
    {
        $user = Auth::user();

        $achievements = array(
            [
                'title' => 'achievement.tasks.bronze.title',
                'subtitle' => 'achievement.tasks.bronze.subtitle',
                'icon' => '/achievement/get-badge-icon/daily-task-bronze-badge.png',
                'obtained' => $user->daily_tasks >= User::BRONZE_DAILY_TASKS,
                'max_streak_number' => User::BRONZE_DAILY_TASKS,
                'init_streak_number' => $user->init_daily_tasks,
            ],
            [
                'title' => 'achievement.tasks.silver.title',
                'subtitle' => 'achievement.tasks.silver.subtitle',
                'icon' => '/achievement/get-badge-icon/daily-task-silver-badge.png',
                'obtained' => $user->daily_tasks >= User::SILVER_DAILY_TASKS,
                'max_streak_number' => User::SILVER_DAILY_TASKS,
                'init_streak_number' => $user->init_daily_tasks,
            ],
            [
                'title' => 'achievement.tasks.gold.title',
                'subtitle' => 'achievement.tasks.gold.subtitle',
                'icon' => '/achievement/get-badge-icon/daily-task-gold-badge.png',
                'obtained' => $user->daily_tasks >= User::GOLD_DAILY_TASKS,
                'max_streak_number' => User::GOLD_DAILY_TASKS,
                'init_streak_number' => $user->init_daily_tasks,
            ],
            [
                'title' => 'achievement.tasks.diamond.title',
                'subtitle' => 'achievement.tasks.diamond.subtitle',
                'icon' => '/achievement/get-badge-icon/daily-task-diamond-badge.png',
                'obtained' => $user->daily_tasks >= User::DIAMOND_DAILY_TASKS,
                'max_streak_number' => User::DIAMOND_DAILY_TASKS,
                'init_streak_number' => $user->init_daily_tasks,
            ],
            [
                'title' => 'achievement.logins.bronze.title',
                'subtitle' => 'achievement.logins.bronze.subtitle',
                'icon' => '/achievement/get-badge-icon/login-bronze-badge.png',
                'obtained' => $user->daily_logins >= User::BRONZE_DAILY_LOGINS,
                'max_streak_number' => User::BRONZE_DAILY_LOGINS,
                'init_streak_number' => $user->init_daily_logins,
            ],
            [
                'title' => 'achievement.logins.silver.title',
                'subtitle' => 'achievement.logins.silver.subtitle',
                'icon' => '/achievement/get-badge-icon/login-silver-badge.png',
                'obtained' => $user->daily_logins >= User::SILVER_DAILY_LOGINS,
                'max_streak_number' => User::SILVER_DAILY_LOGINS,
                'init_streak_number' => $user->init_daily_logins,
            ],
            [
                'title' => 'achievement.logins.gold.title',
                'subtitle' => 'achievement.logins.gold.subtitle',
                'icon' => '/achievement/get-badge-icon/login-gold-badge.png',
                'obtained' => $user->daily_logins >= User::GOLD_DAILY_LOGINS,
                'max_streak_number' => User::GOLD_DAILY_LOGINS,
                'init_streak_number' => $user->init_daily_logins,
            ],
            [
                'title' => 'achievement.logins.diamond.title',
                'subtitle' => 'achievement.logins.diamond.subtitle',
                'icon' => '/achievement/get-badge-icon/login-diamond-badge.png',
                'obtained' => $user->daily_logins >= User::DIAMOND_DAILY_LOGINS,
                'max_streak_number' => User::DIAMOND_DAILY_LOGINS,
                'init_streak_number' => $user->init_daily_logins,
            ],
            [
                'title' => 'achievement.answers.bronze.title',
                'subtitle' => 'achievement.answers.bronze.subtitle',
                'icon' => '/achievement/get-badge-icon/answer-bronze-badge.png',
                'obtained' => $user->daily_answers >= User::BRONZE_DAILY_ANSWERS,
                'max_streak_number' => User::BRONZE_DAILY_ANSWERS,
                'init_streak_number' => $user->init_daily_answers,
            ],
            [
                'title' => 'achievement.answers.silver.title',
                'subtitle' => 'achievement.answers.silver.subtitle',
                'icon' => '/achievement/get-badge-icon/answer-silver-badge.png',
                'obtained' => $user->daily_answers >= User::SILVER_DAILY_ANSWERS,
                'max_streak_number' => User::SILVER_DAILY_ANSWERS,
                'init_streak_number' => $user->init_daily_answers,
            ],
            [
                'title' => 'achievement.answers.gold.title',
                'subtitle' => 'achievement.answers.gold.subtitle',
                'icon' => '/achievement/get-badge-icon/answer-gold-badge.png',
                'obtained' => $user->daily_answers >= User::GOLD_DAILY_ANSWERS,
                'max_streak_number' => User::GOLD_DAILY_ANSWERS,
                'init_streak_number' => $user->init_daily_answers,
            ],
            [
                'title' => 'achievement.answers.diamond.title',
                'subtitle' => 'achievement.answers.diamond.subtitle',
                'icon' => '/achievement/get-badge-icon/answer-diamond-badge.png',
                'obtained' => $user->daily_answers >= User::DIAMOND_DAILY_ANSWERS,
                'max_streak_number' => User::DIAMOND_DAILY_ANSWERS,
                'init_streak_number' => $user->init_daily_answers,
            ],
        );

        return ['success' => true, 'data' => $achievements];
    }

    /**
     * @param string $filename
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function getBadgeIcon($filename)
    {
        return response()->file(public_path('badges/' . $filename));
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function getCallAccessToken(Request $request)
    {
        $twilioAccountSid = env('TWILIO_ACCOUNT_SID');
        $twilioApiKey = env('TWILIO_API_KEY');
        $twilioApiSecret = env('TWILIO_API_KEY_SECRET');

        $user = Auth::user();

        $identity = $user['identity'];
        $countryId = $user['country_id'];
        $name = $user['last_name'] . ' ' . $user['first_name'];

        // Create access token, which we will serialize and send to the client.
        $token = new AccessToken(
            $twilioAccountSid,
            $twilioApiKey,
            $twilioApiSecret,
            3600,
            "$identity###$countryId###$name",
        );

        // Create Video grant.
        $videoGrant = new VideoGrant();
        $videoGrant->setRoom($request->room_id);

        // Add grant to token.
        $token->addGrant($videoGrant);

        return ['success' => true, 'token' => $token->toJWT()];
    }

    /**
     * @param Request $request
     * @return \Illuminate\Support\Collection
     */
    public function getPatientsForGlobalData(Request $request)
    {
        $yesterday = Carbon::yesterday();
        if ($request->has('all')) {
            $users = User::where('email', '!=', env('KEYCLOAK_BACKEND_USERNAME'))
                ->orWhereNull('email')
                ->withTrashed()
                ->get();
        } else {
            $users = User::where('email', '!=', env('KEYCLOAK_BACKEND_USERNAME'))
                ->orWhereNull('email')
                ->whereDate('updated_at', '>=', $yesterday->startOfDay())
                ->whereDate('updated_at', '<=', $yesterday->endOfDay())
                ->withTrashed()
                ->get();
        }

        return $users;
    }

    /**
     * @param integer $id
     * @return PatientResource
     */
    public function getById(int $id)
    {
        return new PatientResource(
            User::with('lastReferral')->findOrFail($id)
        );
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getByIds(Request $request)
    {
        $patient_ids = $request->get('patient_ids', []);

        return [
            'success' => true,
            'data' => PatientListResource::collection(User::whereIn('id', $patient_ids)->get()),
        ];
    }

    /**
     * @return array
     */
    public function getPatientDataForPhoneService()
    {
        return [
            'data' => User::all(),
            'domain' => env('APP_DOMAIN')
        ];
    }

    public function getPatientRawData(Request $request)
    {
        $data = $request->all();
        $query = User::withTrashed()->whereNotNull('identity');

        if (isset($data['country'])) {
            $query->where('country_id', $data['country']);
        }

        if (isset($data['clinic'])) {
            $query->where('clinic_id', $data['clinic']);
        }

        if (isset($data['search_value'])) {
            $query->where(function ($query) use ($data) {
                $query->where('identity', 'like', '%' . $data['search_value'] . '%');
            });
        }

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'date_of_birth') {
                        $dateOfBirth = date_create_from_format('d/m/Y', $filterObj->value);
                        $query->where('date_of_birth', date_format($dateOfBirth, config('settings.defaultTimestampFormat')));
                    } elseif (($filterObj->columnName === 'region' || $filterObj->columnName === 'clinic') && $filterObj->value !== '') {
                        $query->where('clinic_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'country' && $filterObj->value !== '') {
                        $query->where('country_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'treatment_status') {
                        if ($filterObj->value == User::FINISHED_TREATMENT_PLAN) {
                            $query->whereHas('treatmentPlans', function (Builder $query) {
                                $query->whereDate('end_date', '<', Carbon::now());
                            })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                $query->whereDate('end_date', '>', Carbon::now());
                            })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                $query->whereDate('start_date', '<=', Carbon::now())
                                    ->whereDate('end_date', '>=', Carbon::now());
                            });
                        } elseif ($filterObj->value == User::PLANNED_TREATMENT_PLAN) {
                            $query->whereHas('treatmentPlans', function (Builder $query) {
                                $query->whereDate('end_date', '>', Carbon::now());
                            })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                $query->whereDate('start_date', '<=', Carbon::now())
                                    ->whereDate('end_date', '>=', Carbon::now());
                            });
                        } else {
                            $query->whereHas('treatmentPlans', function (Builder $query) {
                                $query->whereDate('start_date', '<=', Carbon::now())
                                    ->whereDate('end_date', '>=', Carbon::now());
                            });
                        }
                    } elseif ($filterObj->columnName === 'gender') {
                        $query->where($filterObj->columnName, $filterObj->value);
                    } elseif ($filterObj->columnName === 'age') {
                        $query->whereRaw('YEAR(NOW()) - YEAR(date_of_birth) = ? OR ABS(MONTH(date_of_birth) - MONTH(NOW())) = ?  OR ABS(DAY(date_of_birth) - DAY(NOW())) = ?', [$filterObj->value, $filterObj->value, $filterObj->value]);
                    } elseif ($filterObj->columnName === 'ongoing_treatment_plan') {
                        $query->whereHas('treatmentPlans', function (Builder $query) use ($filterObj) {
                            $query->where('name', 'like', '%' .  $filterObj->value . '%');
                        });
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        $patients = $query->with([
            'treatmentPlans',
            'assistiveTechnologies',
        ])->withCount('callHistories')->get();

        return [
            'success' => true,
            'data' => PatientRawDataResource::collection($patients),
        ];
    }

    /**
     * Get PHC worker patients for mobile app.
     *
     * @return array
     * @throws \Exception
     */
    public function getPhcWorkerPatients()
    {
        $user = Auth::user();

        $query = User::with('lastReferral')->where(function ($query) use ($user) {
            $query->where('phc_worker_id', $user->therapist_user_id)->orWhereJsonContains('supplementary_phc_workers', intval($user->therapist_user_id));
        });

        $patients = $query->get();

        // Get therapists data from therapist service.
        $therapistResponse = Http::withToken(
            Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE)
        )
            ->get(env('THERAPIST_SERVICE_URL') . '/therapists-by-country', ['country_id' => $user->country_id])
            ->json('data', []);

        $therapists = collect($therapistResponse)->keyBy('id');
        $patients->transform(function ($patient) use ($therapists) {
            $leadTherapistData = [];

            $leadTherapistId = $patient->therapist_id;
            $leadTherapist = $therapists[$leadTherapistId] ?? null;

            if ($leadTherapist) {
                $leadTherapistData[] =  [
                    'first_name' => $leadTherapist['first_name'],
                    'last_name'  => $leadTherapist['last_name'],
                    'type'      => 'lead',
                ];
            }

            $supplementaryTherapistIds = (array) ($patient->secondary_therapists ?? []);

            $supplementaryTherapists = collect($supplementaryTherapistIds)
                ->map(fn($id) => $therapists[$id] ?? null)
                ->filter()
                ->map(fn($therapist) => [
                    'first_name' => $therapist['first_name'],
                    'last_name'  => $therapist['last_name'],
                    'type'      => 'supplementary',
                ])
                ->toArray();

            $patient->referral_therapists = array_merge($leadTherapistData, $supplementaryTherapists);
            return $patient;
        });

        // Get screening questionnaires data from admin service.
        $screeningQuestionnaireResponse = Http::withToken(
            Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE)
        )
            ->get(env('ADMIN_SERVICE_URL') . '/screening-questionnaires/all')
            ->json('data', []);

        $screeningQuestionnairesByUser = collect($screeningQuestionnaireResponse)
            ->flatMap(function ($questionnaire) {
                return collect($questionnaire['answers'] ?? [])
                    ->map(fn($answer) => [
                        'user_id' => $answer['user_id'],
                        'questionnaire' => $questionnaire,
                    ]);
            })
            ->groupBy('user_id')
            ->map(
                fn($items) =>
                $items->pluck('questionnaire')->unique('id')->values()
            );


        $therapistUnreadAppointmentCountByPatient = Appointment::where('therapist_id', $user->therapist_user_id)
            ->whereIn('patient_status', [
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_REJECTED,
                Appointment::STATUS_ACCEPTED
            ])
            ->where('unread', true)
            ->selectRaw('patient_id, COUNT(*) as total')
            ->groupBy('patient_id')
            ->get()
            ->keyBy('patient_id');

        $patients->transform(function ($patient) use ($screeningQuestionnairesByUser, $therapistUnreadAppointmentCountByPatient) {
            $patient->interviewed_questionnaires = $screeningQuestionnairesByUser
                ->get($patient->id, collect())
                ->pluck('id')
                ->values();

            $patient->unread_appointments_count = $therapistUnreadAppointmentCountByPatient[$patient->id]?->total ?? 0;

            return $patient;
        });

        return ['success' => true, 'data' => PatientListMobileResource::collection($patients)];
    }
}

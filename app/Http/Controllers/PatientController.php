<?php

namespace App\Http\Controllers;

use App\Exports\ChatExport;
use App\Exports\PatientProfileExport;
use App\Exports\TreatmentPlanExport;
use App\Helpers\RocketChatHelper;
use App\Helpers\TherapistServiceHelper;
use App\Http\Resources\PatientForTherapistRemoveResource;
use App\Http\Resources\PatientResource;
use App\Models\Activity;
use App\Models\TreatmentPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mpdf\Output\Destination;

class PatientController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $info = [];

        if (isset($data['id'])) {
            $users = User::where('id', $data['id'])->get();
        } else {
            $query = User::query();

            if (isset($data['therapist_id'])) {
                $query->where(function ($query) use ($data) {
                    $query->where('therapist_id', $data['therapist_id'])->orWhereJsonContains('secondary_therapists', intval($data['therapist_id']));
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
                            ->orWhereHas('appointments', function($query) use ($data) {
                                $query->where('start_date', '>', Carbon::now())
                                    ->limit(1);
                            })->whereHas('appointments', function(Builder $query) use ($data) {
                                $query->whereDate('start_date', 'like', '%' . $data['search_value'] . '%');
                            });
                        });
                }
            }

            if (isset($data['filters'])) {
                $filters = $request->get('filters');
                $therapist_id = $data['therapist_id'] ?? '';
                $query->where(function ($query) use ($filters, $therapist_id) {
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
                                $query->whereHas('treatmentPlans', function(Builder $query) {
                                    $query->whereDate('end_date', '<', Carbon::now());
                                })->whereDoesntHave('treatmentPlans', function(Builder $query) {
                                    $query->whereDate('end_date', '>', Carbon::now());
                                })->whereDoesntHave('treatmentPlans', function(Builder $query) {
                                    $query->whereDate('start_date', '<=', Carbon::now())
                                        ->whereDate('end_date', '>=', Carbon::now());
                                });
                            } elseif ($filterObj->value == User::PLANNED_TREATMENT_PLAN) {
                                $query->whereHas('treatmentPlans', function(Builder $query) {
                                    $query->whereDate('end_date', '>', Carbon::now());
                                })->whereDoesntHave('treatmentPlans', function(Builder $query) {
                                    $query->whereDate('start_date', '<=', Carbon::now())
                                        ->whereDate('end_date', '>=', Carbon::now());
                                });
                            } else {
                                $query->whereHas('treatmentPlans', function(Builder $query) {
                                    $query->whereDate('start_date', '<=', Carbon::now())
                                        ->whereDate('end_date', '>=', Carbon::now());
                                });
                            }
                        } else if ($filterObj->columnName === 'age') {
                            $query->whereRaw('YEAR(NOW()) - YEAR(date_of_birth) = ? OR ABS(MONTH(date_of_birth) - MONTH(NOW())) = ?  OR ABS(DAY(date_of_birth) - DAY(NOW())) = ?', [$filterObj->value, $filterObj->value, $filterObj->value]);
                        } else if ($filterObj->columnName === 'ongoing_treatment_plan') {
                            $query->whereHas('treatmentPlans', function(Builder $query) use ($filterObj) {
                                $query->where('name', 'like', '%' .  $filterObj->value . '%');
                            });
                        } else if ($filterObj->columnName === 'secondary_therapist') {
                            if ($filterObj->value == User::SECONDARY_TERAPIST){
                                $query->where(function ($query) use ($therapist_id) {
                                    $query->whereJsonContains('secondary_therapists', intval($therapist_id));
                                });
                            } else {
                                $query->where(function ($query) use ($therapist_id) {
                                    $query->where('secondary_therapists',  'like', '%[]%');
                                });
                            }
                        } elseif ($filterObj->columnName === 'next_appointment' && $filterObj->value !== '') {
                            $nexAppointment = date_create_from_format('d/m/Y', $filterObj->value);
                            $query->whereHas('appointments', function(Builder $query) use ($filterObj) {
                                $query->where('start_date', '>', Carbon::now())
                                    ->limit(1);
                            })->whereHas('appointments', function(Builder $query) use ($nexAppointment) {
                                $query->whereDate('start_date', '=', date_format($nexAppointment, config('settings.defaultTimestampFormat')));
                            });
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

            $users = $query->paginate($data['page_size']);
            $info = [
                'current_page' => $users->currentPage(),
                'total_count' => $users->total(),
            ];
        }
        return ['success' => true, 'data' => PatientResource::collection($users), 'info' => $info];
    }

    /**
     * @param Request $request
     * @return array|void
     * @throws \Exception
     */
    public function store(Request  $request)
    {
        DB::beginTransaction();
        $data = $request->all();

        $dateOfBirth = null;
        if ($data['date_of_birth']) {
            $dateOfBirth = date_create_from_format('d/m/Y', $data['date_of_birth']);
            $dateOfBirth = date_format($dateOfBirth, config('settings.defaultTimestampFormat'));
        }

        $secondaryTherapists = isset($data['secondary_therapists']) ? $data['secondary_therapists'] : [];

        $phoneExist = Http::get(env('THERAPIST_SERVICE_URL') . '/api/patient/by-phone-number', [
            'phone' => $data['phone']
        ]);

        if (!empty($phoneExist) && $phoneExist->successful()) {
            $patientData = $phoneExist->json()['data'];
        }

        if ($patientData > 0) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.phone_exists');
        }

        $user = User::create([
            'therapist_id' => $data['therapist_id'],
            'phone' => $data['phone'],
            'dial_code' => $data['dial_code'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'country_id' => $data['country_id'],
            'gender' => $data['gender'],
            'clinic_id' => $data['clinic_id'],
            'date_of_birth' => $dateOfBirth,
            'note' => $data['note'],
            'secondary_therapists' => $secondaryTherapists
        ]);

        Http::post(env('THERAPIST_SERVICE_URL') . '/api/therapist/new-patient-notification', [
            'therapist_ids' => isset($data['secondary_therapists']) ? $data['secondary_therapists'] : [],
            'patient_first_name' => $user->first_name,
            'patient_last_name' => $user->last_name,
        ]);

        // Create unique identity.
        $clinicIdentity = $data['clinic_identity'];
        $identity = 'P' . $clinicIdentity .
            str_pad($user->id, 4, '0', STR_PAD_LEFT);

        // Create chat user.
        $updateData = $this->createChatUser($identity, $data['last_name'] . ' ' . $data['first_name']);

        $chatRoomIds = [];
        if (!empty($secondaryTherapists)) {
            $response = Http::get(env('THERAPIST_SERVICE_URL') . '/api/therapist/by-ids', [
                'ids' => \GuzzleHttp\json_encode($secondaryTherapists)
            ]);

            if (!empty($response) && $response->successful()) {
                $therapists = $response->json()['data'];
                foreach ($therapists as $therapist) {
                    $therapistIdentity = $therapist['identity'];
                    $chatRoomId = RocketChatHelper::createChatRoom($therapistIdentity, $identity);
                    TherapistServiceHelper::AddNewChatRoom($request->bearerToken(), $chatRoomId, $therapist['id']);
                    array_push($chatRoomIds, $chatRoomId);
                }
            }
        }

        // Create chat room.
        $therapistIdentity = $data['therapist_identity'];
        $chatRoomId = RocketChatHelper::createChatRoom($therapistIdentity, $identity);
        TherapistServiceHelper::AddNewChatRoom($request->bearerToken(), $chatRoomId, $data['therapist_id']);

        $updateData['identity'] = $identity;
        $updateData['chat_rooms'] = array_merge($chatRoomIds, [$chatRoomId]);

        $user->fill($updateData);
        $user->save();

        if (!$user) {
            DB::rollBack();
            return abort(500);
        }

        DB::commit();
        return ['success' => true, 'message' => 'success_message.user_add'];
    }

    /**
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
            $newSecondaryTherapistIds = [];
            $dataUpdate = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
            ];

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

            $chatRoomIds = $user->chat_rooms;
            if (isset($data['secondary_therapists'])) {
                $dataUpdate['secondary_therapists'] = $data['secondary_therapists'];

                if ($user->secondary_therapists) {
                    $newSecondaryTherapistIds = array_values(array_diff($data['secondary_therapists'], $user->secondary_therapists));
                } else {
                    $newSecondaryTherapistIds = $data['secondary_therapists'];
                }

                if (!empty($newSecondaryTherapistIds)) {
                    $response = Http::get(env('THERAPIST_SERVICE_URL') . '/api/therapist/by-ids', [
                        'ids' => \GuzzleHttp\json_encode($newSecondaryTherapistIds)
                    ]);

                    if (!empty($response) && $response->successful()) {
                        $therapists = $response->json()['data'];
                        foreach ($therapists as $therapist) {
                            $therapistIdentity = $therapist['identity'];
                            $chatRoomId = RocketChatHelper::createChatRoom($therapistIdentity, $user->identity);
                            TherapistServiceHelper::AddNewChatRoom($request->bearerToken(), $chatRoomId, $therapist['id']);
                            $chatRoomIds = array_merge($user->chat_rooms, [$chatRoomId]);
                        }
                    }
                }
            }

            $dataUpdate['chat_rooms'] = $chatRoomIds;
            $user->update($dataUpdate);

            if ($newSecondaryTherapistIds) {
                Http::post(env('THERAPIST_SERVICE_URL') . '/api/therapist/new-patient-notification', [
                    'therapist_ids' => $newSecondaryTherapistIds,
                    'patient_first_name' => $user->first_name,
                    'patient_last_name' => $user->last_name,
                ]);
            }
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
        $password = $username . 'PWD';
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
            'chat_password' => hash('sha256', $password)
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByTherapistIds(Request $request)
    {
        $therapistIds = $request->get('therapist_ids', []);
        $patients = User::whereIn('therapist_id', $therapistIds)->get();
        return PatientResource::collection($patients);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByTherapistId(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $patients = User::where('therapist_id', $therapistId)->get();
        return ['success' => true, 'data' => PatientResource::collection($patients)];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getPatientForTherapistRemove(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $data = $request->all();

        $query = User::query();
        if ($therapistId) {
            $query->where(function ($query) use ($data) {
                $query->where('therapist_id', $data['therapist_id'])->orWhereJsonContains('secondary_therapists', intval($data['therapist_id']));
            });
        }

        $patients = $query->get();
        return ['success' => true, 'data' => PatientForTherapistRemoveResource::collection($patients)];
    }

    /**
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
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     *
     * @return array
     * @throws \Exception
     */
    public function deleteAccount(Request $request, User $user)
    {
        $this->obfuscatedUserData($user);
        $user->delete();

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

        return ['success' => true, 'message' => 'success_message.deleted_account'];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteByTherapistId(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $users = User::where('therapist_id', $therapistId)->get();
        if (count($users) > 0) {
            foreach ($users as $user) {
                $this->obfuscatedUserData($user);
                $user->delete();
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
        $therapistIdentity = $request->get('therapist_identity');
        $oldTherapistChatRooms = $request->get('chat_rooms');
        $newTherapistChatRooms = $request->get('new_chat_rooms');

        // Remove chat room of previous therapist
        $chatRooms = array_intersect($oldTherapistChatRooms, $user->chat_rooms);
        $rooms = $user->chat_rooms;
        if (($key = array_search(reset($chatRooms), $rooms)) !== false) {
            unset($rooms[$key]);
        }

        // Remove secondary therapists if transfered therapist
        $secondaryTherapists = $user->secondary_therapists;
        if (($key = array_search($therapistId, $secondaryTherapists)) !== false) {
            unset($secondaryTherapists[$key]);
        }

        //Update own activities
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

        // Check if chat rooms of new therapist exist
        $chatRoomOfNewTherapists = array_intersect($newTherapistChatRooms, $user->chat_rooms);
        if (!$chatRoomOfNewTherapists) {
            // Create chat room for new therapist and patient
            $chatRoomId = RocketChatHelper::createChatRoom($therapistIdentity, $user->identity);
            TherapistServiceHelper::AddNewChatRoom($request->bearerToken(), $chatRoomId, $therapistId);
            $newChatRooms = array_merge($rooms, [$chatRoomId]);
        } else {
            $newChatRooms = $rooms;
        }

        // Update user chatrooms
        $updateData['chat_rooms'] = $newChatRooms;
        $updateData['therapist_id'] = $therapistId;
        $updateData['secondary_therapists'] = $secondaryTherapists;

        $user->update($updateData);
        $user->save();

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
    public function getPatientByPhone(Request $request) {
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
}

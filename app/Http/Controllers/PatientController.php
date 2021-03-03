<?php

namespace App\Http\Controllers;

use App\Helpers\RocketChatHelper;
use App\Helpers\TherapistServiceHelper;
use App\Http\Resources\PatientResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                $query = User::where('therapist_id', $data['therapist_id']);
            }

            if (isset($data['search_value'])) {
                $query->where(function ($query) use ($data) {
                    $query->where('identity', 'like', '%' . $data['search_value'] . '%')
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
                            $query->where('date_of_birth', '>=', date_format($startDate, config('settings.defaultTimestampFormat')))
                                ->where('date_of_birth', '<=', date_format($endDate, config('settings.defaultTimestampFormat')));
                        } else {
                            $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                        }
                    }
                });
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

        $phoneExist = User::where('phone', $data['phone'])->first();
        if ($phoneExist) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.phone_exists');
        }

        $user = User::create([
            'therapist_id' => $data['therapist_id'],
            'phone' => $data['phone'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'country_id' => $data['country_id'],
            'gender' => $data['gender'],
            'clinic_id' => $data['clinic_id'],
            'date_of_birth' => $dateOfBirth,
            'note' => $data['note']
        ]);

        // create unique identity
        $clinicIdentity = $data['clinic_identity'];
        $identity = 'P' . $clinicIdentity .
            str_pad($user->id, 4, '0', STR_PAD_LEFT);

        // create chat user
        $updateData = $this->createChatUser($identity, $data['last_name'] . ' ' . $data['first_name']);

        // create chat room
        $therapistIdentity = $data['therapist_identity'];
        $chatRoomId = RocketChatHelper::createChatRoom($therapistIdentity, $identity);
        TherapistServiceHelper::AddNewChatRoom($request->bearerToken(), $chatRoomId);

        $updateData['identity'] = $identity;
        $updateData['chat_rooms'] = [$chatRoomId];
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
            $dataUpdate = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
            ];

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

            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }

    /**
     * @param string $username
     * @param string $name
     *
     * @return array
     * @throws \Illuminate\Http\Client\RequestException
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
}

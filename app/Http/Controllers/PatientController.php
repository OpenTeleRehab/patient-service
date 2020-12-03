<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
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
        $query = User::where(function ($query) use ($data) {
            $query->where('identity', 'like', '%' . $data['search_value'] . '%')
                ->orWhere('first_name', 'like', '%' . $data['search_value'] . '%')
                ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%');
        });

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    $excludedColumns = ['country', 'clinic'];
                    if (in_array($filterObj->columnName, $excludedColumns)) {
                        continue;
                    } elseif ($filterObj->columnName === 'date_of_birth') {
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
        return ['success' => true, 'data' => UserResource::collection($users), 'info' => $info];
    }

    /**
     * @param Request $request
     * @return array|void
     * @throws \Exception
     */
    public function store(Request  $request)
    {
        DB::beginTransaction();

        $firstName = $request->get('first_name');
        $lastName = $request->get('last_name');
        $phone = $request->get('phone');
        $country = $request->get('country_id');
        $clinic = $request->get('clinic_id');
        $gender = $request->get('gender');
        $note = $request->get('note');
        if ($request->get('date_of_birth')) {
            $dateOfBirth = date_create_from_format('d/m/Y', $request->get('date_of_birth'));
        }

        $clinicIdentity = $request->get('clinic_identity');
        $availablePhone = User::where('phone', $phone)->count();
        if ($availablePhone) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.phone_exists');
        }

        $user = User::create([
            'phone' => $phone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'country_id' => $country,
            'gender' => $gender,
            'clinic_id' => $clinic,
            'date_of_birth' => $dateOfBirth ? date_format($dateOfBirth, config('settings.defaultTimestampFormat')) : null,
            'note' => $note
        ]);

        // Todo create function in model to generate this identity.
        $identity = 'P' . $clinicIdentity .
            str_pad($user->id, 4, '0', STR_PAD_LEFT);
        $user->fill(['identity' => $identity]);
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
                'phone' => $data['phone']
            ];

            if (isset($data['note'])) {
                $dataUpdate['note'] = $data['note'];
            }

            if (isset($data['date_of_birth'])) {
                $dateOfBirth = date_create_from_format('d/m/Y', $data['date_of_birth']);
                $dataUpdate['date_of_birth'] = date_format($dateOfBirth, config('settings.defaultTimestampFormat'));
            }

            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }
}

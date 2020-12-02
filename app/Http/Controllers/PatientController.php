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
        $users = User::all();

        return ['success' => true, 'data' => UserResource::collection($users)];
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
        $dateOfBirth = $request->get('date_of_birth');
        $dateOfBirth = $dateOfBirth ? date_time_format($dateOfBirth, config('settings.defaultTimestampFormat')) : null;
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
            'date_of_birth' => $dateOfBirth,
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
                'gender' => $data['gender']
            ];

            if (isset($data['note'])) {
                $dataUpdate['note'] = $data['note'];
            }
            if (isset($data['date_of_birth'])) {
                $dataUpdate['date_of_birth'] = date_time_format($data['date_of_birth'], config('settings.defaultTimestampFormat'));
            }

            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }
}

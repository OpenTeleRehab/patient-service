<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|bool[]
     */
    public function addNewPinCode(Request $request)
    {
        $user = User::where('phone'. $request->phone)
            ->where('opt_code', $request->opt_code)
            ->firstOrFail();

        return $this->savePinCode($user, $request->pin);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|bool[]
     */
    public function changeNewPinCode(Request $request)
    {
        return $this->savePinCode(Auth::user(), $request->pin);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function login(Request $request)
    {
        $credentials = [
            'phone' => $request->phone,
            'password' => $request->pin,
            'enabled' => 1,
        ];
        if (Auth::attempt($credentials)) {
            return ['success' => true, 'data' => Auth::user()];
        }

        return ['success' => false, 'message' => 'error.invalid_credentials'];
    }

    /**
     * @return bool[]
     */
    public function logout()
    {
        Auth::logout();

        return ['success' => true];
    }

    /**
     * @param \App\Models\User $user
     * @param string $pinCode
     *
     * @return array|bool[]
     */
    private function savePinCode(User $user, $pinCode)
    {
        if (strlen($pinCode) !== 4) {
            return ['success' => false, 'message' => 'error.invalid_pin_code'];
        }

        $user->fill([
            'password' => Hash::make($pinCode)
        ])->save();

        return ['success' => true];
    }
}

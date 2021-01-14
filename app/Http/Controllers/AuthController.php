<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
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
        $phone = '+' . $request->phone;
        $user = User::where('phone', $phone)
            ->where('otp_code', $request->otp_code)
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
            'phone' => '+' . $request->phone,
            'password' => $request->pin,
            'enabled' => 1,
        ];

        if (Auth::attempt($credentials)) {
            /** @var User $user */
            $user = Auth::user();
            // Always make sure old access token is cleared.
            $user->tokens()->delete();

            $token = $user->createToken(config('auth.guards.api.tokenName'))->accessToken;
            $data = [
                'profile' => new UserResource($user),
                'token' => $token,
            ];
            return ['success' => true, 'data' => $data];
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

        $user->update([
            'password' => Hash::make($pinCode),
        ]);

        // Always make sure old access token is cleared.
        $user->tokens()->delete();

        $token = $user->createToken(config('auth.guards.api.tokenName'))->accessToken;
        $data = [
            'profile' => new UserResource($user),
            'token' => $token,
        ];

        return ['success' => true, 'data' => $data];
    }
}

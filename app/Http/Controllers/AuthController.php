<?php

namespace App\Http\Controllers;

use App\Events\LoginEvent;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|bool[]
     */
    public function addNewPinCode(Request $request)
    {
        $phone = $request->phone;
        $user = User::where('phone', $phone)
            ->where('otp_code', $request->otp_code)
            ->firstOrFail();

        // Accept terms of services and privacy policy.
        $data = ['term_and_condition_id' => $request->get('term_and_condition_id'), 'privacy_and_policy_id' => $request->get('privacy_and_policy_id')];

        // Update user language.
        if ($request->has('language')) {
            $data['language_id'] = $request->get('language');
        }

        $user->update($data);

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
            /** @var User $user */
            $user = Auth::user();
            // Always make sure old access token is cleared.
            $user->tokens()->delete();

            $token = $user->createToken(config('auth.guards.api.tokenName'))->accessToken;
            $data = [
                'profile' => new UserResource($user),
                'token' => $token,
            ];
            event(new LoginEvent($request));
            return ['success' => true, 'data' => $data];
        }

        return ['success' => false, 'message' => 'error.invalid_credentials'];
    }

    /**
     * @return bool[]
     */
    public function logout()
    {
        /** @var User $user */
        $user = Auth::user();
        $user->tokens()->delete();

        return ['success' => true];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|bool[]
     */
    public function comparePinCode(Request $request)
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = Auth::user();
        if (Hash::check($request->pin, $user->getAuthPassword())) {
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'error.invalid_pin'];
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

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function acceptTermCondition(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'term_and_condition_id' => $request->get('term_and_condition_id'),
        ]);

        return ['success' => true, 'data' => ['token' => $request->bearerToken()]];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function acceptPrivacyPolicy(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'privacy_and_policy_id' => $request->get('privacy_and_policy_id'),
        ]);

        return ['success' => true, 'data' => ['token' => $request->bearerToken()]];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function enableKidTheme(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'kid_theme' => $request->get('kid_theme'),
        ]);

        return ['success' => true, 'data' => ['profile' => new UserResource($user)]];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function createFirebaseToken(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'firebase_token' => $request->get('firebase_token'),
        ]);

        return ['success' => true, 'data' => ['firebase_token' => $request->get('firebase_token')]];
    }
}

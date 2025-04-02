<?php

namespace App\Http\Controllers;

use App\Models\ApiUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiUserAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $apiUser = ApiUser::where('email', $request->email)->first();

        if (!$apiUser || !Hash::check($request->password, $apiUser->password)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Generate API token
        $token = $apiUser->createToken('api-user-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'api_user' => $apiUser,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}

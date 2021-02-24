<?php

namespace App\Http\Controllers;

use App\Helpers\SMSHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function sendCode(Request $request)
    {
        $to = $request->get('to');
        $hash = $request->get('hash');
        $patientCount = User::where('phone', $to)->count();

        if ($patientCount === 0) {
            return ['success' => false, 'message' => 'error.no_patient_found'];
        }

        try {
            SMSHelper::sendCode($to, $hash);
            return ['success' => true, 'message' => 'success.code_sent'];
        } catch (\Exception $e) {
            Log::error('error.sms_gateway.send', [$e->getMessage()]);
            return ['success' => false, 'message' => 'error.sms_gateway'];
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function verifyCode(Request $request)
    {
        try {
            $to = $request->get('to');
            $code = $request->get('code');
            $isVerified = SMSHelper::verifyCode($to, $code);
            if ($isVerified) {
                User::where('phone', $to)->update(['otp_code' => $code, 'enabled' => true]);
                return ['success' => true, 'message' => 'success.code_verified'];
            }
            return ['success' => false, 'message' => 'error.invalid_code'];
        } catch (\Exception $e) {
            Log::error('error.sms_gateway.verify', [$e->getMessage()]);
            return ['success' => false, 'message' => 'error.sms_gateway'];
        }
    }
}

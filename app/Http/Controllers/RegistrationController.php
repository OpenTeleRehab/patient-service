<?php

namespace App\Http\Controllers;

use App\Helpers\SMSHelper;
use App\Models\User;
use Illuminate\Http\Request;

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
        $patientCount = User::where('phone', $to)->count();

        if ($patientCount === 0) {
            return ['success' => false, 'message' => 'error.no_patient_found'];
        }

        try {
            SMSHelper::sendCode($to);
            return ['success' => true, 'message' => 'success.code_sent'];
        } catch (\Exception $e) {
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
            $verification = SMSHelper::verifyCode($to, $code);
            if ($verification->valid) {
                User::where('phone', $to)->update(['enabled' => true]);
                return ['success' => true, 'message' => 'success.code_verified'];
            }
            return ['success' => false, 'message' => 'error.invalid_code'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'error.sms_gateway'];
        }
    }
}

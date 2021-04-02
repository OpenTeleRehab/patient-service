<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\TreatmentPlanController;
use \App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('register/send-code', [RegistrationController::class, 'sendCode']);
Route::post('register/verify-code', [RegistrationController::class, 'verifyCode']);
Route::apiResource('patient', PatientController::class);
Route::get('patient/list/by-therapist-ids', [PatientController::class, 'getByTherapistIds']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/add-new-pin', [AuthController::class, 'addNewPinCode']);
Route::get('treatment-plan/get-treatment-plan-detail', [TreatmentPlanController::class, 'getActivities']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/compare-pin', [AuthController::class, 'comparePinCode']);
    Route::post('auth/change-pin', [AuthController::class, 'changeNewPinCode']);
    Route::post('auth/accept-term-condition', [AuthController::class, 'acceptTermCondition']);
    Route::post('treatment-plan/complete_activity/{activity}', [TreatmentPlanController::class, 'completeActivity']);
    Route::get('treatment-plan/get-summary', [TreatmentPlanController::class, 'getSummary']);
    Route::get('treatment-plan/get-treatment-plan', [TreatmentPlanController::class, 'getActivities']);
    Route::post('treatment-plan/complete_questionnaire/{activity}', [TreatmentPlanController::class, 'completeQuestionnaire']);
    Route::post('treatment-plan/complete_goal', [TreatmentPlanController::class, 'completeGoal']);
    Route::get('appointment/get-patient-appointments', [AppointmentController::class, 'getPatientAppointments']);
    Route::post('appointment/request-appointment', [AppointmentController::class, 'requestAppointment']);
});

Route::apiResource('treatment-plan', TreatmentPlanController::class);
Route::apiResource('appointment', AppointmentController::class);
Route::post('appointment/updateStatus/{appointment}', [AppointmentController::class, 'updateStatus']);

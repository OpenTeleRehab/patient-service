<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\TreatmentPlanController;
use \App\Http\Controllers\AppointmentController;
use \App\Http\Controllers\ChartController;
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
Route::get('patient/list/by-therapist-id', [PatientController::class, 'getByTherapistId']);
Route::get('patient/list/for-therapist-remove', [PatientController::class, 'getPatientForTherapistRemove']);
Route::get('patient/unread/{user}', [PatientController::class, 'getUserCounters']);
Route::post('patient/activateDeactivateAccount/{user}', [PatientController::class, 'activateDeactivateAccount']);
Route::post('patient/deleteAccount/{user}', [PatientController::class, 'deleteAccount']);
Route::post('patient/delete-chat-room/by-id', [PatientController::class, 'deleteChatRoomById']);
Route::get('patient/list/by-clinic-id', [PatientController::class, 'getByClinicId']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/add-new-pin', [AuthController::class, 'addNewPinCode']);
Route::get('treatment-plan/get-treatment-plan-detail', [TreatmentPlanController::class, 'getActivities']);
Route::get('chart/get-data-for-global-admin', [ChartController::class, 'getDataForGlobalAdmin']);
Route::get('chart/get-data-for-country-admin', [ChartController::class, 'getDataForCountryAdmin']);
Route::get('chart/get-data-for-clinic-admin', [ChartController::class, 'getDataForClinicAdmin']);
Route::get('activities/list/by-ids', [ActivityController::class, 'getByIds']);
Route::post('activities/delete/by-ids', [ActivityController::class, 'deleteByIds']);
Route::get('treatment-plan/get-used-disease', [TreatmentPlanController::class, 'getUsedDisease']);

Route::group(['middleware' => ['auth:api', 'user']], function () {
    Route::get('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/compare-pin', [AuthController::class, 'comparePinCode']);
    Route::post('auth/change-pin', [AuthController::class, 'changeNewPinCode']);
    Route::post('auth/accept-term-condition', [AuthController::class, 'acceptTermCondition']);
    Route::post('auth/accept-privacy-policy', [AuthController::class, 'acceptPrivacyPolicy']);
    Route::post('auth/enable-kid-theme', [AuthController::class, 'enableKidTheme']);
    Route::post('treatment-plan/complete_activity', [TreatmentPlanController::class, 'completeActivity']);
    Route::get('treatment-plan/get-summary', [TreatmentPlanController::class, 'getSummary']);
    Route::get('treatment-plan/get-treatment-plan', [TreatmentPlanController::class, 'getActivities']);
    Route::post('treatment-plan/complete_questionnaire', [TreatmentPlanController::class, 'completeQuestionnaire']);
    Route::post('treatment-plan/complete_goal', [TreatmentPlanController::class, 'completeGoal']);
    Route::get('appointment/get-patient-appointments', [AppointmentController::class, 'getPatientAppointments']);
    Route::post('appointment/request-appointment', [AppointmentController::class, 'requestAppointment']);
    Route::post('appointment/update-patient-status/{appointment}', [AppointmentController::class, 'updatePatientStatus']);
    Route::get('treatment-plan/export/on-going', [TreatmentPlanController::class, 'exportOnGoing']);
    Route::delete('patient/profile/delete', [PatientController::class, 'delete']);
    Route::get('patient/profile/export', [PatientController::class, 'export']);
    Route::get('achievement/get-patient-achievements', [PatientController::class, 'getPatientAchievements']);
});

Route::apiResource('treatment-plan', TreatmentPlanController::class);
Route::get('treatment-plan/export/{treatmentPlan}', [TreatmentPlanController::class, 'export']);
Route::apiResource('appointment', AppointmentController::class);
Route::post('appointment/updateStatus/{appointment}', [AppointmentController::class, 'updateStatus']);
Route::post('patient/delete/by-clinic', [PatientController::class, 'deleteByClinicId']);
Route::post('patient/delete/by-therapist', [PatientController::class, 'deleteByTherapistId']);
Route::post('patient/transfer-to-therapist/{user}', [PatientController::class, 'transferToTherapist']);

Route::get('patient/count/by-phone-number', [PatientController::class, 'getPatientByPhone']);
Route::get('achievement/get-badge-icon/{filename}', [PatientController::class, 'getBadgeIcon']);

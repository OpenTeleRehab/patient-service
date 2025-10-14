<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ApiUserAuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AssistiveTechnologyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallHistoryController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ExternalApiController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ForwarderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\TherapistController;
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

Route::get('patient/get-call-access-token', [PatientController::class, 'getCallAccessToken']);

Route::group(['middleware' => ['auth:api', 'user', 'verify.data.access']], function () {
    Route::get('auth/logout', [AuthController::class, 'logout'])->middleware('role:mobile');
    Route::get('auth/compare-pin', [AuthController::class, 'comparePinCode'])->middleware('role:mobile');
    Route::post('auth/change-pin', [AuthController::class, 'changeNewPinCode'])->middleware('role:mobile');
    Route::post('auth/accept-term-condition', [AuthController::class, 'acceptTermCondition'])->middleware('role:mobile');
    Route::post('auth/accept-privacy-policy', [AuthController::class, 'acceptPrivacyPolicy'])->middleware('role:mobile');
    Route::post('auth/enable-kid-theme', [AuthController::class, 'enableKidTheme'])->middleware('role:mobile');
    Route::post('auth/create-firebase-token', [AuthController::class, 'createFirebaseToken'])->middleware('role:mobile');

    Route::get('chart/get-data-for-global-admin', [ChartController::class, 'getDataForGlobalAdmin']); // deprecated
    Route::get('chart/get-data-for-country-admin', [ChartController::class, 'getDataForCountryAdmin']); // deprecated
    Route::get('chart/get-data-for-clinic-admin', [ChartController::class, 'getDataForClinicAdmin']); // deprecated

    // Notifications
    Route::get('/push-notification', [NotificationController::class, 'pushNotification'])->middleware('role:internal');

    // Patients
    Route::get('patient/id/{id}', [PatientController::class, 'getById'])->middleware('role:internal');
    Route::get('patient/list/by-ids', [PatientController::class, 'getByIds'])->middleware('role:internal');
    Route::get('patient/list/global', [PatientController::class, 'getPatientsForGlobalData'])->middleware('role:internal');
    Route::get('patient/list/by-therapist-id', [PatientController::class, 'getByTherapistId'])->middleware('role:internal');
    Route::get('patient/list/by-therapist-ids', [PatientController::class, 'getByTherapistIds'])->middleware('role:internal');
    Route::get('patient/list/for-therapist-remove', [PatientController::class, 'getPatientForTherapistRemove'])->middleware('role:internal');
    Route::get('patient/list/data-for-phone-service', [PatientController::class, 'getPatientDataForPhoneService']); // Deprecated from phone service
    Route::get('patient/transfer', [PatientController::class, 'transfer']); // not used
    Route::get('patient/profile/export', [PatientController::class, 'export'])->middleware('role:mobile');
    Route::get('patient/count/by-phone-number', [PatientController::class, 'getPatientByPhone'])->middleware('role:internal');
    Route::get('patient/list/get-raw-data', [PatientController::class, 'getPatientRawData'])->middleware('role:internal');
    Route::post('patient/delete/by-clinic', [PatientController::class, 'deleteByClinicId'])->middleware('role:internal');
    Route::post('patient/delete/by-therapist', [PatientController::class, 'deleteByTherapistId'])->middleware('role:internal');
    Route::post('patient/transfer-to-therapist/{user}', [PatientController::class, 'transferToTherapist'])->middleware('role:internal');
    Route::post('patient/deleteAccount/{id}', [PatientController::class, 'deleteAccount'])->middleware('role:internal');
    Route::post('patient/delete-chat-room/by-id', [PatientController::class, 'deleteChatRoomById'])->middleware('role:internal');
    Route::post('patient/activateDeactivateAccount/{user}', [PatientController::class, 'activateDeactivateAccount'])->middleware('role:internal');
    Route::delete('patient/profile/delete', [PatientController::class, 'delete'])->middleware('role:mobile');
    Route::get('patient', [PatientController::class, 'index'])->middleware('role:internal');
    Route::post('patient', [PatientController::class, 'store'])->middleware('role:internal');
    Route::put('patient/{id}', [PatientController::class, 'update'])->middleware('role:internal,mobile');
    Route::get('patient/list-for-chatroom', [PatientController::class, 'listForChatroom'])->middleware('role:internal');

    // Activities
    Route::get('patient-activities/list/by-filters', [ActivityController::class, 'getActivities'])->middleware('role:internal');
    Route::get('patient-activities/list/by-ids', [ActivityController::class, 'getByIds'])->middleware('role:internal');
    Route::post('patient-activities/delete/by-ids', [ActivityController::class, 'deleteByIds']); // not used

    // Therapist
    Route::get('therapists', [TherapistController::class, 'getOwnTherapists'])->middleware('role:mobile');

    // Treatment Plans
    Route::post('treatment-plan/complete_activity', [TreatmentPlanController::class, 'completeActivity'])->middleware('role:mobile');
    Route::get('treatment-plan/get-summary', [TreatmentPlanController::class, 'getSummary'])->middleware('role:mobile');
    Route::get('treatment-plan/get-treatment-plan', [TreatmentPlanController::class, 'getActivities'])->middleware('role:mobile');
    Route::post('treatment-plan/complete_questionnaire', [TreatmentPlanController::class, 'completeQuestionnaire'])->middleware('role:mobile');
    Route::post('treatment-plan/complete_goal', [TreatmentPlanController::class, 'completeGoal'])->middleware('role:mobile');
    Route::get('treatment-plan/export/{treatmentPlan}', [TreatmentPlanController::class, 'export'])->middleware('role:internal');
    Route::get('treatment-plan/get-used-disease', [TreatmentPlanController::class, 'getUsedDisease'])->middleware('role:internal');
    Route::get('treatment-plan/list/global', [TreatmentPlanController::class, 'getTreatmentPlanForGlobalData'])->middleware('role:internal');

    Route::get('patient-treatment-plan/get-treatment-plan-detail', [TreatmentPlanController::class, 'getActivities'])->middleware('role:internal');
    Route::apiResource('patient-treatment-plan', TreatmentPlanController::class)->middleware('role:internal');

    // Appointments
    Route::get('appointment/get-patient-appointments', [AppointmentController::class, 'getPatientAppointments'])->middleware('role:mobile');
    Route::post('appointment/request-appointment', [AppointmentController::class, 'requestAppointment'])->middleware('role:mobile');
    Route::post('appointment/update-patient-status/{appointment}', [AppointmentController::class, 'updatePatientStatus'])->middleware('role:mobile');
    Route::post('appointment/updateStatus/{appointment}', [AppointmentController::class, 'updateStatus'])->middleware('role:internal');
    Route::put('appointment/update-as-read', [AppointmentController::class, 'updateAsRead'])->middleware('role:internal');
    Route::get('appointment', [AppointmentController::class, 'index'])->middleware('role:internal');
    Route::post('appointment', [AppointmentController::class, 'store'])->middleware('role:internal');
    Route::put('appointment/{appointment}', [AppointmentController::class, 'update'])->middleware('role:internal,mobile');
    Route::delete('appointment/{appointment}', [AppointmentController::class, 'destroy'])->middleware('role:internal,mobile');

    Route::get('treatment-plan/on-going/export', [TreatmentPlanController::class, 'exportOnGoing'])->middleware('role:mobile');
    Route::get('achievement/get-patient-achievements', [PatientController::class, 'getPatientAchievements'])->middleware('role:mobile');

    // Assistive technology
    Route::apiResource('patient-assistive-technologies', AssistiveTechnologyController::class)->middleware('role:internal');
    Route::get('assistive-technologies/get-at-patient', [AssistiveTechnologyController::class, 'getAssistiveTechnologyProvidedPatients'])->middleware('role:internal');
    Route::get('assistive-technologies/get-used-at', [AssistiveTechnologyController::class, 'getUsedAssistiveTechnology'])->middleware('role:internal');

    // Admin Service
    Route::name('admin.')->group(function () {
        Route::get('profession', [ForwarderController::class, 'index'])->middleware('role:mobile');
    });

    // Global Admin Service
    Route::name('global_admin.')->group(function () {
        Route::post('survey/skip', [ForwarderController::class, 'store'])->middleware('role:mobile');
        Route::post('survey/submit', [ForwarderController::class, 'store'])->middleware('role:mobile');
    });

    // Report
    Route::get('export', [ReportController::class, 'export'])->middleware('role:internal');

    // Call history
    Route::apiResource('call-history', CallHistoryController::class)->middleware('role:internal');

    Route::get('download-file', [FileController::class, 'download'])->middleware('role:internal');
});

// Public
Route::post('register/send-code', [RegistrationController::class, 'sendCode']);
Route::post('register/verify-code', [RegistrationController::class, 'verifyCode']);

// App Setting
Route::get('app/settings', [SettingController::class, 'getSetting']);

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/add-new-pin', [AuthController::class, 'addNewPinCode']);

Route::get('achievement/get-badge-icon/{filename}', [PatientController::class, 'getBadgeIcon']);

Route::get('therapist/by-ids', [TherapistController::class, 'getByIds']); // @deprecated

Route::name('admin.')->group(function () {
    Route::get('country/list/by-clinic', [ForwarderController::class, 'index']);
});

Route::post('/external/login', [ApiUserAuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/external/logout', [ApiUserAuthController::class, 'logout']);
    Route::get('/external/patients', [ExternalApiController::class, 'getPatients']);
    Route::get('/external/patient/{id}', [ExternalApiController::class, 'getPatient']);
});

<?php

use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\PatientController;
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

Route::get('register/send-code', [RegistrationController::class, 'sendCode']);
Route::post('register/verify-code', [RegistrationController::class, 'verifyCode']);
Route::apiResource('patient', PatientController::class);

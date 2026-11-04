<?php

use App\Http\Controllers\TopicoController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['application' => 'Tópico', 'frontend' => 'http://localhost:9000']);
});

Route::prefix('api')->group(function () {
    Route::get('csrf', fn () => response()->json(['token' => csrf_token()]));
    Route::post('login', [TopicoController::class, 'login'])->middleware('throttle:6,1');
    Route::middleware(['auth', EnsureActiveUser::class])->group(function () {
        Route::get('me', fn (Request $request) => $request->user());
        Route::post('logout', [TopicoController::class, 'logout']);
        Route::get('dashboard', [TopicoController::class, 'dashboard']);
        Route::get('patients', [TopicoController::class, 'patients']);
        Route::post('patients', [TopicoController::class, 'savePatient']);
        Route::put('patients/{id}', [TopicoController::class, 'savePatient']);
        Route::get('patients/{id}/history', [TopicoController::class, 'patientHistory']);
        Route::get('requests', [TopicoController::class, 'requests']);
        Route::post('requests', [TopicoController::class, 'saveRequest']);
        Route::post('requests/{id}/start', [TopicoController::class, 'startRequest']);
        Route::post('requests/{id}/cancel', [TopicoController::class, 'cancelRequest']);
        Route::get('consultations', [TopicoController::class, 'consultations']);
        Route::get('consultations/{id}', [TopicoController::class, 'consultation']);
        Route::post('consultations', [TopicoController::class, 'saveConsultation']);
        Route::put('consultations/{id}', [TopicoController::class, 'saveConsultation']);
        Route::post('consultations/{id}/deliver', [TopicoController::class, 'deliver']);
        Route::get('medicines', [TopicoController::class, 'medicines']);
        Route::post('medicines', [TopicoController::class, 'saveMedicine']);
        Route::put('medicines/{id}', [TopicoController::class, 'saveMedicine']);
        Route::post('stock/entries', [TopicoController::class, 'stockEntry']);
        Route::get('stock/movements', [TopicoController::class, 'movements']);
        Route::get('referrals', [TopicoController::class, 'referrals']);
        Route::post('referrals', [TopicoController::class, 'saveReferral']);
        Route::put('referrals/{id}', [TopicoController::class, 'saveReferral']);
        Route::get('reports', [TopicoController::class, 'reports']);
        Route::get('users', [TopicoController::class, 'users']);
        Route::post('users', [TopicoController::class, 'saveUser']);
        Route::put('users/{id}', [TopicoController::class, 'saveUser']);
    });
});

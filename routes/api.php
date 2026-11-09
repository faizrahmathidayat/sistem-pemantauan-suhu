<?php

use App\Http\Controllers\Api\V1\SensorController;
use Illuminate\Support\Facades\Route;

// Telemetry dari ESP32: POST /api/v1/telemetry (Bearer token per zona).
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('telemetry', SensorController::class)
        ->middleware(['device', 'throttle:telemetry'])
        ->name('telemetry');
});

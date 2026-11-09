<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardDataController;
use App\Http\Controllers\IncidentAcknowledgeController;
use App\Http\Controllers\ZoneReadingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/data', DashboardDataController::class)->name('dashboard.data');
    Route::get('/zones/{zone}/readings', ZoneReadingsController::class)->name('zones.readings');
    Route::post('/incidents/{incident}/acknowledge', IncidentAcknowledgeController::class)->name('incidents.acknowledge');
});

<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardDataController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IncidentAcknowledgeController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ZoneReadingsController;
use App\Http\Controllers\ZoneSettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/home', HomeController::class)->name('home');

    Route::middleware('can:dashboard.view')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/dashboard/data', DashboardDataController::class)->name('dashboard.data');
        Route::get('/zones/{zone}/readings', ZoneReadingsController::class)->name('zones.readings');
    });

    Route::get('/incidents', [IncidentController::class, 'index'])->middleware('can:incident.view')->name('incidents.index');
    Route::post('/incidents/{incident}/acknowledge', IncidentAcknowledgeController::class)
        ->middleware('can:incident.acknowledge')->name('incidents.acknowledge');

    Route::middleware('can:notification.view')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('can:auditlog.view')->name('audit-logs.index');

    Route::resource('roles', RoleController::class)->except('show')->middleware('can:role.manage');

    Route::get('/zones', [ZoneSettingsController::class, 'index'])->middleware('can:zone.view')->name('zones.index');
    Route::middleware('can:zone.update')->group(function () {
        Route::get('/zones/{zone}/edit', [ZoneSettingsController::class, 'edit'])->name('zones.edit');
        Route::put('/zones/{zone}', [ZoneSettingsController::class, 'update'])->name('zones.update');
    });
});

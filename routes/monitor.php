<?php

use App\Http\Controllers\MonitorController;
use App\Http\Controllers\MonitorDataController;
use Illuminate\Support\Facades\Route;

/*
| Monitor publik (TV gudang). Dimuat di luar grup middleware `web` (lihat bootstrap/app.php):
| tanpa sesi, tanpa cookie, tanpa login. Kode rahasia di alamat adalah satu-satunya pengaman.
| Tiap polling TV tidak boleh menulis baris sesi ke database.
|
| Throttle tanpa nama memakai kunci IP yang sama untuk semua rute; awalan (parameter ketiga) membuat
| jatah halaman, data, dan login (throttle:10,1) terpisah, sehingga polling TV tidak mengunci login.
*/
Route::get('/monitor/{token}', MonitorController::class)
    ->middleware('throttle:60,1,monitor-page')
    ->name('monitor.show');

Route::get('/monitor/{token}/data', MonitorDataController::class)
    ->middleware('throttle:300,1,monitor-data')
    ->name('monitor.data');

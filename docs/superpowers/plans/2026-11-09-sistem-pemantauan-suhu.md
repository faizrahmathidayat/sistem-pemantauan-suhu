# Sistem Pemantauan Suhu & Kelembapan Gudang Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun sistem IoT (ESP32 + DHT22 → API Laravel 13 → dashboard web) yang memantau suhu/kelembapan gudang produk segar dan memberi notifikasi in-app ke semua user saat ada bahaya atau sensor mati.

**Architecture:** ESP32 mengirim `POST /api/v1/telemetry` ber-token per zona. Controller invokable menyimpan `sensor_logs`, lalu `IncidentManager` menilai status lewat `ZoneStatusEvaluator`, membuka/menutup `incidents`, dan mengirim `IncidentOpened` (saluran `database`) ke semua `User`. Scheduler tiap menit mendeteksi sensor offline. Dashboard Blade me-poll endpoint JSON tiap 15 detik.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL (Laragon) / SQLite in-memory untuk tes, Blade + Tailwind + Chart.js (Vite), Arduino C++ (ESP32, Adafruit DHT, ArduinoJson).

**Spec:** `docs/superpowers/specs/2026-11-09-sistem-pemantauan-suhu-design.md` (dibaca bersama plan ini; angka dan aturan di sana mengikat).

## Global Constraints

- Laravel 13, PHP 8.4 (constructor property promotion, typed properties, enum, readonly, arrow function bila cocok). Berhenti dan tanya pemilik bila `composer` tidak bisa memasang Laravel 13.
- Zona waktu aplikasi `Asia/Jakarta`; waktu data = `created_at` server.
- Payload: `{device_id, temperature, humidity}`; temperature −40..80, humidity 0..100. Respons: 201 sukses, 401 token/device salah, 422 data tak valid.
- Token perangkat: sha256 di `zones.device_token_hash`, dicari berindeks; `device_id` di body harus milik zona pemilik token.
- Status: Bahaya = di luar `min..max` (nilai tepat di batas BUKAN bahaya); Waspada = di dalam rentang tetapi `>= max - margin` atau `<= min + margin` (suhu pakai `warning_margin` default 2; kelembapan pakai `humidity_warning_margin` default 5, hanya bila batas kelembapan tidak null); Offline = pembacaan terakhir > 120 detik.
- Insiden dibuka setelah 2 pembacaan berturut-turut bahaya pada `reason` yang sama (selisih antar pembacaan ≤ 120 detik, diturunkan dari 2 `sensor_logs` terakhir, tanpa penghitung tersimpan); ditutup setelah 2 pembacaan berturut-turut tidak bahaya (waspada = tidak bahaya). Satu notifikasi per insiden dibuka, tidak ada saat ditutup. Satu zona maksimal satu insiden terbuka per `reason`.
- Insiden `sensor_offline`: dibuka oleh `zones:check-offline` (tiap menit), ditutup begitu ada pembacaan baru; zona tanpa log sama sekali tidak pernah offline.
- Notifikasi: sinkron (tanpa queue), saluran `database` saja, ke semua `User`; pesan terpisah dari `via()` agar WhatsApp bisa ditambah.
- Tanpa halaman pendaftaran publik; semua halaman web di belakang login.
- Retensi `sensor_logs` 90 hari (`MassPrunable`, `model:prune` harian).
- Nilai awal zona (placeholder): Zona A Telur 10–20°C RH 60–85%; Zona B Sayur Daun 15–25°C RH 80–95%; Zona C Buah 8–13°C RH 85–95%.
- Antarmuka berbahasa Indonesia. Tes backend memakai SQLite in-memory dan `php artisan test`.
- Commit lokal tiap task dengan trailer `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Jangan `git push` tanpa konfirmasi pemilik.

## Review Focus

1. Nilai tepat di batas (suhu = `temp_max`) → Waspada, bukan Bahaya (Task 3).
2. Zona dengan batas kelembapan null → kelembapan diabaikan, tak pernah membuat insiden kelembapan (Task 3, 5).
3. Token milik zona A dengan `device_id` zona B → 401 dan tidak ada log tersimpan (Task 4).
4. Dashboard dengan nol zona, atau zona tanpa log sama sekali → tampil tanpa error, bukan "offline" (Task 6, 8, 9).
5. "Tandai Telah Ditangani" ditekan dua kali, atau pada insiden yang sudah tertutup → pengklik pertama tetap tercatat, tidak error (Task 8).

---

### Task 1: Lingkungan, scaffold Laravel 13, git

**Files:**
- Create: seluruh scaffold Laravel di root `D:\GMI\PROJECT\super-app-gmi\iot-gmi\`, `.gitignore`
- Modify: `.env`, `.env.example`, `config/app.php` (timezone), `phpunit.xml`

**Interfaces:**
- Produces: proyek Laravel 13 yang bisa `php artisan test`; DB MySQL `iot_gmi`; repo git dengan remote `origin`.

- [ ] **Step 1: Pasang PHP 8.4.** Tanya pemilik dulu (nama file, sumber, ukuran) sebelum mengunduh: arsip zip PHP 8.4 x64 dari windows.php.net ke `C:\laragon\bin\php\`. Alternatif: pemilik memasangnya lewat menu Laragon. Aktifkan ekstensi di `php.ini`: `openssl, pdo_mysql, pdo_sqlite, sqlite3, mbstring, fileinfo, curl, zip, intl`. Jangan mengubah PHP default sistem; panggil dengan path penuh atau atur PATH sesi saja.
- [ ] **Step 2: Verifikasi.** Run: `php -v` (dengan PHP 8.4 di PATH sesi). Expected: `PHP 8.4.x`.
- [ ] **Step 3: Scaffold.** Jalankan `composer create-project laravel/laravel _scaffold` di dalam `iot-gmi`, pindahkan isinya ke root (jangan menimpa `docs/` dan `.superpowers/`), hapus `_scaffold`. Run: `php artisan --version`. Expected: `Laravel Framework 13.x`.
- [ ] **Step 4: Konfigurasi.** `.env`/`.env.example`: `APP_NAME`, MySQL `DB_DATABASE=iot_gmi` (buat DB lewat Laragon), `APP_TIMEZONE=Asia/Jakarta` (atau `config/app.php`), `ADMIN_EMAIL`, `ADMIN_PASSWORD` kosong di example. `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Jalankan `php artisan install:api --no-interaction`; baca dokumentasi Laravel 13 bila perintahnya berubah.
- [ ] **Step 4b: Verifikasi tes bawaan.** Run: `php artisan test`. Expected: PASS.
- [ ] **Step 5: Git.** `git init`, `git remote add origin git@github.com:faizrahmathidayat/sistem-pemantauan-suhu.git`. `.gitignore` tambah `.superpowers/`, `firmware/**/config.h`. Hentikan visual companion (`scripts/stop-server.sh`). Commit scaffold + dokumen spec + plan: `chore: scaffold laravel 13 and add design docs`.

### Task 2: Skema, enum, model, seeder

**Files:**
- Create: `app/Enums/{ProductType,IncidentReason,ZoneStatus}.php`, `app/Models/{Zone,SensorLog,Incident}.php`, tiga migration, `database/factories/{ZoneFactory,SensorLogFactory,IncidentFactory}.php`, `database/seeders/ZoneSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/SchemaTest.php`

**Interfaces:**
- Produces:
  - `enum ProductType: string { Telur='telur'; Buah='buah'; Sayur='sayur' }`
  - `enum IncidentReason: string { SuhuTinggi='suhu_tinggi'; SuhuRendah='suhu_rendah'; LembapTinggi='lembap_tinggi'; LembapRendah='lembap_rendah'; SensorOffline='sensor_offline' }` dengan `label(): string`, `advice(): string` (kalimat tindakan tetap), `isTemperature(): bool`, `isHumidity(): bool`
  - `enum ZoneStatus: string { Aman='aman'; Waspada='waspada'; Bahaya='bahaya'; Offline='offline'; TanpaData='tanpa_data' }` (`TanpaData` = belum ada log; keputusan implementasi)
  - `Zone` (fillable: name, product_type→ProductType, device_id, device_token_hash, temp_min, temp_max, humidity_min, humidity_max, warning_margin, humidity_warning_margin; hidden `device_token_hash`): `logs(): HasMany` (SensorLog via `device_id`), `latestLog(): HasOne` (terbaru berdasarkan id), `incidents(): HasMany`, `openIncidents(): HasMany` (`ended_at` null)
  - `SensorLog` (fillable: device_id, temperature, humidity; cast `decimal:2`; `MassPrunable` 90 hari)
  - `Incident` (cast reason→IncidentReason, started_at/ended_at/acknowledged_at datetime; `zone()`, `acknowledger(): BelongsTo<User>` nullable; `scopeOpen()`; `isOpen(): bool`)
  - Tabel sesuai bagian 4 spec; `incidents.acknowledged_by` null-on-delete; indeks `sensor_logs(device_id, created_at)`; unik `zones.device_id` dan `zones.device_token_hash` (nullable).

- [ ] **Step 1: Tulis tes `SchemaTest`**: `test_zone_latest_log_returns_newest_by_device_id`, `test_prunes_logs_older_than_90_days` (buat log 91 hari dan 89 hari; `(new SensorLog)->pruneAll()` menyisakan 1), `test_open_incidents_excludes_ended`, `test_seeder_creates_three_zones_with_placeholder_limits` (zona sayur: temp_max 25).
- [ ] **Step 2: Run** `php artisan test tests/Feature/SchemaTest.php`. Expected: FAIL.
- [ ] **Step 3: Implementasi** migration, enum, model, factory, seeder sesuai Interfaces. `ZoneSeeder` membuat tiga zona placeholder dengan `device_id` `gudang-zona-a|b|c` (tanpa token).
- [ ] **Step 4: Run** tes yang sama. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add schema, enums and models`.

### Task 3: ZoneStatusEvaluator

**Files:**
- Create: `app/Services/ZoneStatusEvaluator.php`
- Test: `tests/Unit/ZoneStatusEvaluatorTest.php`

**Interfaces:**
- Consumes: `Zone`, `ZoneStatus`, `IncidentReason` (Task 2).
- Produces (final class, tanpa state):
  - `evaluate(Zone $zone, float $temperature, float $humidity): ZoneStatus` → hanya `Aman|Waspada|Bahaya`
  - `dangerReasons(Zone $zone, float $temperature, float $humidity): array` → daftar `IncidentReason` (bisa lebih dari satu; kosong bila tak bahaya)
  - `forZone(Zone $zone, ?CarbonInterface $now = null): ZoneStatus` → `TanpaData` bila tak ada log; `Offline` bila log terakhir > 120 detik; selain itu `evaluate()` dari log terakhir.
  - `const OFFLINE_AFTER_SECONDS = 120`

- [ ] **Step 1: Tulis tes** (zona temp 15–25, margin 2; RH 80–95, margin 5; instansiasi `Zone` dengan `Zone::factory()->make([...])`): `test_exactly_at_max_is_waspada_not_bahaya` (25.0 → Waspada), `test_above_max_is_bahaya` (25.1 → Bahaya, reasons `[SuhuTinggi]`), `test_below_min_is_bahaya` (14.9), `test_waspada_band` (23.0 Waspada, 22.9 Aman, 15.0 Waspada), `test_humidity_out_of_range_is_bahaya_with_humidity_reason` (96 → `LembapTinggi`, 79 → `LembapRendah`), `test_null_humidity_limits_are_ignored` (humidity_min/max null, humidity 5 → tidak bahaya), `test_multiple_reasons` (suhu 26, RH 70 → dua reason), `test_for_zone_offline_and_tanpa_data` (tanpa log → TanpaData; log 121 detik lalu → Offline; 119 detik → status normal).
- [ ] **Step 2: Run** `php artisan test tests/Unit/ZoneStatusEvaluatorTest.php`. Expected: FAIL (class not found). Catatan: tes `forZone` butuh DB, taruh di `tests/Feature` bila `Unit` tidak memuat RefreshDatabase.
- [ ] **Step 3: Implementasi** sesuai Interfaces dan Global Constraints (perbandingan float dengan `round(...,2)`).
- [ ] **Step 4: Run** tes. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add zone status evaluator`.

### Task 4: Autentikasi perangkat dan endpoint telemetry

**Files:**
- Create: `app/Http/Controllers/Api/V1/SensorController.php`, `app/Http/Requests/StoreSensorLogRequest.php`, `app/Http/Middleware/AuthenticateDevice.php`, `app/Console/Commands/ZoneToken.php`
- Modify: `routes/api.php`, `bootstrap/app.php` (alias middleware `device`), `app/Providers/AppServiceProvider.php` (rate limiter `telemetry`)
- Test: `tests/Feature/TelemetryApiTest.php`, `tests/Feature/ZoneTokenCommandTest.php`

**Interfaces:**
- Consumes: `Zone`, `SensorLog`.
- Produces:
  - Route `POST /api/v1/telemetry`, nama `api.v1.telemetry`, middleware `['device', 'throttle:telemetry']`; `SensorController::__invoke(StoreSensorLogRequest $request): JsonResponse` → 201 `{"data":{"id":…}}`.
  - `AuthenticateDevice::handle(Request, Closure): Response` membaca bearer token, `hash('sha256', $token)`, mencari `Zone`, menyimpan di `$request->attributes->set('zone', $zone)`; 401 bila tak ada/`device_id` body tak cocok.
  - `StoreSensorLogRequest::rules()` per Global Constraints.
  - `php artisan zone:token {device_id}` mencetak token acak (`Str::random(48)`) sekali, menyimpan sha256-nya; `device_id` tak dikenal → exit code 1.
  - Rate limiter `telemetry`: 20 permintaan/menit per hash token.

- [ ] **Step 1: Tulis tes** `TelemetryApiTest`: `test_valid_payload_with_token_stores_log_and_returns_201`, `test_missing_token_returns_401`, `test_token_of_zone_a_with_device_id_of_zone_b_returns_401_and_stores_nothing`, `test_invalid_payloads_return_422_and_store_nothing` (dataset: humidity 150, temperature "abc", temperature −41, field `humidity` hilang, `device_id` kosong), `test_numeric_strings_are_accepted` ("18.2"). `ZoneTokenCommandTest`: `test_command_prints_token_once_and_authenticates`, `test_rerun_rotates_token_old_token_fails`, `test_unknown_device_id_fails`.
- [ ] **Step 2: Run** `php artisan test --filter=TelemetryApi`. Expected: FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Controller tipis: simpan `SensorLog` memakai `$request->safe()`; belum ada logika insiden (ditambah Task 5).
- [ ] **Step 4: Run** `php artisan test --filter="TelemetryApi|ZoneToken"`. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add device-authenticated telemetry endpoint`.

### Task 5: IncidentManager dan notifikasi

**Files:**
- Create: `app/Services/IncidentManager.php`, `app/Notifications/IncidentOpened.php`
- Modify: `app/Http/Controllers/Api/V1/SensorController.php`
- Test: `tests/Feature/IncidentLifecycleTest.php`

**Interfaces:**
- Consumes: `ZoneStatusEvaluator::dangerReasons`, `Incident`, `SensorLog`, `User`.
- Produces:
  - `IncidentManager::__construct(private ZoneStatusEvaluator $evaluator)`
  - `IncidentManager::handleReading(Zone $zone, SensorLog $log): void` — per `reason`: buka/tutup sesuai Global Constraints; juga menutup insiden `sensor_offline` terbuka; memperbarui `peak_value` (nilai terburuk: terbesar untuk *_tinggi, terkecil untuk *_rendah).
  - `IncidentManager::openOffline(Zone $zone): ?Incident` — null bila zona tanpa log atau sudah punya insiden offline terbuka; bila membuka, mengirim notifikasi.
  - `IncidentOpened::__construct(public readonly Incident $incident, public readonly ?SensorLog $log = null)`; `via(object $notifiable): array` → `['database']`; `toArray(object $notifiable): array` dengan kunci `incident_id, zone_id, zone_name, reason, title, temperature, humidity, limit, occurred_at, advice, url` (url = route `dashboard`).
  - `SensorController` memanggil `handleReading` setelah menyimpan log.

- [ ] **Step 1: Tulis tes** `IncidentLifecycleTest` (zona temp 15–25; kirim lewat `handleReading` dengan `SensorLog` berselisih 30 detik, `Carbon::setTestNow`; `Notification::fake()` kecuali tes isi): `test_single_danger_reading_does_not_open_incident`, `test_opens_incident_after_two_consecutive_danger_readings_and_notifies_all_users`, `test_gap_over_two_minutes_breaks_the_streak`, `test_no_extra_notification_while_incident_open`, `test_peak_value_updates_when_reading_is_worse`, `test_closes_after_two_non_danger_readings_and_waspada_counts_as_non_danger`, `test_closing_sends_no_notification`, `test_reopens_with_new_notification_after_closing`, `test_second_reason_opens_second_incident_and_notification` (suhu tinggi lalu RH rendah), `test_null_humidity_limits_never_open_humidity_incident`, `test_notification_payload_contains_zone_temperature_limit_and_advice` (jangan fake; baca `database` notification), `test_telemetry_endpoint_triggers_incident_end_to_end` (dua POST bahaya).
- [ ] **Step 2: Run** `php artisan test tests/Feature/IncidentLifecycleTest.php`. Expected: FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Streak: ambil 2 `SensorLog` terakhir `device_id` itu urut `id desc` (log baru termasuk); dua-duanya harus punya `reason` bahaya yang sama dan selisih `created_at` ≤ 120 detik untuk membuka; kebalikannya untuk menutup. Gunakan `DB::transaction` + `lockForUpdate` pada baris zona agar dua POST bersamaan tidak membuka insiden ganda.
- [ ] **Step 4: Run** tes. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add incident lifecycle and notifications`.

### Task 6: Deteksi sensor offline, scheduler, prune

**Files:**
- Create: `app/Console/Commands/CheckOfflineZones.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/OfflineDetectionTest.php`

**Interfaces:**
- Consumes: `IncidentManager::openOffline`, `ZoneStatusEvaluator::OFFLINE_AFTER_SECONDS`.
- Produces: `php artisan zones:check-offline`; mencatat `Cache::put('zones:last-offline-check', now())` tiap jalan. Jadwal: `zones:check-offline` `everyMinute()`, `model:prune` `daily()`.

- [ ] **Step 1: Tulis tes**: `test_zone_silent_for_over_two_minutes_gets_one_offline_incident_and_notification`, `test_running_twice_does_not_duplicate`, `test_zone_with_no_logs_is_never_offline`, `test_new_reading_closes_offline_incident_without_notification`, `test_command_records_last_check_time`, `test_schedule_registers_both_commands` (`php artisan schedule:list` via `Artisan::call`, string berisi `zones:check-offline` dan `model:prune`).
- [ ] **Step 2: Run** `php artisan test tests/Feature/OfflineDetectionTest.php`. Expected: FAIL.
- [ ] **Step 3: Implementasi** perintah dan jadwal.
- [ ] **Step 4: Run** tes. Expected: PASS.
- [ ] **Step 5: Commit** `feat: detect offline sensors via scheduler`.

### Task 7: Login dan manajemen user

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`, `app/Console/Commands/CreateUser.php`, `resources/views/auth/login.blade.php`, `resources/views/layouts/app.blade.php` (kerangka dasar, dilengkapi Task 9)
- Modify: `routes/web.php`, `database/seeders/DatabaseSeeder.php` (admin dari `ADMIN_EMAIL`/`ADMIN_PASSWORD`, dilewati bila kosong)
- Test: `tests/Feature/AuthTest.php`

**Interfaces:**
- Produces: route `login` (GET/POST), `logout` (POST), middleware `auth` pada semua halaman lain, guest diarahkan ke `login`; `php artisan user:create {name} {email} {--password=}` (tanpa `--password` meminta dengan `secret()`); tidak ada route register. Selesai login → route `dashboard`.

- [ ] **Step 1: Tulis tes**: `test_guest_is_redirected_to_login_from_dashboard`, `test_valid_login_redirects_to_dashboard`, `test_invalid_login_shows_error`, `test_register_route_does_not_exist` (GET `/register` → 404), `test_user_create_command_creates_hashed_user`, `test_seeder_creates_admin_from_env`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AuthTest.php`. Expected: FAIL.
- [ ] **Step 3: Implementasi** login Blade buatan sendiri (tanpa paket starter kit). Route `dashboard` boleh berupa placeholder sampai Task 8.
- [ ] **Step 4: Run** tes. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add login and user management commands`.

### Task 8: Endpoint dashboard, grafik, acknowledge

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`, `DashboardDataController.php`, `ZoneReadingsController.php`, `IncidentAcknowledgeController.php` (semua invokable atau `index` tunggal)
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardDataTest.php`, `tests/Feature/AcknowledgeTest.php`

**Interfaces:**
- Consumes: `ZoneStatusEvaluator::forZone`, `Zone`, `Incident`.
- Produces:
  - `GET /dashboard` (route `dashboard`, view `dashboard`).
  - `GET /dashboard/data` (route `dashboard.data`) → JSON: `{ zones: [{id, name, product_type, status, temperature, humidity, temp_min, temp_max, humidity_min, humidity_max, trend ('naik'|'turun'|null), last_seen_seconds}], banners: [{incident_id, zone_name, kind ('kritis'|'offline'), message, started_at, acknowledged_by (nama|null)}], unread_notifications: int, scheduler_stale: bool }`. Urutan zona: bahaya, offline, waspada, aman, tanpa_data. `scheduler_stale` true bila `zones:last-offline-check` tak ada atau > 5 menit. `trend` = 'naik'/'turun' bila suhu di luar batas (di atas max / di bawah min), selain itu null.
  - `GET /zones/{zone}/readings?hours=24` (route `zones.readings`) → `{labels: [ISO], temperature: [..], humidity: [..], temp_min, temp_max}` dengan rata-rata per bucket 5 menit; `hours` dibatasi 1..72.
  - `POST /incidents/{incident}/acknowledge` (route `incidents.acknowledge`): mengisi `acknowledged_by/at` hanya bila belum terisi; pada insiden sudah tertutup atau sudah di-acknowledge tetap redirect/JSON sukses tanpa mengubah pengklik pertama.

- [ ] **Step 1: Tulis tes**: `test_data_orders_zones_by_severity`, `test_data_with_zero_zones_returns_empty_lists`, `test_zone_without_logs_has_status_tanpa_data_not_offline`, `test_open_danger_incident_produces_kritis_banner`, `test_open_offline_incident_produces_offline_banner`, `test_banner_shows_acknowledger_name_after_ack`, `test_scheduler_stale_flag`, `test_readings_bucket_into_five_minute_averages_and_clamp_hours`, `test_guest_cannot_access_data_endpoints` (302/401). `AcknowledgeTest`: `test_acknowledge_records_user_and_time`, `test_second_acknowledge_keeps_first_user`, `test_acknowledge_closed_incident_is_a_noop_without_error`, `test_acknowledge_does_not_close_incident`.
- [ ] **Step 2: Run** `php artisan test --filter="DashboardData|Acknowledge"`. Expected: FAIL.
- [ ] **Step 3: Implementasi** sesuai kontrak JSON. Hindari N+1 (eager load `latestLog`, `openIncidents`).
- [ ] **Step 4: Run** tes. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add dashboard data, readings and acknowledge endpoints`.

### Task 9: Tampilan dashboard

**Files:**
- Create: `resources/views/dashboard.blade.php`, `resources/js/dashboard.js`
- Modify: `resources/views/layouts/app.blade.php`, `resources/css/app.css`, `package.json` (`chart.js`), `vite.config.js` bila perlu
- Test: `tests/Feature/DashboardViewTest.php`

**Interfaces:**
- Consumes: kontrak JSON Task 8. Mockup acuan: `.superpowers/brainstorm/2295-1794197799/content/dashboard-layout.html`.
- Produces: halaman `dashboard` dengan: banner sticky di atas (merah `kritis`, abu-oranye `offline`, teks "Sedang ditangani oleh {nama}" setelah ack, tombol selebar layar di mobile yang POST ke `incidents.acknowledge`); kartu per zona (badge status, suhu besar, `(Max: …)`, panah naik/turun, kelembapan, "update X detik lalu", badge BAHAYA berdenyut); grafik Chart.js 24 jam dengan chip filter zona/metrik yang bisa digeser horizontal di mobile; polling `dashboard.data` tiap 15 detik; peringatan kecil bila `scheduler_stale`; susunan kartu vertikal di mobile dengan urutan dari server.

- [ ] **Step 1: Tulis tes** `DashboardViewTest`: `test_dashboard_renders_for_logged_in_user_with_no_zones`, `test_dashboard_renders_zone_names_and_status_labels`, `test_dashboard_page_contains_data_and_acknowledge_endpoints` (assertSee URL `dashboard/data`), `test_dashboard_includes_vite_assets`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/DashboardViewTest.php`. Expected: FAIL.
- [ ] **Step 3: Implementasi** Blade + Tailwind + `dashboard.js`; `npm install chart.js`; `npm run build`.
- [ ] **Step 4: Run** tes dan `npm run build`. Expected: PASS dan build sukses.
- [ ] **Step 5: Verifikasi visual.** Jalankan `php artisan serve` + seed data contoh, buka di browser pane lebar desktop dan 375px. Periksa: banner sticky, urutan zona bahaya di atas, tombol selebar layar, tidak ada scroll horizontal. Laporkan jujur bila ada yang belum cocok dengan mockup.
- [ ] **Step 6: Commit** `feat: add monitoring dashboard ui`.

### Task 10: Lonceng notifikasi dan riwayat insiden

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`, `IncidentController.php`, `resources/views/notifications/index.blade.php`, `resources/views/incidents/index.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php` (lonceng + jumlah belum dibaca)
- Test: `tests/Feature/NotificationPageTest.php`, `tests/Feature/IncidentLogTest.php`

**Interfaces:**
- Produces: `GET /notifications` (route `notifications.index`), `POST /notifications/{id}/read` (`notifications.read`), `POST /notifications/read-all` (`notifications.read-all`), `GET /incidents` (`incidents.index`, paginasi 15, terbaru dulu; kolom zona, mulai, selesai ("berlangsung" bila null), durasi, puncak, status penanganan; di mobile berupa daftar kartu: zona + durasi di atas, status di bawah).

- [ ] **Step 1: Tulis tes**: `test_user_sees_only_own_notifications_and_unread_count`, `test_mark_as_read_and_read_all`, `test_cannot_mark_another_users_notification`, `test_incident_log_lists_newest_first_with_duration_and_acknowledger`, `test_open_incident_shows_berlangsung`, `test_incident_log_paginates_at_15`.
- [ ] **Step 2: Run** `php artisan test --filter="NotificationPage|IncidentLog"`. Expected: FAIL.
- [ ] **Step 3: Implementasi** controller, view, dan lonceng (jumlah dari `auth()->user()->unreadNotifications()->count()`; ikut ter-update lewat `unread_notifications` pada polling).
- [ ] **Step 4: Run** tes. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add notification center and incident log`.

### Task 11: Firmware ESP32

**Files:**
- Create: `firmware/esp32-telemetry/esp32-telemetry.ino`, `config.example.h`, `SensorReader.h/.cpp`, `WifiConnection.h/.cpp`, `TelemetryClient.h/.cpp`, `firmware/esp32-telemetry/README.md`

**Interfaces:**
- Produces: `config.example.h` dengan `WIFI_SSID`, `WIFI_PASSWORD`, `API_URL` (mis. `http://192.168.1.10:8000/api/v1/telemetry`), `DEVICE_ID`, `DEVICE_TOKEN`, `DHT_PIN` (4), `DHT_TYPE` (DHT22), `SEND_INTERVAL_MS` (30000), `HTTP_TIMEOUT_MS` (5000). `struct Reading { float temperature; float humidity; bool valid; }`; `Reading SensorReader::read()`; `bool WifiConnection::ensureConnected(uint32_t timeoutMs)`; `bool TelemetryClient::send(const Reading&)` (POST JSON `{device_id,temperature,humidity}` dengan header `Authorization: Bearer …` dan `Content-Type: application/json`, true bila HTTP 201).

- [ ] **Step 1: Tulis** sketch dan modul sesuai Interfaces. Loop berbasis `millis()`; pembacaan `NaN` tidak dikirim; kegagalan Wi-Fi/HTTP hanya dicatat ke Serial lalu coba lagi pada siklus berikutnya; tanpa antrean offline; HTTP LAN saja.
- [ ] **Step 2: Verifikasi** kompilasi: bila `arduino-cli` tersedia, `arduino-cli compile --fqbn esp32:esp32:esp32 firmware/esp32-telemetry` (dengan `config.h` disalin dari example). Bila tidak tersedia, katakan jelas bahwa firmware belum dikompilasi dan masukkan ke README langkah kompilasi di Arduino IDE (board "ESP32 Dev Module", library Adafruit DHT, ArduinoJson).
- [ ] **Step 3: Uji kontrak** dari komputer dengan `curl` ke server lokal (token dari `zone:token`): payload sama persis dengan yang dikirim firmware (201), dan tanpa token (401).
- [ ] **Step 4: Commit** `feat: add esp32 telemetry firmware`.

### Task 12: README dan verifikasi menyeluruh

**Files:**
- Create: `README.md`
- Modify: `.env.example`

- [ ] **Step 1: Tulis README** (Indonesia): kebutuhan (PHP 8.4, Composer, Node, MySQL), instalasi (`composer install`, `.env`, `php artisan key:generate`, `migrate --seed`, `npm ci && npm run build`), membuat user, membuat token per zona (`zone:token`), menjalankan `php artisan serve` **dan** `php artisan schedule:work` (wajib, jelaskan alasan), memasang ESP32, uji dengan `curl`, menambah saluran WhatsApp nanti (tambah di `IncidentOpened::via()`), batasan versi 1 (tanpa HTTPS, tanpa layar admin).
- [ ] **Step 2: Jalankan seluruh tes.** Run: `php artisan test`. Expected: semua PASS.
- [ ] **Step 3: Uji dari ujung ke ujung** secara lokal: `migrate:fresh --seed`, buat user dan token, kirim 2 `curl` bahaya untuk satu zona → insiden terbuka, notifikasi muncul di lonceng dan banner merah di dashboard; kirim 2 `curl` normal → insiden tertutup; hentikan pengiriman lebih dari 2 menit dengan `schedule:work` berjalan → insiden offline dan notifikasi. Catat hasil nyata, termasuk yang gagal.
- [ ] **Step 4: Commit** `docs: add readme`. Tanya pemilik sebelum `git push -u origin main`.

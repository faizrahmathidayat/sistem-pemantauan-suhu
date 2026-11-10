# Monitor Publik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman layar penuh tanpa login untuk TV gudang (`/monitor/{kode-rahasia}`) dengan pita status keseluruhan dan kartu zona gelap, plus pengelolaan alamat rahasia oleh admin.

**Architecture:** Kolom `monitor_token_hash` di `warehouses` (hash sha256, tampil sekali). `MonitorSnapshot` menghitung JSON dari model dan `ZoneStatusEvaluator` yang sudah ada. Dua rute publik (halaman dan data) di luar middleware `auth`, dengan throttle dan header anti-cache/anti-indeks. `resources/js/monitor.js` melakukan polling 10 detik dan menggambar ulang. Admin mengelola alamat dari halaman ubah gudang; tiap aksi masuk Log Perubahan.

**Tech Stack:** Laravel 13, PHP 8.4, MySQL 8 / SQLite (tes), PHPUnit 12, Blade + Tailwind 4 via Vite.

**Spec:** `docs/superpowers/specs/2026-11-10-monitor-publik-design.md`

## Global Constraints

- Antarmuka berbahasa Indonesia; zona waktu `config('app.timezone')`.
- Kode monitor: `Str::random(40)`, disimpan hanya sebagai `hash('sha256', $token)` di `warehouses.monitor_token_hash` (string 64, nullable, unik); atribut ini hidden dan tidak boleh muncul di JSON, di catatan audit, atau di halaman setelah sekali tampil.
- Rute publik tidak memakai `auth`, tidak mengirim cookie sesi/CSRF yang dibutuhkan, dan tidak menampilkan produk, id insiden, nama pengguna, tombol aksi, atau tautan keluar.
- Respons halaman dan data: `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`. Throttle per IP: halaman `throttle:60,1`, data `throttle:300,1`.
- Token tidak dikenal/dicabut: 404 (halaman dan data).
- Urutan keparahan status: Bahaya > Offline > Waspada > Aman > Tanpa data (pakai `ZoneStatus::severity()`).
- Setelah mengubah JS atau kelas Tailwind: `npm run build`. Tiap perintah PHP memakai PHP 8.4 Laragon (`C:\laragon\bin\php\php-8.4.27-Win32-vs17-x64` di `PATH`).
- Komit diakhiri `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Jangan push tanpa persetujuan user.

## Review Focus

- Kode lama tidak berlaku setelah dibuat ulang atau dimatikan; kode kosong, `0`, atau yang berbeda satu karakter tidak pernah cocok (Task 1, 3).
- Monitor gudang A tidak pernah memuat zona atau angka gudang B (Task 2).
- Zona offline, belum ada data, atau berbatas kelembapan NULL tidak membuat halaman error dan tidak membuat pita menyatakan "AMAN" (Task 2, 4).
- Koneksi putus atau server mati: halaman menampilkan pita "TIDAK TERHUBUNG" dan meredupkan kartu, bukan membiarkan angka lama tampak terkini (Task 4).
- Kode tampil sekali: tidak muncul lagi setelah muat ulang halaman ubah gudang, tidak ada di cache browser (Task 5).

---

### Task 1: Kode monitor di model Warehouse

**Files:**
- Create: `database/migrations/2026_11_10_110001_add_monitor_token_to_warehouses.php`
- Modify: `app/Models/Warehouse.php`
- Test: `tests/Feature/WarehouseMonitorTokenTest.php`

**Interfaces:**
- Produces: `Warehouse::issueMonitorToken(): string`, `Warehouse::revokeMonitorToken(): void`, `Warehouse::hasMonitorToken(): bool`, `static Warehouse::findByMonitorToken(?string $token): ?Warehouse`.

- [ ] **Step 1: Tulis tes (RED)** di `WarehouseMonitorTokenTest` (RefreshDatabase):
  - `test_issue_returns_a_40_char_token_and_stores_only_its_hash`: `$token = $w->issueMonitorToken()`; `strlen($token) === 40`; `$w->fresh()->monitor_token_hash === hash('sha256', $token)`; `assertStringNotContainsString($token, json_encode(DB::table('warehouses')->first()))`.
  - `test_find_by_token_matches_only_the_exact_token`: token benar menemukan gudangnya; `findByMonitorToken(null)`, `''`, `'0'`, dan token dengan satu karakter diubah → `null`.
  - `test_reissuing_invalidates_the_previous_token`: dua kali `issueMonitorToken()`; token pertama → `null`, kedua → gudang.
  - `test_revoke_clears_the_token_and_find_returns_null`; `hasMonitorToken()` false setelahnya, true sebelumnya.
  - `test_hash_is_hidden_from_serialisation`: `array_key_exists('monitor_token_hash', $w->toArray())` false.
  - `test_two_warehouses_never_share_a_token`: dua gudang diberi kode; masing-masing hanya menemukan dirinya.
- [ ] **Step 2: Jalankan, pastikan gagal**
  Run: `php artisan test --filter=WarehouseMonitorTokenTest` Expected: FAIL (method/kolom tidak ada).
- [ ] **Step 3: Migrasi** tambah `monitor_token_hash` `string(64)` nullable `unique()` setelah `name`; `down()` menjatuhkan indeks dan kolom. Hanya `Schema`, tanpa model.
- [ ] **Step 4: Implementasi di `Warehouse`**: atribut `#[Hidden(['monitor_token_hash'])]` (pola `Zone`); `issueMonitorToken()` memakai `Str::random(40)` + `forceFill([...])->save()`; `revokeMonitorToken()` mengosongkan; `findByMonitorToken` mengembalikan `null` untuk null/string kosong, selain itu `where('monitor_token_hash', hash('sha256', $token))->first()`. Jangan masukkan kolom ke `$fillable`.
- [ ] **Step 5: Jalankan, pastikan lulus**
  Run: `php artisan test --filter=WarehouseMonitorTokenTest` Expected: PASS.
- [ ] **Step 6: Komit** `feat(monitor): kode rahasia monitor per gudang`.

---

### Task 2: `MonitorSnapshot`

**Files:**
- Create: `app/Services/MonitorSnapshot.php`
- Test: `tests/Feature/MonitorSnapshotTest.php`

**Interfaces:**
- Consumes: `ZoneStatusEvaluator::forZone(Zone, ?CarbonInterface): ZoneStatus`, `::dangerReasons(Zone, float, float): list<IncidentReason>`, `ZoneStatus::label()/severity()`, `IncidentReason::label()/unit()/limitFor()/isTemperature()`.
- Produces: `final class MonitorSnapshot { public function __construct(ZoneStatusEvaluator $evaluator); /** @return array{warehouse: string, server_time: string, timezone: string, overall: array{level: string, headline: string, detail: string}, zones: list<array<string, mixed>>} */ public function build(Warehouse $warehouse, ?CarbonInterface $now = null): array }`. Bentuk JSON persis seperti bagian "Data JSON" di spec; kunci zona: `name, status, status_label, temperature, humidity, temp_min, temp_max, humidity_min, humidity_max, last_seen_seconds`.

- [ ] **Step 1: Tulis tes (RED)** di `MonitorSnapshotTest` (RefreshDatabase; buat zona dengan `SensorLog` memakai `device_id` zona dan `created_at` terkontrol):
  - `test_overall_level_follows_the_worst_zone` dengan `#[DataProvider]`: (semua aman → `aman`/"SEMUA ZONA AMAN"), (satu bahaya + satu aman → `bahaya`/"PERHATIAN: 1 ZONA DI LUAR BATAS"), (dua bahaya → "PERHATIAN: 2 ZONA DI LUAR BATAS"), (offline + waspada → `offline`/"SENSOR OFFLINE: 1 ZONA"), (waspada + aman → `waspada`/"WASPADA: 1 ZONA MENDEKATI BATAS"), (bahaya + offline → `bahaya`), (tanpa zona → `tanpa_data`/"BELUM ADA DATA"), (semua zona belum pernah mengirim → `tanpa_data`), (aman + belum ada data → `aman`).
  - `test_danger_detail_names_the_zone_reason_value_and_limit`: zona maks 20 dengan suhu 21.4 → detail memuat `Zona Loading: Suhu terlalu tinggi 21.4°C (batas 20°C)`; dua alasan pada satu zona (suhu dan lembap) keduanya muncul.
  - `test_zones_are_sorted_by_severity_then_name`.
  - `test_zone_without_humidity_limits_has_null_humidity_limits_and_does_not_break`.
  - `test_offline_zone_keeps_its_last_values_and_reports_last_seen_seconds`; zona tanpa log: `temperature`/`humidity`/`last_seen_seconds` `null`, status `tanpa_data`.
  - `test_only_the_given_warehouses_zones_are_included`: zona gudang lain tidak muncul di `zones`, `detail`, atau `headline`.
  - `test_snapshot_never_exposes_products_or_ids`: `json_encode` hasil tidak memuat nama produk zona dan tidak ada kunci `id`/`products`/`incident*`.
  - `test_timezone_and_server_time`: `timezone` = `config('app.timezone')`; `server_time` format ISO 8601 dari `$now`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
  Run: `php artisan test --filter=MonitorSnapshotTest` Expected: FAIL (kelas tidak ada).
- [ ] **Step 3: Implementasi `MonitorSnapshot::build`.** Muat zona gudang (`$warehouse->zones()->with('latestLog')`); status lewat `forZone($zone, $now)`; urut `severity` lalu nama. Tingkat pita dari status terburuk di antara zona yang bukan `TanpaData` (bila tak ada → `tanpa_data`); judul dan rincian sesuai tabel di spec. Nilai untuk alasan bahaya: suhu bila `isTemperature()`, kelembapan bila tidak; format angka satu desimal tanpa nol di belakang (pola `DashboardSnapshot::number`). Angka sensor di `zones` dikirim apa adanya (float).
- [ ] **Step 4: Jalankan, pastikan lulus.**
  Run: `php artisan test --filter=MonitorSnapshotTest` Expected: PASS.
- [ ] **Step 5: Komit** `feat(monitor): ringkasan data monitor per gudang`.

---

### Task 3: Endpoint data publik

**Files:**
- Create: `app/Http/Controllers/MonitorDataController.php`, `app/Http/Middleware/MonitorResponseHeaders.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/MonitorDataTest.php`

**Interfaces:**
- Consumes: `Warehouse::findByMonitorToken`, `MonitorSnapshot::build`.
- Produces: rute `monitor.data` (`GET /monitor/{token}/data`); middleware `MonitorResponseHeaders` (menambahkan tiga header spec) dipakai juga oleh Task 4.

- [ ] **Step 1: Tulis tes (RED)** di `MonitorDataTest` (RefreshDatabase):
  - `test_valid_token_returns_snapshot_without_login`: `getJson("/monitor/{$token}/data")` 200, `assertJsonPath('warehouse', ...)`, `assertJsonStructure(['warehouse','server_time','timezone','overall' => ['level','headline','detail'],'zones'])`.
  - `test_unknown_malformed_or_empty_tokens_return_404` (provider: acak 40 karakter, `abc`, `0`, token gudang lain dengan huruf diubah).
  - `test_old_token_is_404_after_reissue_or_revoke`.
  - `test_response_headers_forbid_caching_indexing_and_referrer`: ketiga header benar, juga pada respons 404.
  - `test_routes_are_throttled_and_not_behind_auth`: `Route::getRoutes()->getByName('monitor.data')->gatherMiddleware()` memuat `throttle:300,1` dan tidak memuat `auth`.
  - `test_logged_in_user_gets_the_same_result`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
  Run: `php artisan test --filter=MonitorDataTest` Expected: FAIL (404 untuk rute yang belum ada / kelas tidak ada).
- [ ] **Step 3: Implementasi.** `MonitorResponseHeaders::handle` menambah header ke respons apa pun, termasuk 404 (pasang di grup rute, gunakan `$next($request)` lalu header pada hasilnya; untuk 404 dari `abort` pastikan header tetap terpasang). Rute di `routes/web.php` di luar grup `auth` dan `guest`: `Route::middleware([MonitorResponseHeaders::class])->prefix('monitor')->group(...)` dengan `get('{token}/data', MonitorDataController::class)->middleware('throttle:300,1')->name('monitor.data')`. Controller: `$warehouse = Warehouse::findByMonitorToken($token) ?? abort(404)`; `response()->json($snapshot->build($warehouse))`.
- [ ] **Step 4: Jalankan, pastikan lulus.**
  Run: `php artisan test --filter=MonitorDataTest` Expected: PASS.
- [ ] **Step 5: Komit** `feat(monitor): endpoint data publik berkode rahasia`.

---

### Task 4: Halaman monitor (Blade + JS)

**Files:**
- Create: `app/Http/Controllers/MonitorController.php`, `resources/views/monitor/show.blade.php`, `resources/js/monitor.js`
- Modify: `routes/web.php`, `vite.config.js` (tambah `resources/js/monitor.js` ke `input`)
- Test: `tests/Feature/MonitorPageTest.php`

**Interfaces:**
- Consumes: `MonitorSnapshot::build`, `MonitorResponseHeaders`, rute `monitor.data`.
- Produces: rute `monitor.show` (`GET /monitor/{token}`, `throttle:60,1`).

- [ ] **Step 1: Tulis tes (RED)** di `MonitorPageTest` (RefreshDatabase):
  - `test_page_renders_without_login_with_the_first_snapshot_embedded`: 200; memuat nama gudang di `<title>`, `<meta name="robots" content="noindex, nofollow">`, tag `<script type="application/json" id="monitor-initial">` berisi JSON snapshot valid, `data-url` ke `route('monitor.data', $token)`.
  - `test_page_is_a_standalone_document`: tidak memuat `csrf-token`, `href="/login"`, form, tombol, atau sidebar (`id="sidebar"`); memuat aset `monitor.js` lewat Vite.
  - `test_unknown_token_is_404`; `test_headers_forbid_caching_indexing_and_referrer`; `test_route_is_throttled_60_per_minute_and_not_behind_auth`.
  - `test_page_does_not_leak_other_warehouses_or_products`: nama zona gudang lain dan nama produk tidak ada di HTML.
  - `test_script_contract`: file `resources/js/monitor.js` memuat `setInterval`, konstanta `STALE_AFTER_MS = 45_000`, teks `TIDAK TERHUBUNG KE SERVER`, teks `Alamat monitor ini tidak berlaku lagi`, dan `location.reload` (muat ulang berkala); `vite.config.js` memuat `resources/js/monitor.js`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
  Run: `php artisan test --filter=MonitorPageTest` Expected: FAIL.
- [ ] **Step 3: Controller dan rute.** `MonitorController::__invoke(string $token, MonitorSnapshot $snapshot): View` (404 untuk token tak dikenal) mengirim `snapshot` dan `dataUrl`; rute `get('{token}', MonitorController::class)->middleware('throttle:60,1')->name('monitor.show')` dalam grup yang sama dengan Task 3.
- [ ] **Step 4: View `monitor/show.blade.php`** dokumen mandiri (bukan `layouts.app`): `lang="id"`, viewport, meta robots, `<title>{{ $snapshot['warehouse'] }} · Monitor</title>`, `@vite(['resources/css/app.css', 'resources/js/monitor.js'])`, latar gelap penuh layar tanpa scroll. Kerangka: header (nama gudang, jam), pita status `#monitor-banner`, grid kartu `#monitor-zones` (`grid-template-columns: repeat(auto-fit, minmax(min(100%, 26rem), 1fr))`), baris bawah (`#monitor-updated`), pita koneksi `#monitor-offline` (tersembunyi). Snapshot awal dalam `<script type="application/json" id="monitor-initial">{!! json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>` dan `data-url` pada elemen akar. Ukuran huruf memakai `clamp()`/`vw` agar pas di 1080p dan 4K.
- [ ] **Step 5: `monitor.js`.** Render pertama dari `#monitor-initial`; `setInterval` 10 detik mengambil `data-url` (`fetch`, `cache: 'no-store'`, tanpa kredensial). Pita tingkat `aman` hijau, `waspada` kuning, `offline` abu-abu tua, `bahaya` merah berdenyut pelan, `tanpa_data` abu-abu. Kartu: tepi atas berwarna status, nama, suhu besar, kelembapan, "Batas ≤ {max}°C · RH {min}–{max}%" (tanpa RH bila batas kosong), status, "diperbarui n dtk lalu" dihitung ulang tiap detik dari `last_seen_seconds` + waktu lokal sejak respons diterima; zona offline diredupkan dengan nilai terakhir; zona tanpa data menampilkan "–". Semua teks dari server melewati fungsi `esc()` sebelum dimasukkan ke HTML. Jam dari `server_time` + selisih waktu lokal dengan `Intl.DateTimeFormat('id-ID', { timeZone: data.timezone, ... })`. `STALE_AFTER_MS = 45_000`: bila belum ada respons sukses selama itu, tampilkan `#monitor-offline` "TIDAK TERHUBUNG KE SERVER · data terakhir HH:MM:SS" dan redupkan grid. Respons 404 dari data: ganti isi layar dengan "Alamat monitor ini tidak berlaku lagi. Minta alamat baru kepada admin." dan hentikan polling. `setTimeout(() => location.reload(), 6 * 60 * 60 * 1000)`.
- [ ] **Step 6: Build dan jalankan tes.**
  Run: `npm run build` lalu `php artisan test --filter=MonitorPageTest` Expected: build sukses; PASS.
- [ ] **Step 7: Verifikasi tampilan di browser pane** (bukan tes otomatis): buat kode untuk satu gudang lewat skrip sementara di scratchpad, buka `/monitor/{kode}` pada 1920×1080, periksa keadaan aman, bahaya (ubah batas sementara), offline, dan server dimatikan (pita koneksi); hapus data sementara setelahnya.
- [ ] **Step 8: Komit** `feat(monitor): halaman monitor layar penuh untuk TV`.

---

### Task 5: Pengelolaan alamat oleh admin

**Files:**
- Create: `app/Http/Controllers/WarehouseMonitorController.php`
- Modify: `routes/web.php`, `app/Http/Controllers/WarehouseController.php` (`edit`), `resources/views/warehouses/edit.blade.php`, `app/Models/AuditLog.php`
- Test: `tests/Feature/WarehouseMonitorAdminTest.php`

**Interfaces:**
- Consumes: `Warehouse::issueMonitorToken/revokeMonitorToken/hasMonitorToken`, `AuditLogger::record`.
- Produces: rute `warehouses.monitor.store` (`POST /warehouses/{warehouse}/monitor`) dan `warehouses.monitor.destroy` (`DELETE /warehouses/{warehouse}/monitor`), keduanya di grup `auth` + `can:warehouse.manage`; event audit `warehouse.monitor_issued` ("Membuat alamat monitor") dan `warehouse.monitor_revoked` ("Mematikan alamat monitor"); kolom diff `monitor` (label "Alamat monitor").

- [ ] **Step 1: Tulis tes (RED)** di `WarehouseMonitorAdminTest` (RefreshDatabase; aktor `User::factory()->withPermissions(Permission::WarehouseManage)->assignedTo($w)`):
  - `test_store_issues_a_token_and_shows_the_full_url_once`: POST → redirect ke `warehouses.edit`; halaman tujuan memuat `route('monitor.show', $token)` utuh (ambil dari flash/isi halaman); muat ulang halaman ubah gudang → alamat tidak muncul lagi.
  - `test_edit_page_with_the_new_url_is_not_cacheable`: respons memuat `Cache-Control` berisi `no-store`.
  - `test_url_works_and_old_one_stops_after_reissue`: URL dari langkah pertama membuka monitor 200; setelah POST kedua, URL lama 404 dan baru 200.
  - `test_destroy_revokes_and_second_destroy_is_a_harmless_noop`: setelah DELETE, URL 404; DELETE kedua tidak error dan tidak menambah catatan audit.
  - `test_actions_are_recorded_in_the_audit_log`: `warehouse.monitor_issued` dan `warehouse.monitor_revoked` dengan `subject_label` nama gudang, diff memuat kunci `monitor`; `json_encode` diff tidak memuat token ataupun hash.
  - `test_requires_warehouse_manage_and_access_to_that_warehouse`: tanpa izin 403; punya izin tetapi tidak di-assign ke gudang itu 403 (POST dan DELETE); Administrator boleh.
  - `test_guests_are_redirected_to_login`.
  - `test_edit_page_shows_status_and_the_right_buttons`: belum ada kode → "Buat alamat monitor"; sudah ada → "Buat ulang alamat" dan "Matikan alamat"; hash tidak pernah dicetak.
- [ ] **Step 2: Jalankan, pastikan gagal.**
  Run: `php artisan test --filter=WarehouseMonitorAdminTest` Expected: FAIL.
- [ ] **Step 3: Implementasi.** Rute di grup `auth` bersama resource gudang (`can:warehouse.manage`). `WarehouseMonitorController::store`: `abort_unless($request->user()->canAccessWarehouse($warehouse), 403)`; dalam transaksi `issueMonitorToken()` + `AuditLogger::record(... 'warehouse.monitor_issued', $warehouse, $warehouse->name, ['monitor' => ['from' => $had ? 'aktif' : null, 'to' => 'aktif (alamat baru)']])`; redirect ke `warehouses.edit` dengan `with('new_monitor_url', route('monitor.show', $token))`. `destroy`: bila belum ada kode, redirect dengan pesan; bila ada, `revokeMonitorToken()` + audit (`from 'aktif'`, `to 'dimatikan'`). `AuditLog::EVENTS` dan `FIELDS` ditambah. `WarehouseController::edit`: bila sesi punya `new_monitor_url`, kembalikan respons dengan header `Cache-Control: no-store` (pola `ZoneManagementController`). View: kartu "Layar monitor" di bawah kartu zona, memakai komponen `x-card`/`x-flash`/kelas tombol yang ada; kotak peringatan sekali-tampil meniru `zones/manage.blade.php` (alamat dalam `select-all`, tombol Salin berbasis Alpine `navigator.clipboard` dengan penanganan gagal); tombol "Matikan alamat" memakai `x-danger` gaya yang sama dengan hapus yang sudah ada (konfirmasi `onsubmit confirm`).
- [ ] **Step 4: Jalankan, pastikan lulus.**
  Run: `php artisan test --filter="WarehouseMonitorAdminTest|AuditLogTest|WarehouseManagementTest"` Expected: PASS.
- [ ] **Step 5: Komit** `feat(monitor): admin membuat, mengganti, dan mematikan alamat monitor`.

---

### Task 6: Dokumentasi dan verifikasi akhir

**Files:**
- Modify: `README.md`, `docs/LAPORAN-PENGERJAAN.md`

- [ ] **Step 1: README**: bagian "Monitor publik" (apa itu, cara membuat alamat di halaman ubah gudang, memasang di TV dengan browser layar penuh/kiosk, cara mengganti atau mematikan, perilaku koneksi putus, catatan bahwa kode hanya tampil sekali).
- [ ] **Step 2: LAPORAN-PENGERJAAN**: bagian baru ringkas (fitur, keputusan yang diambil, jumlah tes, hal yang belum).
- [ ] **Step 3: Jalankan seluruh tes dan Pint.**
  Run: `php artisan test` Expected: semua lulus (kecuali satu yang memang dilewati di SQLite). Run: `vendor/bin/pint --test` Expected: satu-satunya kegagalan adalah `tests/Feature/IncidentLifecycleTest.php` (sudah ada sebelumnya).
- [ ] **Step 4: Komit** `docs(monitor): dokumentasi monitor publik`.

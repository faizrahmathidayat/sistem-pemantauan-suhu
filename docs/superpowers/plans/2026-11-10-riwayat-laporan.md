# Riwayat & Laporan Sensor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman **Laporan** (`/reports`): filter gudang/zona/periode, ringkasan per zona, grafik min/rata-rata/maks, daftar insiden, ekspor CSV, dan tampilan cetak.

**Architecture:** Izin baru `report.view`. Penentuan filter (`ReportFilters`) dan periode (`ReportPeriod`) dipisah dari perhitungan (`ReportService`, agregasi di database). Satu `ReportController` melayani halaman dan dua ekspor CSV; grafik di-render dari JSON yang disisipkan di halaman (`resources/js/reports.js`).

**Tech Stack:** Laravel 13, PHP 8.4, MySQL 8 (produksi) / SQLite (tes), PHPUnit 12 (`#[DataProvider]`), Blade + Tailwind 4 + Chart.js via Vite.

**Spec:** `docs/superpowers/specs/2026-11-10-riwayat-laporan-design.md`

## Global Constraints

- Bahasa antarmuka Indonesia. Zona waktu aplikasi `Asia/Jakarta` (`config('app.timezone')`); waktu di CSV `Y-m-d H:i:s`.
- Retensi data `SensorLog::RETENTION_DAYS` = 90; periode maksimal 90 hari dan tidak boleh mulai lebih dari 90 hari lalu.
- Izin `report.view` (label "Melihat dan mengunduh laporan sensor", kelompok "Laporan") melindungi halaman dan kedua ekspor.
- Akses gudang/zona memakai `Warehouse::visibleTo`, `Zone::visibleTo`, `User::canAccessZone`; Administrator melihat semua. Gudang/zona yang ada tetapi di luar jangkauan: 403; id tidak ada: 404.
- Agregasi dilakukan di database; ekspresi bucket tidak memakai `UNIX_TIMESTAMP` (bergantung zona waktu sesi). MySQL: `TIMESTAMPDIFF(SECOND, '2000-01-01 00:00:00', created_at) DIV ?`; SQLite: `(CAST(strftime('%s', created_at) AS INTEGER) - 946684800) / ?`.
- Sel CSV teks yang diawali `=`, `+`, `-`, `@`, tab, atau CR diberi awalan `'`. CSV: UTF-8 BOM, koma, titik desimal.
- Setelah mengubah kelas Tailwind atau JS: `npm run build`. Tiap perintah PHP memakai PHP 8.4 Laragon (`C:\laragon\bin\php\php-8.4.27-Win32-vs17-x64` di `PATH`).
- Komit diakhiri `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Jangan push tanpa persetujuan user.

## Review Focus

- Zona tanpa pembacaan pada periode: nilai kosong ditampilkan "–" (bukan 0), tanpa pembagian dengan nol; CSV berisi sel kosong.
- Zona dengan batas kelembapan NULL: pembacaan "dalam batas" hanya menilai suhu.
- Nama gudang/zona berawalan `=`/`+`/`-`/`@` di CSV tidak menjadi formula.
- Periode 90 hari dengan data rapat: grafik tetap ≤ ~300 titik; CSV mentah dialirkan bertahap (tidak memuat semua baris).
- User tidak bisa memakai parameter `gudang`/`zona` (halaman, ekspor) untuk gudang yang tidak di-assign.
- Tanggal custom: "dari" mulai 00:00:00 dan "sampai" berakhir 23:59:59 (inklusif); "sampai" hari ini dipotong ke sekarang.
- Bucket benar di batas bucket dan insiden yang melewati batas periode dipotong, tumpang tindih tidak dihitung ganda.

---

### Task 1: Izin `report.view`

**Files:**
- Modify: `app/Enums/Permission.php`, `app/Support/StarterRoles.php`, `app/Http/Controllers/HomeController.php`
- Create: `database/migrations/2026_11_10_100001_grant_report_view_permission.php`
- Test: `tests/Feature/RolePermissionTest.php` (ubah), `tests/Feature/ReportPermissionMigrationTest.php` (baru), `tests/Feature/AccessControlTest.php` (ubah)

**Interfaces:**
- Produces: `Permission::ReportView` (`'report.view'`), `can:report.view` Gate (otomatis lewat loop di `AppServiceProvider`).
- Konsumen: Task 4 (route), Task 5 (route ekspor).

- [ ] **Step 1: Ubah tes lama dan tulis tes baru (RED)**
  - `RolePermissionTest::test_migration_creates_starter_roles_with_expected_permissions`: tambahkan `'report.view'` ke daftar Supervisor (urut alfabet: setelah `'notification.view'`, sebelum `'zone.update'`) dan Operator (setelah `'notification.view'`).
  - `ReportPermissionMigrationTest` (RefreshDatabase; `include` file migrasi baru dan panggil `up()`):
    - `test_grants_report_view_to_roles_with_dashboard_view_only`: role A punya `dashboard.view`, role B hanya `zone.view`; hapus baris `report.view` dari semua role lalu `up()`: A memilikinya, B tidak.
    - `test_up_is_idempotent`: `up()` dua kali tidak membuat baris ganda dan tidak error.
    - `test_down_removes_the_permission`: `down()` menghapus semua baris `report.view`.
  - `AccessControlTest::protectedRoutes`: tambah `'reports' => ['GET', '/reports', Permission::ReportView]`, `'reports summary csv' => ['GET', '/reports/summary.csv', Permission::ReportView]`, `'reports readings csv' => ['GET', '/reports/readings.csv', Permission::ReportView]`.
  - `AccessControlTest::test_home_redirects_to_the_first_permitted_page`: tambah assertion user hanya `ReportView` diarahkan ke `route('reports.index')`.
- [ ] **Step 2: Jalankan, pastikan gagal**
  Run: `php artisan test --filter="RolePermissionTest|ReportPermissionMigrationTest|AccessControlTest"` Expected: FAIL (`Permission::ReportView` tidak ada).
- [ ] **Step 3: Implementasi**
  - `Permission`: case `ReportView = 'report.view'` ditaruh setelah `NotificationView`; `label()` "Melihat dan mengunduh laporan sensor"; `group()` "Laporan".
  - `StarterRoles::STARTERS`: tambahkan `Permission::ReportView` ke Supervisor dan Operator.
  - `HomeController::LANDING`: `'report.view' => 'reports.index'` setelah `'notification.view'`.
  - Migrasi baru (hanya `DB`/`Schema`, tanpa model): `up()` menyisipkan `['role_id' => X, 'permission' => 'report.view']` untuk tiap role yang punya `dashboard.view` dan belum punya `report.view` (tanpa duplikat); `down()` menghapus semua baris `permission = 'report.view'`.
  - Route `reports.index` dibuat di Task 4; sampai saat itu tes `AccessControlTest` untuk `/reports*` dan landing masih merah, dan itu diharapkan. Task 1 selesai dengan tes migrasi dan `RolePermissionTest` hijau; `AccessControlTest` baris baru ditandai selesai di Task 4/5.
- [ ] **Step 4: Jalankan tes izin**
  Run: `php artisan test --filter="RolePermissionTest|ReportPermissionMigrationTest|RoleManagementTest"` Expected: PASS.
- [ ] **Step 5: Commit** `feat(laporan): izin report.view`

---

### Task 2: `ReportPeriod` dan `ReportFilters`

**Files:**
- Create: `app/Support/ReportPeriod.php`, `app/Support/InvalidReportPeriod.php`, `app/Support/ReportFilters.php`
- Test: `tests/Unit/ReportPeriodTest.php`, `tests/Feature/ReportFiltersTest.php`

**Interfaces:**
- Produces:
  - `final class InvalidReportPeriod extends \InvalidArgumentException` (pesan Indonesia siap ditampilkan).
  - `final class ReportPeriod { public const MAX_DAYS = 90; public const PRESETS = ['24h' => '24 jam', '7d' => '7 hari', '30d' => '30 hari', '90d' => '90 hari']; public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to, public readonly string $key); public static function preset(string $key, ?CarbonImmutable $now = null): self; public static function custom(string $from, string $to, ?CarbonImmutable $now = null): self; public function seconds(): int; public function bucketSeconds(): int; public function label(): string; }`
  - `final class ReportFilters { public function __construct(public readonly ?Warehouse $warehouse, public readonly ?Zone $zone, public readonly ReportPeriod $period, public readonly string $periodKey, public readonly ?string $from, public readonly ?string $to, public readonly ?string $error); public static function resolve(Request $request, User $user): self; }` (query: `gudang`, `zona`, `periode`, `dari`, `sampai`).
- Task 3 memakai `ReportPeriod`; Task 4/5 memakai `ReportFilters::resolve`.

- [ ] **Step 1: Tes unit `ReportPeriodTest` (RED)** dengan `$now = CarbonImmutable::parse('2026-11-10 12:00:00', 'Asia/Jakarta')`:
  - `test_presets`: `preset('24h')` → `from` = now−24 jam, `to` = now, `key` '24h'; `preset('7d')` dan `'90d'` sama untuk hari; kunci tak dikenal → `InvalidReportPeriod`.
  - `test_custom_covers_whole_days_inclusive`: `custom('2026-11-01','2026-11-03')` → from `2026-11-01 00:00:00`, to `2026-11-03 23:59:59`.
  - `test_custom_clamps_to_now`: `custom('2026-11-09','2026-11-10')` → to = now (bukan 23:59:59); `custom('2026-11-09','2026-12-31')` juga ke now.
  - Data provider penolakan (`InvalidReportPeriod`): format bukan tanggal (`'abc'`, `'2026-02-30'`), dari setelah sampai, dari di masa depan, dari lebih dari 90 hari lalu (`'2026-08-01'`), rentang lebih dari 90 hari (`'2026-08-20'`..`'2026-11-10'` dari lebih awal dari batas retensi sudah ditolak; pakai `from=2026-08-13`, `to=2026-11-10` = 90 hari penuh diterima, `2026-08-12` ditolak karena kurang dari 90 hari lalu 00:00 — tulis kedua kasus batas).
  - `test_bucket_seconds_picks_smallest_step_for_at_most_300_points`: langkah `[300, 900, 1800, 3600, 7200, 14400, 21600, 43200, 86400]`; 24 jam (86.400 dtk/300 = 288) → 300; 7 hari → 3600 (604.800/300 = 2.016 → 3600); 30 hari → 14400; 90 hari → 43200; rentang 1 jam → 300 (tidak kurang dari 300).
  - `test_label`: `preset('7d')->label()` → "3 Nov 2026 12:00 – 10 Nov 2026 12:00"; custom → "1 Nov 2026 – 3 Nov 2026" (tanggal saja bila `key` = 'custom'). Bulan singkatan Indonesia ("Jan Feb Mar Apr Mei Jun Jul Agu Sep Okt Nov Des").
- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `php artisan test tests/Unit/ReportPeriodTest.php` Expected: FAIL (kelas tidak ada).
- [ ] **Step 3: Implementasi `ReportPeriod` dan `InvalidReportPeriod`.** `custom()` parse dengan `CarbonImmutable::createFromFormat('!Y-m-d', ..., $tz)` dan memeriksa `getLastErrors()` (tanggal mustahil seperti 2026-02-30 harus ditolak). Pesan galat contoh: "Tanggal tidak valid.", "Tanggal awal tidak boleh setelah tanggal akhir.", "Data hanya tersimpan 90 hari; pilih tanggal awal dari 12 Agu 2026." (batas = hari ini dikurangi 90 hari, 00:00).
- [ ] **Step 4: Jalankan tes unit.** Expected: PASS.
- [ ] **Step 5: Tes `ReportFiltersTest` (RED)** (RefreshDatabase; `Request::create('/reports', 'GET', [...])`; dua gudang A (zona A1, A2) dan B (zona B1)):
  - `test_defaults_to_first_visible_warehouse_first_zone_and_seven_days`: operator di-assign A → warehouse A, zona A1 (urut nama), `periodKey` '7d', `error` null.
  - `test_administrator_defaults_to_first_warehouse_by_name`.
  - `test_user_with_no_warehouse_gets_null_warehouse_and_zone_without_error`.
  - `test_explicit_zone_selects_its_warehouse`: hanya `zona` → warehouse mengikuti zona.
  - `test_zone_outside_selected_warehouse_is_an_error`: `gudang=A&zona=B1.id` (keduanya dapat diakses administrator) → `error` berisi "Zona tidak termasuk gudang yang dipilih."
  - `test_foreign_warehouse_or_zone_is_forbidden`: operator A meminta `gudang=B.id` dan (terpisah) `zona=B1.id` → `HttpException` status 403.
  - `test_unknown_ids_are_not_found`: `gudang=9999` → status 404 (`NotFoundHttpException`/`ModelNotFoundException` dipetakan 404).
  - `test_custom_period_uses_from_and_to` dan `test_invalid_custom_period_sets_error_and_falls_back_to_seven_days`: `periode=custom&dari=2026-11-05&sampai=2026-11-01` → `error` berisi pesan "Tanggal awal tidak boleh setelah tanggal akhir.", `period->key` '7d'.
  - `test_unknown_period_key_falls_back_with_error`: `periode=1y` → error "Periode tidak dikenal.".
- [ ] **Step 6: Jalankan, pastikan gagal** lalu implementasikan `ReportFilters::resolve`: ambil `Warehouse::visibleTo($user)->orderBy('name')`; `gudang` diberikan → `Warehouse::findOrFail` lalu `abort_unless($user->canAccessWarehouse($w), 403)`; `zona` diberikan → `Zone::findOrFail` + `abort_unless($user->canAccessZone($z), 403)`; tanpa `gudang` dan dengan `zona` → gudang dari zona; tanpa keduanya → gudang pertama yang terlihat, zona pertama (urut nama) di gudang itu; `periode` default '7d'; galat disimpan sebagai string, bukan dilempar.
- [ ] **Step 7: Jalankan tes** `php artisan test --filter="ReportPeriodTest|ReportFiltersTest"` Expected: PASS.
- [ ] **Step 8: Commit** `feat(laporan): periode dan filter laporan`

---

### Task 3: `ReportService`

**Files:**
- Create: `app/Services/ReportService.php`
- Test: `tests/Feature/ReportServiceTest.php`

**Interfaces:**
- Consumes: `ReportPeriod` (Task 2), `Zone`, `SensorLog`, `Incident`, `IncidentReason`.
- Produces (`final class ReportService`):
  - `summary(Collection $zones, ReportPeriod $period): array` → daftar baris: `['zone' => Zone, 'readings' => int, 'temperature' => ['min'=>?float,'max'=>?float,'avg'=>?float], 'humidity' => [sama], 'in_range_percent' => ?float (1 desimal), 'incident_count' => int, 'incident_minutes' => int, 'offline_minutes' => int]` dalam urutan `$zones`.
  - `series(Zone $zone, ReportPeriod $period): array` → `['bucket_seconds' => int, 'labels' => list<string ISO8601>, 'temperature' => ['avg'=>list<float>,'min'=>list<float>,'max'=>list<float>], 'humidity' => [sama]]`, urut waktu, hanya bucket yang punya data.
  - `incidents(Zone $zone, ReportPeriod $period, int $limit = 100): Collection<Incident>` (yang beririsan dengan periode, terbaru dulu).
  - `readings(Zone $zone, ReportPeriod $period): LazyCollection<SensorLog>` (urut `id`, `lazyById(5000)`).
- Konsumen: Task 4, 5.

- [ ] **Step 1: Tulis `ReportServiceTest` (RED)** (RefreshDatabase; `Carbon::setTestNow('2026-11-10 12:00:00')`; zona dengan batas suhu 15–25, kelembapan 80–95; log dibuat lewat `SensorLog::factory()->create([... 'created_at' => ...])`):
  - `test_summary_aggregates_only_this_zones_readings_inside_the_period`: 3 log dalam periode (suhu 18, 20, 22; kelembapan 85, 90, 95), 1 log sebelum `from`, 1 log setelah `to`, 1 log device lain → `readings` 3, suhu min 18/maks 22/avg 20.0, kelembapan 85/95/90.0.
  - `test_in_range_percent_counts_readings_within_both_limits_inclusive`: suhu 15 (tepat batas) dan 25 dihitung dalam batas, 26 di luar, suhu normal tetapi kelembapan 96 di luar → persen = 2/4 = 50.0.
  - `test_in_range_ignores_humidity_when_limits_are_null`: zona `humidity_min = humidity_max = null`; kelembapan 10 tetap dihitung dalam batas bila suhu normal.
  - `test_zone_without_readings_has_null_stats_and_zero_counts`: `readings` 0, semua min/maks/avg dan `in_range_percent` null.
  - `test_incident_counts_clip_to_period_and_do_not_double_count_overlaps`: periode 24 jam; insiden suhu 10:00–11:00 dan kelembapan 10:30–11:30 (lama gabungan 90 menit), insiden offline 02:00–02:20 (20 menit), insiden mulai 2 hari lalu selesai 3 jam setelah `from` (dipotong, 180 menit), insiden terbuka mulai 11:00 (hingga sekarang 12:00 = 60 menit, tumpang tindih dengan yang lain dihitung sekali), insiden sudah selesai sebelum `from` (tidak dihitung) → `incident_count` dan `incident_minutes`/`offline_minutes` sesuai hitungan manual (tulis angka eksak di tes).
  - `test_series_buckets_keep_min_avg_and_max`: periode 24 jam (bucket 300 dtk): dua log di bucket yang sama (suhu 10 dan 30), satu di bucket lain → 2 label, bucket pertama min 10/maks 30/avg 20; label ISO8601 berzona `+07:00`; log tepat di batas bucket (`12:05:00` vs `12:04:59`) jatuh ke bucket berbeda.
  - `test_series_is_empty_when_no_readings`: semua larik kosong.
  - `test_series_point_count_is_bounded_for_ninety_days`: 90 hari dengan 1 log per jam (2.160 log) → jumlah label ≤ 300.
  - `test_incidents_lists_overlapping_newest_first_and_respects_limit`: 3 insiden beririsan + 1 di luar; `limit` 2 → 2 terbaru.
  - `test_readings_streams_in_id_order_for_zone_and_period_only`: hasil `LazyCollection` dengan id naik, hanya log zona/periode itu.
- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `php artisan test tests/Feature/ReportServiceTest.php` Expected: FAIL (kelas tidak ada).
- [ ] **Step 3: Implementasi `ReportService`.**
  - `summary`: satu query agregat per zona (`count`, `min/max/avg` suhu dan kelembapan, jumlah dalam batas lewat `SUM(CASE WHEN temperature BETWEEN ? AND ? [AND humidity BETWEEN ? AND ?] THEN 1 ELSE 0 END)` dengan binding batas zona; kelembapan dilewati bila salah satu batas null), memakai indeks `(device_id, created_at)`; `created_at` antara `from` dan `to` inklusif. Insiden: query `Incident` zona yang `started_at <= to` dan (`ended_at` null atau `>= from`); potong tiap interval ke `[from, to]` (insiden terbuka berakhir di `min(now, to)`); `incident_minutes` = panjang gabungan interval non-offline (union interval, bukan jumlah), `offline_minutes` = gabungan interval `SensorOffline`; `incident_count` = semua yang beririsan; menit dibulatkan ke bawah.
  - `series`: `groupBy` ekspresi bucket sesuai driver (lihat Global Constraints; bucket start = `2000-01-01 00:00:00` di zona waktu aplikasi + bucket×N detik), `selectRaw` avg/min/max untuk suhu dan kelembapan, urut bucket.
  - `incidents`, `readings`: sesuai Interfaces.
- [ ] **Step 4: Jalankan tes.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(laporan): perhitungan ringkasan, deret waktu, insiden`

---

### Task 4: Halaman Laporan

**Files:**
- Create: `app/Http/Controllers/ReportController.php`, `resources/views/reports/index.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php` (tautan "Laporan"; `print:hidden` pada `<header>`), `resources/views/dashboard.blade.php` (tautan "Riwayat lengkap"), `tests/Feature/AccessControlTest.php` (assertion nav)
- Test: `tests/Feature/ReportPageTest.php`

**Interfaces:**
- Consumes: `ReportFilters::resolve`, `ReportService` (Task 2, 3).
- Produces: route `reports.index` (`GET /reports`) di grup `can:report.view`; variabel view `reports.index`: `filters`, `warehouses`, `zones`, `rows` (hasil `summary`), `series` (atau null), `incidents`, `hasData`.

- [ ] **Step 1: Tulis `ReportPageTest` (RED)** (dua gudang, Operator di-assign A, data seperti Task 3):
  - `test_page_shows_summary_rows_for_zones_of_selected_warehouse_only`: melihat nama zona A1, A2 dan angka suhu min/maks/avg yang diharapkan; tidak melihat zona B1.
  - `test_warehouse_selector_lists_only_visible_warehouses`.
  - `test_empty_period_shows_dashes_and_no_data_notice`: sel "–" dan teks "Belum ada data pada periode ini."
  - `test_invalid_period_shows_error_message_and_no_summary`.
  - `test_foreign_warehouse_and_zone_parameters_are_forbidden` (403 untuk `?gudang=B` dan `?zona=B1`).
  - `test_incident_list_shows_zone_incidents_in_period`.
  - `test_page_states_retention`: teks "Data disimpan 90 hari".
  - `test_chart_data_is_embedded_as_json`: elemen `<script id="report-data" type="application/json">` berisi `labels`, `temperature`, `humidity`, batas zona (`temp_min`, `temp_max`, `humidity_min`, `humidity_max`), dan tidak mengandung `</script>` mentah (nama zona berisi `</script>` ter-escape; pakai `JSON_HEX_TAG | JSON_HEX_AMP`).
  - `test_user_without_any_warehouse_sees_notice`.
  - Nav: user Operator (punya `report.view`) melihat tautan "Laporan" di dashboard; user tanpa izin tidak melihatnya (`withPermissions(Permission::DashboardView)`); dashboard menampilkan "Riwayat lengkap" hanya untuk pemegang izin.
- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `php artisan test tests/Feature/ReportPageTest.php` Expected: FAIL (route tidak ada).
- [ ] **Step 3: Implementasi.**
  - Route di `routes/web.php`: `Route::middleware('can:report.view')->group(...)` berisi `GET /reports` → `[ReportController::class, 'index']`, nama `reports.index` (dua ekspor ditambah di Task 5).
  - `ReportController::index(Request)`: `ReportFilters::resolve`; `warehouses` = `Warehouse::visibleTo`; `zones` = zona gudang terpilih (urut nama, dengan `products`); bila `error` ada → tanpa `rows/series/incidents`; selain itu panggil service. `hasData` = ada pembacaan di salah satu zona.
  - View: form GET (select gudang, zona, periode, input tanggal tampil bila "Tanggal sendiri"; tombol Terapkan), blok galat (`role="alert"`), tabel ringkasan (angka diformat desimal koma `number_format($v, 1, ',', '.')`, "–" untuk null, waktu offline dan insiden "X jam Y mnt"), kartu grafik (canvas `#report-chart`, tombol Suhu/Kelembapan, memuat `reports.js` dengan `@vite` di Task 6), daftar insiden, catatan "Data disimpan 90 hari", kelas `print:hidden` pada form dan tombol; blok judul khusus cetak (`hidden print:block`: gudang, zona, periode, "Dicetak oleh {nama} pada {waktu}").
  - Layout: tautan "Laporan" `@can('report.view')` setelah "Riwayat Insiden"; `print:hidden` pada `<header>`. Dashboard: tautan "Riwayat lengkap" ke `route('reports.index')` di kartu tren, `@can('report.view')`.
- [ ] **Step 4: Jalankan tes** `php artisan test --filter="ReportPageTest|AccessControlTest"` Expected: PASS untuk halaman dan landing; entri ekspor CSV di `AccessControlTest` masih menunggu Task 5.
- [ ] **Step 5: Commit** `feat(laporan): halaman laporan`

---

### Task 5: Ekspor CSV

**Files:**
- Create: `app/Support/Csv.php`, tambahan method di `app/Http/Controllers/ReportController.php`
- Modify: `routes/web.php`, `resources/views/reports/index.blade.php` (dua tombol unduh)
- Test: `tests/Unit/CsvTest.php`, `tests/Feature/ReportExportTest.php`

**Interfaces:**
- Produces:
  - `final class Csv { public static function cell(mixed $value): mixed` (string berawalan `=`,`+`,`-`,`@`, tab, atau CR diberi `'` di depan; angka dan null tidak berubah, null → ''); `public static function download(string $filename, array $header, iterable $rows): StreamedResponse` (BOM UTF-8, `fputcsv`, `Content-Type: text/csv; charset=UTF-8`, `Content-Disposition: attachment`, `Cache-Control: no-store, private`); `public static function filename(string $prefix, string $warehouse, ReportPeriod $period, ?string $zone = null): string` (slug ASCII, tanggal `Ymd`) }`.
  - Route `reports.summary-csv` (`GET /reports/summary.csv`), `reports.readings-csv` (`GET /reports/readings.csv`); parameter sama dengan halaman.
  - Ringkasan CSV header: `Gudang, Zona, Device ID, Pembacaan, Suhu min, Suhu maks, Suhu rata-rata, Kelembapan min, Kelembapan maks, Kelembapan rata-rata, % dalam batas, Jumlah insiden, Menit insiden, Menit offline`. Mentah: `Waktu, Gudang, Zona, Device ID, Suhu (°C), Kelembapan (%)`.

- [ ] **Step 1: Tulis tes (RED).**
  - `CsvTest`: `cell` mengawali `'` untuk `=SUM(A1)`, `+1`, `-1`, `@x`, `"\tx"`, `"\rx"`; teks normal, angka `18.5`, `0`, dan `null` tidak berubah (null → ''); `filename('laporan-ringkasan', 'Gudang Cikokol (Telur)', preset7d)` → `laporan-ringkasan-gudang-cikokol-telur-20261103-20261110.csv`.
  - `ReportExportTest`: `test_summary_csv_has_bom_header_and_one_row_per_zone` (isi dibaca dari `streamedContent()`), `test_readings_csv_contains_only_selected_zone_and_period_in_time_order`, `test_formula_like_names_are_escaped` (nama zona `=HYPERLINK("x")` → sel diawali `'`), `test_empty_period_yields_header_only_for_readings_and_empty_cells_for_summary`, `test_invalid_period_returns_422`, `test_foreign_zone_or_warehouse_returns_403_for_both_exports`, `test_response_is_not_cacheable_and_has_attachment_filename`, `test_readings_csv_is_streamed_in_chunks` (buat 12.000 log dengan satu insert massal; unduh dan hitung baris = 12.001; tidak ada pembatasan memori yang dicek, cukup benar dan lengkap).
- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `php artisan test --filter="CsvTest|ReportExportTest"` Expected: FAIL.
- [ ] **Step 3: Implementasi** `Csv` dan dua method controller (`ReportFilters::resolve`; `error` → `abort(422, $error)`; tanpa zona terpilih untuk ekspor mentah → 422 "Pilih zona."). Mentah memakai `ReportService::readings` (`lazyById`). Tambahkan dua route ke grup `can:report.view`; dua tombol "Unduh ringkasan (CSV)" dan "Unduh data mentah (CSV)" di halaman memakai parameter query saat ini (`request()->query()`), `print:hidden`.
- [ ] **Step 4: Jalankan semua tes laporan.** Run: `php artisan test --filter="Csv|ReportExportTest|ReportPageTest|AccessControlTest"` Expected: PASS (termasuk entri `/reports/*.csv` di `AccessControlTest`).
- [ ] **Step 5: Commit** `feat(laporan): ekspor CSV ringkasan dan data mentah`

---

### Task 6: Grafik, cetak, dokumentasi, verifikasi nyata

**Files:**
- Create: `resources/js/reports.js`
- Modify: `vite.config.js` (tambah input `resources/js/reports.js`), `resources/views/reports/index.blade.php` (`@push('scripts') @vite(['resources/js/reports.js'])`, tombol "Cetak / Simpan PDF" memanggil `window.print()`), `README.md`, `docs/LAPORAN-PENGERJAAN.md` (bagian ringkas laporan)
- Test: tidak ada tes PHP baru (perilaku sisi browser); verifikasi manual di bawah.

**Interfaces:**
- Consumes: elemen `#report-chart`, `#report-data` (JSON dari Task 4: `labels`, `temperature{avg,min,max}`, `humidity{avg,min,max}`, `temp_min`, `temp_max`, `humidity_min`, `humidity_max`, `bucket_seconds`), tombol `[data-metric]`.

- [ ] **Step 1: Tulis `reports.js`**: baca JSON, gambar Chart.js garis (rata-rata tebal; min dan maks tipis, warna terang), garis batas zona putus-putus (hanya bila tidak null), label sumbu X: jam (`HH:mm`) bila `bucket_seconds` ≤ 3600 dan rentang ≤ 48 jam, selain itu tanggal + jam; tombol Suhu/Kelembapan menukar data; `animation: false` (agar cetak benar); tanpa data → sembunyikan canvas dan tampilkan `#chart-empty`.
- [ ] **Step 2: Build dan jalankan seluruh suite.** Run: `npm run build` lalu `php artisan test` Expected: semua hijau (hanya satu tes yang sengaja dilewati di SQLite); `vendor/bin/pint --test` bersih untuk berkas baru (berkas `IncidentLifecycleTest.php` memang sudah gagal sebelum fitur ini).
- [ ] **Step 3: Dokumentasi.** README: bagian "Laporan" (izin `report.view`, filter, periode maksimal 90 hari, CSV dan catatan Excel/Indonesia, cetak ke PDF lewat browser, batas yang ditunda) dan tambahkan `report.view` ke daftar izin dan tabel role awal. `docs/LAPORAN-PENGERJAAN.md`: tambahkan ringkasan fitur ini beserta jumlah tes.
- [ ] **Step 4: Verifikasi di browser terhadap MySQL dev** (server sudah berjalan di :8000; bila belum, jalankan `php artisan serve --port=8000`): buat gudang dan zona sementara `zz-laporan` lewat tinker dengan ± 3.000 pembacaan acak 30 hari terakhir dan dua insiden; login sebagai Administrator; periksa `/reports` untuk 24 jam, 7 hari, 30 hari, dan tanggal sendiri (tabel, grafik tiga garis, daftar insiden); unduh kedua CSV dan periksa isinya (BOM, baris, desimal titik); periksa tampilan cetak lewat pratinjau cetak; login sebagai user Operator yang hanya di-assign ke gudang lain dan pastikan `?gudang=` gudang sementara memberi 403; setelah selesai hapus zona dan gudang sementara (pembacaan ikut terhapus lewat penghapusan zona) dan pastikan tabel `sensor_logs` kembali seperti semula.
- [ ] **Step 5: Commit** `feat(laporan): grafik, tampilan cetak, dokumentasi`

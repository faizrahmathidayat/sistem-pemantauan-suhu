# Multi-Gudang (Gudang → Zona → Produk) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sistem mengenal banyak gudang, tiap gudang punya banyak zona (satu sensor dan batas sendiri) berisi daftar produk; user di-assign ke gudang dan hanya melihat serta menerima alert gudangnya.

**Architecture:** Tabel `warehouses`, `warehouse_user`, `zone_products`, dan kolom `zones.warehouse_id`. Pembatasan data lewat scope `visibleTo(User)` di model Warehouse/Zone/Incident dan metode `User::canAccessWarehouse/canAccessZone`, dipakai semua query dan controller. Administrator melihat semua gudang. Izin baru `warehouse.manage` dan `zone.manage` melalui enum `Permission` yang sudah ada.

**Tech Stack:** Laravel 13, PHP 8.4, Blade + Tailwind 4 + Chart.js (Vite), PHPUnit 12 (SQLite in-memory), MySQL untuk dev.

**Spec:** `docs/superpowers/specs/2026-11-09-multi-gudang-design.md` (dibangun di atas modul hak akses: `docs/superpowers/specs/2026-11-09-hak-akses-design.md`)

## Global Constraints

- Jalankan dari `D:\GMI\PROJECT\super-app-gmi\iot-gmi` dengan `export PATH="/c/laragon/bin/php/php-8.4.27-Win32-vs17-x64:$PATH"`. Tes: `php artisan test`. Baseline sebelum rencana ini: **192 tes lulus**. Jalankan `vendor/bin/pint --test` pada berkas yang disentuh (satu berkas lama, `IncidentLifecycleTest.php`, memang gagal Pint dan tidak diubah).
- Seluruh teks tampilan berbahasa Indonesia; kelas Tailwind baru butuh `npm run build` sebelum dicek di browser.
- Migrasi **tidak boleh memakai model Eloquent** (hanya `DB`/`Schema`), supaya perubahan model kelak tidak merusak `migrate:fresh`. Harus jalan di SQLite (tes) dan MySQL (dev).
- Factory bawaan harus menjaga 192 tes lama tetap hijau: `ZoneFactory` otomatis membuat gudang, dan user pabrik bawaan adalah Administrator (melihat semua gudang). Tes lama yang menyebut `product_type` atau judul notifikasi lama diperbarui seperlunya, bukan dihapus.
- Aturan akses: Administrator = semua gudang; user lain = gudang yang di-assign; akses ke zona/insiden gudang lain = **403** (JSON untuk endpoint polling). Alert = user dengan `notification.view` dan (Administrator atau di-assign ke gudang zona itu).
- Nama gudang unik; nama zona unik per gudang; `device_id` unik global (huruf kecil, angka, `-`, maks 64). Produk: satu nama per baris, maks 20 per zona, maks 80 karakter per nama, kosong/duplikat diabaikan.
- Token sensor: hanya hash sha256 yang disimpan; nilai asli tampil **sekali** dan tidak pernah masuk Log Perubahan, tampilan lain, atau log aplikasi. `php artisan zone:token` tetap bekerja.
- API telemetri (`POST /api/v1/telemetry`) tidak berubah perilakunya.
- Semua perubahan lewat TDD (tes gagal dulu); commit per task dengan akhiran `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Kebocoran data antar gudang lewat JSON dan halaman: `/dashboard/data`, `/zones/{zone}/readings`, `/incidents`, `POST /incidents/{incident}/acknowledge`, Batas Zona, notifikasi (Task 2).
2. User dengan dua gudang melihat keduanya; non-Administrator tanpa gudang melihat dashboard kosong dengan keterangan, bukan galat (Task 2, 3).
3. Migrasi pada database yang sudah berisi data: sensor, token, insiden, dan pembacaan tetap utuh; jenis produk lama menjadi produk (Task 1).
4. Menghapus zona yang punya riwayat sensor: pembacaan, insiden, dan produknya hilang, zona lain tidak tersentuh, dan token lama langsung ditolak telemetri (Task 5).
5. Token tampil sekali saja dan tidak bocor ke Log Perubahan atau muat-ulang halaman (Task 5).
6. User non-Administrator tidak bisa meng-assign gudang yang bukan miliknya, dan menyunting user lain tidak boleh melepas gudang yang tidak ia punya (Task 6).

---

### Task 1: Skema, model, dan migrasi data

**Files:**
- Create: `database/migrations/2026_11_09_130001_create_warehouses_and_zone_products.php`, `app/Models/Warehouse.php`, `app/Models/ZoneProduct.php`, `database/factories/WarehouseFactory.php`
- Modify: `app/Models/Zone.php`, `database/factories/ZoneFactory.php`, `database/seeders/ZoneSeeder.php`, `app/Services/DashboardSnapshot.php`, `resources/views/zones/index.blade.php`, `resources/views/zones/edit.blade.php`, tes lama yang menyebut `product_type`
- Delete: `app/Enums/ProductType.php`
- Test: `tests/Feature/WarehouseSchemaTest.php`

**Interfaces:**
- Produces: `Warehouse` (`name`): `zones(): HasMany`, `users(): BelongsToMany` (pivot `warehouse_user`). `ZoneProduct` (`zone_id`, `name`, `position`). `Zone` mendapat `warehouse_id` (fillable), `warehouse(): BelongsTo`, `products(): HasMany` (urut `position`, `id`), `syncProducts(list<string> $names): void` (ganti seluruh daftar, `position` mengikuti urutan); `product_type` dan `ProductType` dihapus.
- Produces: `WarehouseFactory` (`name` unik); `ZoneFactory` default `warehouse_id` membuat gudang lewat `Warehouse::factory()`.
- Produces: `DashboardSnapshot` zona memuat `warehouse_id` (int) dan `products` (`list<string>`) menggantikan `product_type`.

- [ ] **Step 1: Tulis tes gagal** di `WarehouseSchemaTest`: `test_zone_belongs_to_a_warehouse_and_lists_products_in_order` (buat zona, `syncProducts(['Telur A','Telur B'])`; `products->pluck('name')` berurutan; sync kedua `['Telur B']` menggantikan), `test_warehouse_name_is_unique`, `test_zone_name_is_unique_within_a_warehouse_but_not_across`, `test_deleting_a_zone_removes_its_products`, `test_warehouse_with_zones_cannot_be_deleted_at_database_level` (FK restrict), `test_migration_moves_existing_zones_into_a_default_warehouse` (tes data migrasi: `migrate:rollback` migrasi ini dengan `--path` pada koneksi tes, isi tabel `zones` lama lewat `DB` dengan `product_type='telur'` plus satu user non-Administrator, jalankan migrasi lagi; harapan: satu gudang "Gudang Utama" memuat semua zona itu, zona punya satu produk bernama "Telur", kolom `product_type` hilang, `device_id`/token/insiden utuh, user non-Administrator di-assign ke gudang itu, user Administrator tidak perlu), `test_dashboard_snapshot_zone_has_warehouse_id_and_product_names`. Bila rollback/ulang migrasi tidak mungkin di SQLite in-memory, ganti dengan tes terhadap kelas pembantu berbasis `DB::table` dan catat `Ruling` di ledger.
- [ ] **Step 2: Jalankan** `php artisan test tests/Feature/WarehouseSchemaTest.php`. Expected: gagal.
- [ ] **Step 3: Implementasi.** Migrasi: buat `warehouses` (`name` unik, timestamps), `warehouse_user` (`warehouse_id`, `user_id` FK cascade, unik pasangan), `zone_products` (`zone_id` FK cascade, `name`, `position`); tambah `zones.warehouse_id` nullable → isi backfill (buat "Gudang Utama" bila ada zona; zona lama jadi anggota; produk dari `product_type` dengan label Telur/Buah/Sayur; assign user yang rolenya bukan sistem) → ubah jadi wajib dengan FK `restrictOnDelete`; ubah unik nama zona menjadi `[warehouse_id, name]` bila sebelumnya ada indeks unik; hapus `product_type`; `down()` memulihkan kolom `product_type` (bawaan `sayur`). Semua langkah data memakai `DB::table`. Perbarui `ZoneSeeder`: gudang "Gudang Cikokol (Telur)" dengan zona Inbound, FG, dan Loading (device `cikokol-inbound|fg|loading`, batas telur 10–20 °C dan 60–85 %, produk contoh). Perbarui dua view zona agar menampilkan nama gudang dan daftar produk menggantikan jenis produk, dan hapus `ProductType` beserta pemakaiannya.
- [ ] **Step 4: Jalankan** tes file ini lalu seluruh suite. Expected: semua lulus (192 lama + baru).
- [ ] **Step 5: Commit** `feat: skema gudang, zona, dan produk beserta migrasi data`.

---

### Task 2: Pembatasan data per gudang

**Files:**
- Modify: `app/Models/User.php`, `app/Models/Warehouse.php`, `app/Models/Zone.php`, `app/Models/Incident.php`, `app/Services/DashboardSnapshot.php`, `app/Services/IncidentManager.php`, `app/Http/Controllers/{DashboardController,ZoneReadingsController,IncidentController,IncidentAcknowledgeController,ZoneSettingsController}.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/WarehouseAccessTest.php`

**Interfaces:**
- Consumes: Task 1 (`Warehouse`, `Zone::warehouse`).
- Produces: `User::warehouses(): BelongsToMany`, `User::canAccessWarehouse(Warehouse|int $warehouse): bool` (true untuk Administrator), `User::canAccessZone(Zone $zone): bool`, `User::scopeWithWarehouseAccess(Builder, Warehouse|int)` (Administrator atau di-assign). `Warehouse::scopeVisibleTo(Builder, User)`, `Zone::scopeVisibleTo(Builder, User)`, `Incident::scopeVisibleTo(Builder, User)`. `UserFactory::assignedTo(Warehouse ...$warehouses)` (state/afterCreating yang meng-assign).
- Produces pemetaan akses: `DashboardSnapshot::build`, riwayat insiden, insiden terbaru di dashboard, daftar dan form Batas Zona hanya memuat data gudang yang boleh; `ZoneReadingsController`, `IncidentAcknowledgeController`, `ZoneSettingsController::edit/update` memberi 403 untuk gudang lain; `IncidentManager::notify` memakai `User::withPermission(NotificationView)` digabung `withWarehouseAccess($zone->warehouse_id)`.

- [ ] **Step 1: Tulis tes gagal** di `WarehouseAccessTest` (dua gudang A dan B, masing-masing satu zona, satu insiden terbuka; user `withRole(Operator)` di-assign ke A saja; Administrator bawaan): `test_dashboard_data_contains_only_assigned_warehouses` (JSON `zones` dan `banners` hanya milik A; Administrator melihat A dan B), `test_user_with_two_warehouses_sees_both`, `test_unassigned_non_administrator_gets_empty_data_not_an_error` (200, `zones` kosong), `test_readings_for_another_warehouse_zone_is_forbidden_json` (403 JSON; zona A 200), `test_incident_log_lists_only_assigned_warehouse_incidents`, `test_acknowledge_in_another_warehouse_is_forbidden_and_leaves_incident_open`, `test_zone_limits_list_and_edit_are_scoped` (daftar `/zones` hanya zona A; `GET/PUT /zones/{zone B}/edit` 403, nilai tidak berubah), `test_recent_incidents_on_dashboard_are_scoped`, `test_alerts_go_only_to_assigned_users_and_administrators` (picu insiden zona B lewat jalur `IncidentManager` seperti `IncidentLifecycleTest`: Administrator ya, Operator-A tidak, Operator-B ya, Operator-A dengan gudang A dan B ya), `test_administrator_user_without_assignments_still_sees_everything`.
- [ ] **Step 2: Jalankan** `php artisan test tests/Feature/WarehouseAccessTest.php`. Expected: gagal.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Pembatasan terpusat di scope dan metode `User`; controller hanya memanggilnya (`abort_unless($request->user()->canAccessZone($zone), 403)`). Insiden diselesaikan lewat relasi `zone.warehouse_id`.
- [ ] **Step 4: Jalankan** file ini lalu seluruh suite. Expected: lulus.
- [ ] **Step 5: Commit** `feat: pembatasan data per gudang (dashboard, insiden, zona, alert)`.

---

### Task 3: Dashboard multi-gudang (chip, kartu dengan produk, lokasi lengkap)

**Files:**
- Modify: `app/Services/DashboardSnapshot.php`, `app/Notifications/IncidentOpened.php`, `resources/views/dashboard.blade.php`, `resources/views/incidents/index.blade.php`, `resources/views/notifications/index.blade.php`, `resources/views/zones/index.blade.php` (kelompok per gudang), `resources/js/dashboard.js`, tes lama yang menyebut judul notifikasi
- Test: `tests/Feature/MultiWarehouseDashboardTest.php`

**Interfaces:**
- Consumes: Task 2 (data sudah terbatas per user).
- Produces: `DashboardSnapshot::build()` menambah `warehouses: list<{id: int, name: string, status: string, open_incidents: int}>` (`status` = status terburuk zona gudang itu menurut `ZoneStatus::severity()`; gudang tanpa zona: `tanpa_data`) dan `has_access: bool` (false hanya bila user non-Administrator tanpa gudang); tiap zona memuat `warehouse_name`; teks banner menjadi "Gudang › Zona: …". Judul notifikasi `IncidentOpened` memuat "Gudang › Zona" dan datanya memuat `warehouse_name`.

- [ ] **Step 1: Tulis tes gagal** di `MultiWarehouseDashboardTest`: `test_snapshot_lists_warehouses_with_worst_status_and_open_incident_count` (satu gudang dengan zona Aman dan zona Bahaya → `status = bahaya`, `open_incidents = 1`; gudang lain semua aman), `test_snapshot_flags_missing_access` (`has_access` false untuk Operator tanpa gudang, true untuk Administrator), `test_banner_message_names_warehouse_and_zone`, `test_notification_title_and_data_include_the_warehouse`, `test_dashboard_page_renders_chips_and_empty_notice` (halaman memuat tiap nama gudang yang boleh dilihat; user tanpa gudang melihat "Anda belum ditugaskan ke gudang mana pun"), `test_incident_log_and_notification_page_show_warehouse_name`, `test_zone_limits_page_groups_zones_by_warehouse`. Perbarui assert lama yang menyebut judul notifikasi lama.
- [ ] **Step 2: Jalankan** file ini. Expected: gagal.
- [ ] **Step 3: Implementasi.** `dashboard.js`: chip "Semua gudang" + satu chip per gudang (titik status 🔴/🟡/🟢 dan jumlah insiden terbuka), pilihan tersimpan di `?gudang=` (`history.replaceState`), bawaan "Semua gudang" bila lebih dari satu gudang, otomatis gudangnya bila hanya satu; kartu zona difilter per chip dan memuat daftar produk (`esc()` dipakai untuk semua teks); grafik dan chip zona mengikuti filter; keterangan kosong bila `has_access` salah. View Blade mengikuti (nama gudang di Riwayat Insiden, Notifikasi, dan Batas Zona).
- [ ] **Step 4: Jalankan** file ini, seluruh suite, `npm run build`, lalu cek di browser pada lebar desktop dan 375 px (tanpa scroll horizontal): ganti chip, buka lewat `?gudang=`, lihat produk di kartu, user tanpa gudang.
- [ ] **Step 5: Commit** `feat: dashboard multi-gudang dengan chip gudang dan produk pada kartu zona`.

---

### Task 4: Pengelolaan gudang dan izin baru

**Files:**
- Create: `app/Http/Controllers/WarehouseController.php`, `app/Http/Requests/WarehouseRequest.php`, `resources/views/warehouses/{index,create,edit,_form}.blade.php`
- Modify: `app/Enums/Permission.php`, `routes/web.php`, `resources/views/layouts/app.blade.php`, `app/Http/Controllers/HomeController.php`, `app/Models/AuditLog.php`
- Test: `tests/Feature/WarehouseManagementTest.php`

**Interfaces:**
- Consumes: Task 2 (`visibleTo`), `AuditLogger::saveAndLog/record` (modul hak akses).
- Produces: `Permission::WarehouseManage = 'warehouse.manage'` ("Mengelola gudang", grup "Gudang") dan `Permission::ZoneManage = 'zone.manage'` ("Mengelola zona dan produk", grup "Zona"); resource `warehouses` (kecuali `show`, `can:warehouse.manage`); `AuditLog::EVENTS` bertambah `warehouse.created|updated|deleted` dan `zone.created|updated|deleted|token_rotated`, `FIELDS` bertambah `warehouse`, `warehouses`, `device_id`, `products`.

- [ ] **Step 1: Tulis tes gagal** di `WarehouseManagementTest` (pelaku `withPermissions(WarehouseManage)`): `test_index_lists_only_visible_warehouses_with_zone_counts`, `test_store_creates_warehouse_and_auto_assigns_a_non_administrator_creator`, `test_name_is_required_and_unique`, `test_update_renames_warehouse`, `test_cannot_manage_a_warehouse_the_actor_cannot_access` (non-Administrator: `edit/update/destroy` gudang lain 403), `test_warehouse_with_zones_cannot_be_deleted` (galat menyebut jumlah zona; gudang utuh), `test_empty_warehouse_can_be_deleted`, `test_pages_require_warehouse_manage_permission`, `test_new_permissions_appear_on_the_role_form_and_administrator_has_them`, `test_warehouse_events_are_recorded_in_the_audit_log` (created, updated hanya bila nama berubah, deleted).
- [ ] **Step 2: Jalankan** file ini. Expected: gagal.
- [ ] **Step 3: Implementasi** mengikuti pola `RoleController/RoleRequest` (kartu ramah HP, pesan `status`/`error`, konfirmasi hapus); tambahkan `warehouse.manage` ke urutan landing `HomeController` dan menu "Gudang" dengan `@can`. Halaman ubah gudang memuat daftar zonanya dengan tautan "Tambah zona" dan "Kelola" (target Task 5; tombolnya tampil hanya bila user punya `zone.manage`).
- [ ] **Step 4: Jalankan** file ini, seluruh suite, `npm run build`. Expected: lulus.
- [ ] **Step 5: Commit** `feat: pengelolaan gudang dan izin warehouse.manage/zone.manage`.

---

### Task 5: Pengelolaan zona, produk, dan token sensor

**Files:**
- Create: `app/Http/Controllers/ZoneManagementController.php`, `app/Http/Requests/ZoneManagementRequest.php`, `app/Support/ZoneProducts.php`, `resources/views/zones/{create,manage,_form}.blade.php`
- Modify: `routes/web.php`, `app/Http/Requests/UpdateZoneRequest.php` (aturan batas dipakai bersama), `resources/views/warehouses/edit.blade.php`, `app/Console/Commands/ZoneToken.php` (memakai pembuat token bersama)
- Test: `tests/Feature/ZoneManagementTest.php`, `tests/Unit/ZoneProductsTest.php`

**Interfaces:**
- Consumes: Task 2 (`canAccessZone/Warehouse`), Task 4 (izin dan label audit).
- Produces: `ZoneProducts::parse(string $text): list<string>` (satu nama per baris; pangkas spasi; buang kosong dan duplikat tanpa membedakan huruf besar-kecil; maks 20 nama; nama lebih dari 80 karakter dianggap tidak valid di request). `Zone::issueToken(): string` (membuat token acak 48 karakter, menyimpan hanya sha256-nya, mengembalikan nilai asli) dipakai UI dan `zone:token`. Route: `GET /warehouses/{warehouse}/zones/create` (`warehouses.zones.create`), `POST /warehouses/{warehouse}/zones` (`warehouses.zones.store`), `GET|PUT /zones/{zone}/manage` (`zones.manage`, `zones.manage.update`), `DELETE /zones/{zone}` (`zones.destroy`), `POST /zones/{zone}/token` (`zones.token`), semuanya `can:zone.manage` dan wajib akses ke gudangnya. Token baru dibawa ke halaman berikutnya lewat flash sesi (`new_token`), tampil sekali.

- [ ] **Step 1: Tulis tes gagal.** `ZoneProductsTest`: pangkas dan buang baris kosong/duplikat (`"Telur A\n\n telur a \nTelur B"` → `['Telur A','Telur B']`), batas 20 nama. `ZoneManagementTest` (pelaku `withPermissions(ZoneManage)` di-assign ke gudang A): `test_store_creates_zone_with_limits_products_and_a_one_time_token` (redirect ke halaman kelola; flash `new_token` berisi 48 karakter; di database hanya `sha256` token itu; muat ulang halaman tidak menampilkan token lagi), `test_new_token_authenticates_telemetry_for_the_new_zone` (POST `/api/v1/telemetry` dengan token itu → 201), `test_store_validates_name_unique_per_warehouse_device_id_format_unique_and_limits` (galat aturan batas yang sama dengan `UpdateZoneRequest`), `test_device_id_cannot_be_changed_after_creation` (field `device_id` pada update diabaikan/ditolak), `test_update_renames_zone_and_replaces_products`, `test_rotating_token_invalidates_the_old_one` (telemetri dengan token lama → 401, token baru → 201; token tidak ada di `AuditLog::all()->toJson()`), `test_delete_removes_zone_logs_incidents_and_products_but_not_other_zones` (zona lain dan pembacaannya utuh; telemetri zona terhapus → 401), `test_cannot_manage_zones_of_another_warehouse` (403 untuk create/store/manage/update/destroy/token), `test_pages_require_zone_manage_permission`, `test_zone_events_are_recorded_without_token_value` (`zone.created`, `zone.updated` hanya bila ada perubahan, `zone.deleted`, `zone.token_rotated`; tidak ada nilai token di diff).
- [ ] **Step 2: Jalankan** kedua file. Expected: gagal.
- [ ] **Step 3: Implementasi.** Ekstrak aturan batas dan pemeriksaan lintas-kolom dari `UpdateZoneRequest` ke trait bersama yang dipakai juga oleh `ZoneManagementRequest`. Penghapusan zona dalam satu transaksi: hapus `sensor_logs` berdevice zona itu, lalu zona (insiden dan produk ikut lewat FK cascade), lalu catat audit; dialog konfirmasi menyebut seluruh data zona ikut terhapus. Form: nama, device ID (hanya saat membuat), batas dan margin, area teks produk (satu per baris).
- [ ] **Step 4: Jalankan** kedua file, seluruh suite, `npm run build`, lalu cek di browser: buat zona, salin token, cek token tidak muncul lagi setelah muat ulang.
- [ ] **Step 5: Commit** `feat: pengelolaan zona, produk, dan token sensor dari aplikasi`.

---

### Task 6: Assignment user ke gudang, dokumentasi

**Files:**
- Modify: `app/Http/Controllers/UserController.php`, `app/Http/Requests/UserRequest.php`, `resources/views/users/_form.blade.php`, `resources/views/users/index.blade.php`, `README.md`, `firmware/esp32-telemetry/README.md` (bila menyebut `zone:token` untuk setup awal)
- Test: `tests/Feature/UserWarehouseAssignmentTest.php`

**Interfaces:**
- Consumes: Task 2 (`User::warehouses`, `canAccessWarehouse`), modul hak akses (`UserController`, `AuditLogger::saveAndLog` dengan `$extra`).
- Produces: field form `warehouses[]` (id gudang). Diff audit `warehouses` (`from`/`to` berupa daftar nama gudang) pada `user.created` dan `user.updated`.

- [ ] **Step 1: Tulis tes gagal** (pelaku `withPermissions(UserManage)`): `test_store_assigns_selected_warehouses`, `test_update_replaces_assignment_for_administrator_actor`, `test_non_administrator_can_only_assign_warehouses_they_have` (id gudang di luar miliknya → galat `warehouses.0`, tidak tersimpan), `test_non_administrator_edit_keeps_assignments_it_cannot_see` (target punya A dan B, pelaku hanya A; menyimpan dengan A saja tidak melepas B; menghapus centang A melepas A saja), `test_warehouse_checkboxes_show_only_assignable_warehouses`, `test_assignment_changes_are_recorded_in_audit_log` (nama gudang lama dan baru; tanpa perubahan tidak ada catatan), `test_assignment_is_irrelevant_for_administrator_target` (form menampilkan keterangan, tanpa error), `test_user_list_shows_warehouses_per_user`.
- [ ] **Step 2: Jalankan** file ini. Expected: gagal.
- [ ] **Step 3: Implementasi.** `UserRequest`: `warehouses` opsional array, tiap elemen `exists:warehouses,id`; pemeriksaan "milik pelaku" dilakukan di controller (non-Administrator) lalu `sync` terbatas pada himpunan yang boleh ia atur; untuk Administrator `sync` penuh. Perbarui README (struktur Gudang → Zona → Produk, izin baru, cara menambah gudang/zona/sensor lewat aplikasi, assignment, aturan akses, seeder contoh Cikokol) dan README firmware bila perlu.
- [ ] **Step 4: Jalankan** file ini, seluruh suite, `vendor/bin/pint --test` pada berkas baru, `npm run build`, lalu cek manual di browser: buat gudang, tambah zona, kirim telemetri dengan token baru, assign user, login sebagai user itu dan pastikan hanya gudangnya yang tampil.
- [ ] **Step 5: Commit** `feat: assignment user ke gudang dan dokumentasi multi-gudang`.

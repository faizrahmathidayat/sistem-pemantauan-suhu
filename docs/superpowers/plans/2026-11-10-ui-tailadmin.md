# Perombakan Tampilan Gaya TailAdmin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Semua halaman memakai layout sidebar + header dan komponen bergaya TailAdmin, responsif di HP, tanpa mengubah backend.

**Architecture:** Token warna dan kelas komponen di `resources/css/app.css`; komponen Blade anonim di `resources/views/components/`; satu layout baru (`layouts/app.blade.php`) dengan sidebar dan header berbasis Alpine.js; setiap halaman digambar ulang memakai komponen itu. `dashboard.js` dan `reports.js` hanya berganti kelas dan warna.

**Tech Stack:** Laravel 13 Blade (komponen anonim), Tailwind 4 (`@theme`, `@layer components`), Alpine.js 3, Chart.js 4, Vite 8, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-11-10-ui-tailadmin-design.md`

## Global Constraints

- Backend (controller, model, service, rute, izin, migrasi) tidak diubah; kecuali penyesuaian teks tampilan di view. Bila sebuah tes gagal karena markup lama, sesuaikan tes agar memeriksa hal yang sama pada markup baru; jangan melonggarkan asersi.
- Atribut yang dibaca kode lain tetap: `data-data-url`, `data-readings-url`, `data-ack-url` (pada `#dashboard`), `data-unread-badge`, id `dashboard-data`, `banners`, `zones`, `warehouse-chips`, `chart-zone-chips`, `chart-metric-chips`, `trend-chart`, `chart-empty`, `scheduler-warning`, `connection-warning`, `no-access-notice`, `report-data`, `report-chart`, `custom-dates`, tombol `data-warehouse`, `data-zone`, `data-metric`, `data-ack`, dan `<option>Administrator</option>`.
- Section layout tetap: `title`, `content`, `banner`, `scripts`, stack `head`.
- Teks antarmuka berbahasa Indonesia; label menu yang sudah ada tidak berubah ("Dashboard", "Riwayat Insiden", "Laporan", "Batas Zona", "Log Perubahan", "Gudang", "User", "Role", "Keluar").
- Token palet (nilai TailAdmin): `brand` 25 `#f2f7ff`, 50 `#ecf3ff`, 100 `#dde9ff`, 300 `#9cb9ff`, 500 `#465fff`, 600 `#3641f5`, 700 `#2a31d8`; `gray` 25 `#fcfcfd`, 50 `#f9fafb`, 100 `#f2f4f7`, 200 `#e4e7ec`, 300 `#d0d5dd`, 400 `#98a2b3`, 500 `#667085`, 600 `#475467`, 700 `#344054`, 800 `#1d2939`, 900 `#101828`; `success` 50 `#ecfdf3`, 500 `#12b76a`, 600 `#039855`, 700 `#027a48`; `warning` 50 `#fffaeb`, 500 `#f79009`, 600 `#dc6803`, 700 `#b54708`; `error` 50 `#fef3f2`, 500 `#f04438`, 600 `#d92d20`, 700 `#b42318`.
- Dependensi baru hanya `alpinejs` dengan versi persis (mis. `3.14.9`, bukan rentang). Font tetap font sistem.
- Setelah mengubah kelas atau JS: `npm run build`. Tiap perintah PHP memakai PHP 8.4 Laragon di `PATH` (`C:\laragon\bin\php\php-8.4.27-Win32-vs17-x64`). Server uji sudah berjalan di :8000 dan membaca berkas langsung.
- Komit diakhiri `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Jangan push tanpa persetujuan user.
- Akun uji untuk verifikasi browser dibuat sementara di T1 (Administrator dan Operator yang hanya di-assign ke gudang demo; kata sandi acak disimpan di berkas scratchpad, tidak ditulis di komit atau pesan), dihapus di T6.

## Review Focus

- Menu sidebar mengikuti izin persis seperti navigasi lama: user tanpa izin X tidak melihat tautan X, dan tidak ada halaman kehilangan akses ke menu yang seharusnya ada.
- Teks dari data (nama zona/gudang/produk, nama user) tetap ter-escape, termasuk di string HTML `dashboard.js` (`esc`) dan komponen baru.
- Di HP, sidebar bisa dibuka dan ditutup, dan konten tidak tertutup banner kritis maupun header (z-index dan `top` banner).
- Tidak ada scroll mendatar halaman pada lebar 375 px di semua halaman (tabel lebar hanya bergulir di dalam kartunya).
- Cetak Laporan tetap tanpa sidebar dan header, dan grafik tetap tampil.
- Aset Vite lengkap setelah build: `app`, `dashboard`, `reports` tersedia di manifest, tidak ada galat konsol di halaman mana pun.
- Kontras dan keterbacaan badge status (aman/waspada/bahaya/offline) dan fokus keyboard pada tombol dan tautan.

---

### Task 1: Fondasi (token, komponen, layout, login, halaman galat)

**Files:**
- Modify: `resources/css/app.css`, `resources/js/app.js`, `package.json`/`package-lock.json` (Alpine), `resources/views/layouts/app.blade.php`, `resources/views/auth/login.blade.php`, `resources/views/auth/no-access.blade.php`, `resources/views/errors/403.blade.php`
- Create: `resources/views/components/{icon,card,badge,page-header,alert,empty,table}.blade.php`, `resources/views/layouts/partials/{sidebar,header}.blade.php`
- Test: `tests/Feature/LayoutTest.php` (baru); sesuaikan `AccessControlTest::test_navigation_only_shows_permitted_links` bila perlu

**Interfaces:**
- Produces (dipakai T2-T5):
  - `<x-icon name="…" class="size-5"/>`; nama: `dashboard`, `incident`, `report`, `bell`, `zone`, `building`, `users`, `shield`, `log`, `menu`, `close`, `logout`, `chevron-down`, `thermometer`, `droplet`, `clock`, `arrow-up`, `arrow-down`, `plus`, `download`, `print`, `check`, `alert`, `pencil`, `trash`, `key`, `info`.
  - `<x-card :title="null" class="">` slot bawaan + slot bernama `actions` (kanan judul); padding `p-5 sm:p-6`.
  - `<x-badge variant="gray|brand|success|warning|error" :pulse="false">`.
  - `<x-page-header title="…" description="…" :crumbs="[['label' => 'X', 'url' => '/x']]">` slot bawaan = tombol aksi.
  - `<x-alert variant="success|error|warning|info">`.
  - `<x-empty title="…" icon="…">` slot bawaan = deskripsi/aksi.
  - `<x-table>` slot = `<thead>`/`<tbody>`; kelas `.table-th`, `.table-td` (CSS).
  - Kelas CSS: `.form-label`, `.form-input` (juga untuk `select`/`textarea`), `.form-hint`, `.form-error`, `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger`, `.chip`, `.chip-active`.
  - Layout: sidebar `@can`-gated; header dengan `[data-unread-badge]`; wadah sticky `<div class="sticky top-0 z-40">` berisi header lalu `@yield('banner')`.

- [ ] **Step 1: Siapkan akun uji dan pemasangan Alpine.** Pasang `npm install alpinejs@3.14.9 --save-exact`. Buat skrip scratchpad (`C:\Users\ASUS\AppData\Local\Temp\claude\7700e313f07d2659\d72c7953-0af4-43ee-9092-877eca13a384\scratchpad\ui-setup.php`) yang membuat dua user uji (`zz-ui-admin@uji.test` Administrator, `zz-ui-op@uji.test` Operator yang di-assign ke "Gudang Demo") dengan kata sandi acak yang ditulis ke `ui-uji.json` di scratchpad; jalankan.
- [ ] **Step 2: Tulis `LayoutTest` (RED).** Konteks: user dengan `Role::where('name','Operator')`, `User::factory()->create()` (Administrator), dan `withPermissions(...)`.
  - `test_sidebar_shows_only_permitted_links`: Operator melihat `url('/dashboard')`, `/incidents`, `/reports`, `/notifications` di sidebar dan tidak melihat `/zones`, `/warehouses`, `/users`, `/roles`, `/audit-logs`; Administrator melihat semuanya.
  - `test_sidebar_marks_the_current_page`: pada `/incidents`, tautan "Riwayat Insiden" memuat `aria-current="page"` dan tautan Dashboard tidak.
  - `test_header_shows_user_name_role_logout_and_unread_badge`: nama user, nama role ("Operator"/"Administrator"), formulir POST ke `route('logout')`, dan `data-unread-badge` bernilai jumlah belum dibaca (buat 2 notifikasi belum dibaca).
  - `test_mobile_menu_button_and_banner_container_exist`: `aria-label="Buka menu"` ada; di `/dashboard` id `banners` berada di dalam wadah `sticky` yang sama dengan header (cek urutan `<header` sebelum `id="banners"` di dalam elemen `class="sticky`).
  - `test_login_403_and_no_access_pages_render`: `/login` memuat input email, password, dan tombol Masuk; user tanpa izin ke `/users` mendapat 403 dengan teks "403"; user tanpa role melihat "belum punya hak akses".
  - `test_page_title_and_brand_come_from_config`: `<title>` memuat `config('app.name')` dan sidebar menampilkan nama itu.
- [ ] **Step 3: Jalankan, pastikan gagal.** Run: `php artisan test tests/Feature/LayoutTest.php` Expected: FAIL.
- [ ] **Step 4: Implementasi.**
  - `app.css`: `@theme` untuk palet (lihat Global Constraints) dan `--font-sans` tetap; `@layer components` berisi kelas komponen yang tercantum di Interfaces dengan gaya TailAdmin (input `h-11 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10`, tombol utama biru `rounded-lg px-4 py-3 text-sm font-medium`, `.chip` pil abu-abu yang menjadi biru saat `chip-active`, `[x-cloak]{display:none!important}`, `.table-th` `px-5 py-3 text-start text-xs font-medium text-gray-500`, `.table-td` `px-5 py-4 text-sm text-gray-500`).
  - `app.js`: impor Alpine, `window.Alpine = Alpine`, `Alpine.start()`.
  - Komponen Blade sesuai Interfaces; `x-icon` memuat peta nama → SVG (stroke `currentColor`, `viewBox` 24, garis sederhana buatan sendiri) dan memeriksa gambar di browser pada Step 6.
  - `layouts/app.blade.php`: `<body>` dengan `x-data="{ sidebarOpen: false }"`; sidebar tetap di `lg:` dan panel geser + latar gelap di bawahnya (`@keydown.escape.window`, klik latar menutup); area kanan dengan wadah sticky (header + `@yield('banner')`) dan `<main class="p-4 md:p-6">`; `print:hidden` pada sidebar, header; `main` penuh lebar saat cetak. Sidebar: grup Menu, Pengaturan, Administrasi dengan `@can` yang sama seperti navigasi lama, `aria-current="page"` memakai `request()->routeIs(...)` (dashboard; incidents.*; reports.*; notifications.*; zones.index|zones.edit|zones.update; warehouses.*|warehouses.zones.*|zones.manage*|zones.token|zones.destroy; users.*; roles.*; audit-logs.*). Header: hamburger (`aria-label="Buka menu"`), lonceng (`@can('notification.view')`, `data-unread-badge` seperti sekarang), dropdown pengguna (inisial, nama, `auth()->user()->role?->name`, formulir Keluar).
  - Login: dua kolom di `lg:` (formulir | panel merek biru), satu kolom di HP, memakai kelas komponen; 403 dan no-access: kartu di tengah dengan `x-empty`/ikon dan tautan kembali.
- [ ] **Step 5: Jalankan tes.** Run: `php artisan test` Expected: PASS (sesuaikan tes lama yang bergantung markup lama sesuai Global Constraints).
- [ ] **Step 6: Bangun dan lihat di browser.** Run: `npm run build`; login sebagai akun uji (isi form lewat `javascript_tool`) dan periksa `/dashboard` (konten lama belum direstyle), `/login`, sidebar desktop dan 375 px (buka/tutup), dropdown pengguna, tanpa galat konsol; perbaiki ikon yang tampak salah.
- [ ] **Step 7: Commit** `feat(ui): layout sidebar dan header gaya TailAdmin, komponen dasar`

---

### Task 2: Dashboard

**Files:**
- Modify: `resources/views/dashboard.blade.php`, `resources/js/dashboard.js`
- Test: `tests/Feature/DashboardViewTest.php` (ubah seperlunya), `tests/Feature/DashboardUiTest.php` (baru)

**Interfaces:**
- Consumes: komponen dan kelas T1; kontrak id/atribut pada Global Constraints.
- Produces: tidak ada (halaman akhir).

- [ ] **Step 1: Tulis `DashboardUiTest` (RED).**
  - `test_dashboard_keeps_the_javascript_contract`: respons memuat `id="dashboard"`, ketiga `data-*-url`, `id="dashboard-data"` (JSON valid dengan kunci `zones`, `warehouses`, `banners`), dan id `warehouse-chips`, `zones`, `banners`, `chart-zone-chips`, `chart-metric-chips`, `trend-chart`, `chart-empty`, `scheduler-warning`, `connection-warning`.
  - `test_dashboard_shows_recent_incidents_card_and_report_link`: nama zona insiden terbaru tampil, serta tautan "Riwayat lengkap" bagi pemegang `report.view`.
  - `test_no_access_user_sees_notice`: user tanpa gudang melihat "Anda belum ditugaskan ke gudang mana pun." tanpa `<script id="dashboard-data">` yang berisi zona.
- [ ] **Step 2: Jalankan; yang belum ada harus gagal sesuai perubahan markup.** Run: `php artisan test --filter="DashboardUiTest|DashboardViewTest"`.
- [ ] **Step 3: Implementasi.** `dashboard.blade.php`: `x-page-header` (judul "Dashboard", deskripsi pemantauan), wadah chip gudang bergulir mendatar, grid zona `grid gap-4 sm:grid-cols-2 xl:grid-cols-3`, `x-card` grafik (judul, chip zona, segmen Suhu/Kelembapan, canvas tinggi `h-64 sm:h-80`), `x-card` insiden terbaru (daftar dengan `x-badge` status penanganan). Peringatan scheduler/koneksi memakai gaya `x-alert`. `dashboard.js`: ganti kelas pada string HTML: kartu zona (`rounded-2xl border bg-white p-5`, cincin `ring-4 ring-error-100` + `border-error-500` untuk Bahaya, badge status memakai kelas palet baru dengan titik berdenyut untuk Bahaya, suhu besar `text-4xl font-semibold`, indikator tren berupa ikon panah dalam lencana kecil, batas `(Max: …)`, baris kelembapan dengan ikon tetes, "update X lalu" dengan ikon jam, tag produk `rounded-md bg-gray-100`), banner kritis (merah/kuning/abu seperti sekarang, wadah `px-4 py-3 sm:px-6`, tombol `w-full sm:w-auto`), chip memakai `.chip`; ikon di `dashboard.js` berupa SVG inline kecil yang didefinisikan sekali di berkas itu. Semua teks dari data tetap lewat `esc()`. Opsi Chart.js: warna garis memakai `brand-500`/`#0ea5e9`, grid `#f2f4f7`, tanpa border sumbu.
- [ ] **Step 4: Jalankan tes dan bangun.** Run: `php artisan test` lalu `npm run build` Expected: PASS.
- [ ] **Step 5: Lihat di browser** dengan data demo: desktop dan 375 px; zona Bahaya di paling atas (buat satu insiden demo sementara dengan `demo:simulate`-tidak diperlukan: pakai satu pembacaan di atas batas lewat skrip scratchpad dua kali, lalu `demo:clear` + `demo:seed` ulang di akhir T6 bila data demo berubah), banner menempel saat gulir, tombol Tandai Telah Ditangani berfungsi dan banner menjadi "Sedang ditangani", chip gudang berpindah dan `?gudang=` berubah, grafik tampil, tanpa galat konsol.
- [ ] **Step 6: Commit** `feat(ui): dashboard dengan kartu metrik, banner sticky, dan grafik bergaya TailAdmin`

---

### Task 3: Halaman daftar (Riwayat Insiden, Notifikasi, Log Perubahan, Batas Zona)

**Files:**
- Modify: `resources/views/incidents/index.blade.php`, `resources/views/notifications/index.blade.php`, `resources/views/audit-logs/index.blade.php`, `resources/views/zones/index.blade.php`
- Test: tes yang sudah ada untuk keempat halaman (`IncidentLogTest`, `NotificationPageTest`, `AuditLogTest`, `ZoneSettingsTest`, `MultiWarehouseDashboardTest`); tambah `tests/Feature/ListPagesUiTest.php`

**Interfaces:** Consumes komponen T1. Produces: pola "tabel di ≥ md, daftar kartu di HP" yang dipakai ulang T4-T5.

- [ ] **Step 1: Tulis `ListPagesUiTest` (RED).**
  - `test_incidents_page_renders_table_and_mobile_cards_with_same_data`: dua insiden → nama zona tampil di tabel (`<table`) dan di daftar kartu (`md:hidden`); badge "Belum ditangani"/"Ditangani oleh {nama}" ada; tautan paginasi ada bila > 1 halaman (buat 30 insiden, cek `page=2`).
  - `test_empty_states_use_the_empty_component`: halaman insiden, notifikasi, dan log perubahan tanpa data menampilkan teks kosong yang sama seperti sebelumnya ("Belum ada insiden." dst.).
  - `test_audit_log_page_lists_changes_in_both_layouts`: satu entri → "nilai lama → baru" muncul pada tabel dan kartu.
  - `test_zone_settings_lists_each_warehouse_with_zone_cards`: gudang dan zona terlihat, tombol "Ubah batas" bagi pemegang `zone.update`, "Kelola zona" dan "+ Zona" bagi pemegang `zone.manage`.
- [ ] **Step 2: Jalankan, pastikan gagal** (bagian markup baru), **implementasi**, **jalankan** `php artisan test`: PASS.
- [ ] **Step 3: Implementasi.** Tiap halaman: `x-page-header` + `x-card`/`x-table` bergaya TailAdmin (kepala tabel abu-abu muda, baris `divide-y`, badge untuk status/penyebab), daftar kartu untuk HP, `x-empty` untuk keadaan kosong, paginasi dibungkus rapi (view paginasi bawaan sudah memakai Tailwind; periksa tampilannya). Notifikasi: item belum dibaca diberi penanda titik biru dan tombol "Tandai semua dibaca" memakai `.btn-secondary`.
- [ ] **Step 4: Bangun dan lihat di browser** (desktop dan 375 px) keempat halaman; tidak ada scroll mendatar.
- [ ] **Step 5: Commit** `feat(ui): halaman daftar dengan tabel dan kartu bergaya TailAdmin`

---

### Task 4: Laporan

**Files:**
- Modify: `resources/views/reports/index.blade.php`, `resources/js/reports.js`
- Test: `tests/Feature/ReportPageTest.php`, `ReportExportTest.php` (tetap hijau); tambah `tests/Feature/ReportUiTest.php`

**Interfaces:** Consumes komponen T1, pola T3; kontrak: id `report-data`, `report-chart`, `custom-dates`, tombol `data-metric`, tautan unduh, tombol cetak, blok judul cetak (`hidden print:block`).

- [ ] **Step 1: Tulis `ReportUiTest` (RED).**
  - `test_summary_is_a_table_on_desktop_and_cards_on_mobile`: dua zona → nama zona dan angka suhu (mis. "18,0") muncul di `<table` dan di daftar kartu `md:hidden`.
  - `test_filter_form_keeps_field_names_and_custom_dates_toggle`: select `gudang`, `zona`, `periode`, input `dari`, `sampai`, wadah `id="custom-dates"` (hidden bila periode bukan custom, tidak hidden bila custom).
  - `test_download_print_and_print_header_remain`: tautan `reports.summary-csv` dan `reports.readings-csv`, tombol "Cetak / Simpan PDF", blok `hidden print:block` berisi gudang, zona, periode.
- [ ] **Step 2: Jalankan, pastikan gagal; implementasi; jalankan** `php artisan test --filter="Report"`: PASS.
- [ ] **Step 3: Implementasi.** Filter dalam `x-card` dengan grid `sm:grid-cols-2 lg:grid-cols-4`, tombol Terapkan `w-full sm:w-auto`; ringkasan: tabel (`x-table`) di ≥ md dengan angka rata kanan, dan daftar kartu per zona di HP (nama zona, pembacaan, suhu/kelembapan ringkas, % dalam batas sebagai `x-badge` hijau/kuning/merah berdasarkan ambang 98/90, insiden dan offline); kartu grafik dan insiden; `reports.js`: warna dan grid seperti dashboard.
- [ ] **Step 4: Bangun dan lihat di browser** (desktop, 375 px, pratinjau cetak lewat emulasi media cetak bila tersedia; jika tidak, periksa kelas `print:*` di CSS hasil build); unduh CSV dari halaman tetap berfungsi.
- [ ] **Step 5: Commit** `feat(ui): halaman laporan bergaya TailAdmin`

---

### Task 5: Manajemen (Gudang, Zona, User, Role)

**Files:**
- Modify: `resources/views/warehouses/{index,create,edit,_form}.blade.php`, `resources/views/zones/{create,edit,manage}.blade.php`, `resources/views/users/{index,create,edit,_form}.blade.php`, `resources/views/roles/{index,create,edit,_form}.blade.php`
- Test: `WarehouseManagementTest`, `ZoneManagementTest`, `ZoneSettingsTest`, `UserManagementTest`, `RoleManagementTest`, `UserWarehouseAssignmentTest` (tetap hijau); tambah `tests/Feature/ManagementUiTest.php`

**Interfaces:** Consumes komponen dan pola T1-T3.

- [ ] **Step 1: Tulis `ManagementUiTest` (RED).**
  - `test_forms_use_shared_form_classes_and_keep_field_names`: halaman `warehouses/create`, `zones` create (`warehouses/{id}/zones/create`), `users/create`, `roles/create` memuat input dengan nama yang sama seperti sebelumnya (`name`, `device_id`, `temp_min`…, `email`, `role_id`, `warehouses[]`, `permissions[]`) dan kelas `form-input`; tombol kirim `btn-primary`.
  - `test_validation_errors_are_shown_with_alert_component`: kirim form kosong → pesan galat Indonesia muncul dalam `role="alert"`.
  - `test_zone_manage_page_marks_the_dangerous_actions`: halaman kelola zona memuat bagian "Hapus zona" dengan `btn-danger` dan konfirmasi `confirm(`; token tampil sekali masih dalam blok mencolok.
  - `test_users_and_roles_lists_render_table_and_mobile_cards`: nama user/role muncul di `<table` dan di kartu `md:hidden`.
- [ ] **Step 2: Jalankan, pastikan gagal; implementasi; jalankan** `php artisan test`: PASS.
- [ ] **Step 3: Implementasi.** Setiap form dalam `x-card` dengan grid dua kolom di `sm:`; kotak centang izin per kelompok dalam kartu kecil; tombol utama `w-full sm:w-auto`; blok bahaya (hapus) di kartu berborder merah terpisah; daftar gudang/user/role berupa tabel + kartu HP dengan aksi "Ubah"/"Hapus" memakai `.btn-secondary`/`.btn-danger` kecil; flash `status` memakai `x-alert`.
- [ ] **Step 4: Bangun dan lihat di browser** semua halaman ini (desktop, 375 px) sebagai Administrator dan Operator; tidak ada scroll mendatar.
- [ ] **Step 5: Commit** `feat(ui): halaman manajemen bergaya TailAdmin`

---

### Task 6: Verifikasi akhir, dokumentasi, pembersihan

**Files:**
- Modify: `README.md` (catatan tampilan, mengganti `APP_NAME`), `docs/LAPORAN-PENGERJAAN.md` (bagian perombakan UI)

- [ ] **Step 1: Bangun dan jalankan seluruh suite.** Run: `npm run build` lalu `php artisan test` lalu `vendor/bin/pint --test app tests routes` Expected: semua hijau (satu tes dilewati di SQLite; berkas `IncidentLifecycleTest.php` memang sudah punya selisih Pint lama).
- [ ] **Step 2: Sapuan browser terakhir** sebagai Administrator dan Operator (gudang demo): dashboard, insiden, notifikasi, laporan, batas zona, gudang, kelola zona, user, role, log perubahan, login, 403; desktop dan 375 px; periksa tanpa galat konsol, tanpa scroll mendatar (`document.documentElement.scrollWidth <= innerWidth`), sidebar HP, banner menempel, dan tombol aksi selebar layar di HP.
- [ ] **Step 3: Dokumentasi.** README: bagian singkat "Tampilan" (layout, komponen, cara mengganti warna lewat token dan nama lewat `APP_NAME`); laporan pengerjaan: bagian "Perombakan tampilan" dengan hasil verifikasi.
- [ ] **Step 4: Pembersihan.** Hapus akun uji (`zz-ui-*`) dan berkas `ui-uji.json`; periksa jumlah user dan notifikasi tabel kembali seperti sebelum T1; bila data demo diubah selama verifikasi, `php artisan demo:clear` lalu `php artisan demo:seed` (dan jalankan ulang `demo:simulate` di latar belakang bila sebelumnya aktif).
- [ ] **Step 5: Commit** `docs(ui): dokumentasi tampilan dan laporan pengerjaan`

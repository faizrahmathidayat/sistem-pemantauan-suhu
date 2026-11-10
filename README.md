# Sistem Pemantauan Suhu & Kelembapan Gudang Produk Segar

Pemantauan telur, buah, dan sayur berbasis IoT: **ESP32 + DHT22** mengirim data ke **Laravel 13 (PHP 8.4)**, yang menyimpan data, menilai status tiap zona, dan memberi notifikasi in-app ke user yang rolenya boleh menerima notifikasi saat suhu/kelembapan keluar batas atau sensor berhenti mengirim data.

```
ESP32 + DHT22 --POST JSON (Bearer token)--> /api/v1/telemetry --> sensor_logs
                                                    |
                                  IncidentManager (2 pembacaan berturut-turut)
                                                    |
                  incidents + notifikasi database ke user berizin notifikasi
                                                    |
        Dashboard web (kartu zona, grafik 24 jam, banner kritis, riwayat insiden)
```

Rancangan lengkap: [`docs/superpowers/specs/2026-11-09-sistem-pemantauan-suhu-design.md`](docs/superpowers/specs/2026-11-09-sistem-pemantauan-suhu-design.md).

## Kebutuhan

PHP 8.4 (ekstensi `openssl pdo_mysql pdo_sqlite sqlite3 mbstring fileinfo curl zip intl`), Composer 2, Node 20+, MySQL 8.

## Instalasi

```bash
composer install
cp .env.example .env          # lalu atur DB_*, ADMIN_EMAIL, ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed    # membuat tabel, gudang contoh (Cikokol, 3 zona), role awal, dan akun admin bila ADMIN_* diisi
npm ci && npm run build
```

## Gudang, zona, dan produk

Struktur data: **Gudang → Zona → Produk**.

- **Gudang** hanya punya nama (mis. "Gudang Cikokol (Telur)").
- **Zona** milik satu gudang: satu sensor ESP32 (`device_id` + token) dan batas suhu/kelembapan sendiri; satu sensor mengawasi semua produk di zona itu.
- **Produk** adalah daftar nama di dalam zona (tanpa stok atau jumlah), satu nama per baris.
- **User** di-assign ke satu atau lebih gudang (di form User) dan hanya melihat serta menerima alert gudang itu. **Administrator melihat semua gudang** tanpa perlu di-assign; user non-Administrator tanpa gudang melihat dashboard kosong.

Menambah gudang dan sensor lewat aplikasi (tanpa CLI):

1. Menu **Gudang** (izin `warehouse.manage`) → **+ Gudang**. Pembuat non-Administrator otomatis di-assign ke gudang barunya.
2. Buka gudang itu → **+ Zona** (izin `zone.manage`): isi nama, `device_id` sensor (huruf kecil, angka, `-`; tidak bisa diubah kemudian), batas suhu/kelembapan, dan produk. Token sensor dibuat otomatis dan **tampil sekali**; salin ke `config.h` firmware. Token hilang? Buat token baru dari halaman **Kelola zona** (token lama langsung tidak berlaku).
3. Menu **User** → centang gudang untuk tiap user.

Menghapus zona ikut menghapus seluruh pembacaan sensor, insiden, dan produknya (dengan konfirmasi, tidak bisa dibatalkan); gudang hanya bisa dihapus bila tidak punya zona. Dashboard menampilkan chip gudang (status terburuk dan jumlah insiden terbuka), pilihannya tersimpan di alamat halaman (`?gudang=`).

Data contoh dari seeder (`database/seeders/ZoneSeeder.php`) memakai batas **placeholder** untuk Gudang Cikokol (Telur):

| Zona | Device ID | Suhu | Kelembapan |
|---|---|---|---|
| Inbound | `cikokol-inbound` | 10–20°C | 60–85% |
| FG | `cikokol-fg` | 10–20°C | 60–85% |
| Loading | `cikokol-loading` | 10–20°C | 60–85% |

Instalasi lama yang di-`migrate`: zona yang sudah ada dipindahkan ke gudang bawaan **"Gudang Utama"** (sensor, token, insiden, dan riwayat tetap utuh), jenis produk lama (Telur/Buah/Sayur) menjadi satu produk per zona, dan user non-Administrator di-assign ke gudang itu.

### Batas suhu dan kelembapan

Ubah lewat menu **Batas Zona** di dashboard (`/zones`; perlu izin `zone.update`, perubahan langsung berlaku). Setiap perubahan lewat halaman ini tercatat (siapa, kapan, nilai lama → baru) di menu **Log Perubahan** (`/audit-logs`); mengubah langsung di database atau seeder tidak tercatat. Alternatif: `database/seeders/ZoneSeeder.php` (lalu `php artisan db:seed --class=ZoneSeeder`, yang menimpa nilai di database) atau langsung di tabel `zones` (`temp_min`, `temp_max`, `humidity_min`, `humidity_max`; isi kelembapan dengan NULL untuk tidak memeriksanya; `warning_margin` dan `humidity_warning_margin` mengatur batas status Waspada).

## Laporan

Menu **Laporan** (`/reports`, izin `report.view`) menampilkan riwayat sensor:

- **Filter:** gudang, zona (untuk grafik dan data mentah), dan periode: 24 jam, 7 hari (bawaan), 30 hari, 90 hari, atau **tanggal sendiri**. Data hanya disimpan 90 hari, jadi tanggal awal tidak boleh lebih lama dari itu. Pilihan ikut tersimpan di alamat halaman.
- **Ringkasan per zona:** suhu dan kelembapan terendah / tertinggi / rata-rata, jumlah pembacaan, jumlah insiden (tanpa sensor offline), lama insiden, lama sensor offline, dan persentase pembacaan yang dalam batas (dihitung terhadap batas zona *saat ini*).
- **Grafik** zona terpilih dengan garis rata-rata, terendah, dan tertinggi per interval (supaya lonjakan singkat tidak tersembunyi) serta garis batas zona, plus daftar insiden zona itu.
- **Unduh CSV:** ringkasan semua zona di gudang itu, dan data mentah satu zona. File berformat UTF-8 (dengan BOM), pemisah koma, titik desimal. Excel berbahasa Indonesia kadang menaruh semuanya dalam satu kolom; buka lewat **Data → From Text/CSV**.
- **Cetak / Simpan PDF:** memakai fitur cetak browser (menu dan formulir tidak ikut tercetak). Tidak ada pembuatan PDF di server.

User hanya melihat gudang yang di-assign; gudang atau zona lain ditolak (403) di halaman maupun unduhan. Laporan harian otomatis belum ada.

## User dan token perangkat

Tidak ada halaman pendaftaran publik. User dibuat oleh administrator lewat menu **User** (`/users`) atau lewat perintah di bawah.

### Role dan hak akses

Setiap user punya **satu role**; role menentukan fitur yang boleh dipakai. Role dikelola di menu **Role** (`/roles`, izin `role.manage`) dengan kotak centang izin per role. Izin yang tersedia: `dashboard.view`, `incident.view`, `incident.acknowledge`, `notification.view`, `report.view`, `warehouse.manage`, `zone.view`, `zone.update`, `zone.manage`, `auditlog.view`, `user.manage`, `role.manage`. Role menentukan *apa* yang boleh dilakukan; assignment gudang menentukan *di gudang mana*.

| Role awal | Izin |
|---|---|
| Administrator (sistem) | Semua izin, selalu (tidak bisa diubah atau dihapus) |
| Supervisor | Dashboard, insiden (lihat dan tandai ditangani), notifikasi, laporan, batas zona (lihat dan ubah), log perubahan |
| Operator | Dashboard, insiden (lihat dan tandai ditangani), notifikasi, laporan |

- Alert (notifikasi insiden) hanya dikirim ke user yang rolenya punya `notification.view` **dan** (Administrator atau di-assign ke gudang zona itu).
- User non-Administrator (pemegang `user.manage`) hanya boleh meng-assign gudang yang ia sendiri punya, dan hanya boleh melihat, mengubah, atau menghapus user yang seluruh gudangnya termasuk gudang miliknya (atau yang belum punya gudang). User gudang lain tidak tampil di daftar dan tidak bisa disentuh; kalau tidak, ia bisa mengganti kata sandi user itu lalu masuk sebagai dia.
- Selalu tersisa minimal satu Administrator; Administrator terakhir tidak bisa dihapus atau dipindah role, dan user tidak bisa menghapus akunnya sendiri.
- Hanya Administrator yang boleh memberi role Administrator atau mengubah/menghapus akun Administrator; user non-Administrator juga tidak bisa mengubah role-nya sendiri.
- Izin `user.manage` dan `role.manage` bersifat kuat: pemegang `role.manage` bisa memberi role-nya sendiri izin apa pun (kecuali menjadi Administrator). Berikan hanya kepada orang yang dipercaya.
- Role yang masih dipakai user tidak bisa dihapus. User tanpa role hanya melihat halaman "belum punya hak akses".
- Pembuatan, perubahan, dan penghapusan user dan role tercatat di **Log Perubahan** (kata sandi tidak pernah dicatat).
- Instalasi yang sudah berjalan: `php artisan migrate` membuat role awal dan menjadikan semua user yang ada sebagai Administrator, dan memberi izin `report.view` ke role yang sudah punya `dashboard.view` (cabut di menu **Role** bila perlu).

```bash
php artisan user:create "Budi Santoso" budi@example.com    # kata sandi akan ditanyakan; role bawaan Administrator
php artisan user:create "Sari" sari@example.com --role=Operator
php artisan zone:token cikokol-fg                          # token sensor zona FG, tampil sekali
```

User yang dibuat lewat CLI belum punya gudang (kecuali Administrator); tetapkan gudangnya lewat menu **User**. Token sensor juga bisa dibuat dari aplikasi (halaman **Kelola zona**). Membuat token lagi untuk zona yang sama memutar token (token lama langsung tidak berlaku).

## Data demo

Untuk melihat dashboard dan Laporan tanpa sensor sungguhan:

```bash
php artisan demo:seed              # "Gudang Demo": 3 zona (device_id demo-*), 30 hari pembacaan tiap 15 menit, beberapa insiden
php artisan demo:simulate          # opsional: kirim pembacaan palsu tiap 30 detik (Ctrl+C untuk berhenti)
php artisan demo:clear             # hapus semua data demo
```

Semua data demo ada di gudang tersendiri sehingga `demo:clear` tidak menyentuh data asli. Administrator langsung melihat "Gudang Demo"; user lain perlu di-assign ke gudang itu lewat menu **User**. Tanpa `demo:simulate`, zona demo ditandai offline setelah 2 menit (dan memicu insiden serta notifikasi), seperti sensor yang mati. `demo:seed --days=N` mengatur panjang riwayat (1-90).

## Tampilan

Antarmuka memakai pola **TailAdmin** (sidebar kiri, header yang menempel, kartu, tabel, badge) yang ditulis sendiri di atas Tailwind 4; tidak ada berkas template yang disalin.

- **Layout:** `resources/views/layouts/app.blade.php` dengan `layouts/partials/sidebar` dan `header`. Menu sidebar hanya menampilkan yang diizinkan role; di HP sidebar menjadi panel geser (Alpine.js). Banner kritis menempel tepat di bawah header.
- **Sidebar bisa diciutkan:** di layar lebar tombol panah ganda di header mengubah sidebar dari lebar penuh menjadi hanya ikon (dengan tooltip nama menu) dan sebaliknya, dengan transisi halus. Pilihan diingat di browser (`localStorage`, kunci `sidebarExpanded`) dan dipasang sebelum halaman digambar, jadi tidak berkedip. State-nya ada di `Alpine.data('shell')` (`resources/js/app.js`); di HP sidebar tetap panel geser penuh.
- **Komponen Blade** (`resources/views/components/`): `x-icon`, `x-card`, `x-badge`, `x-page-header`, `x-alert`, `x-empty`, `x-table`, `x-nav-link`, `x-form-errors`, `x-flash`. Kelas formulir dan tombol (`.form-input`, `.btn-primary`, `.chip`, dll.) ada di `resources/css/app.css`.
- **Warna:** skala `brand` di `@theme` pada `resources/css/app.css` (warna utama `#0a1f12` = `brand-500`); ganti skala itu untuk mengganti tema. Nama aplikasi di sidebar dan judul halaman diambil dari `APP_NAME` di `.env`.
- Tabel di layar lebar dan daftar kartu di HP/tablet (`data-mobile-list`); tombol aksi utama selebar layar di HP; zona berstatus Bahaya diurutkan paling atas oleh server.
- Setelah mengubah kelas atau JS: `npm run build`. Tidak ada mode gelap.

## Monitor publik (layar TV gudang)

Halaman layar penuh tanpa login supaya semua karyawan bisa melihat kondisi gudang dari TV di dinding. Isinya satu pita status besar ("SEMUA ZONA AMAN" / "PERHATIAN: n ZONA DI LUAR BATAS" / "SENSOR OFFLINE" / "WASPADA") dan satu kartu gelap per zona dengan suhu dan kelembapan besar. Memperbarui diri tiap 10 detik; tidak ada tombol, menu, nama pengguna, atau nama produk.

- **Membuat alamat:** menu **Gudang** → ubah gudang → kartu **Layar monitor** → **Buat alamat monitor** (izin `warehouse.manage`). Alamatnya berbentuk `/monitor/<40 karakter acak>`; hanya yang memegang alamat lengkap itu yang bisa membukanya. Alamat **hanya tampil sekali**: salin lalu buka di browser TV dan jadikan layar penuh (F11). Satu gudang satu alamat; beberapa TV boleh memakai alamat yang sama.
- **Mengganti atau mematikan:** **Buat ulang alamat** mematikan alamat lama seketika (TV harus diisi alamat baru); **Matikan alamat** menghentikan monitor gudang itu. TV yang alamatnya sudah mati menampilkan "Alamat monitor ini tidak berlaku lagi". Semua aksi tercatat di Log Perubahan.
- **Banyak zona:** TV tidak bisa digulir, jadi halaman selalu setinggi layar. Maksimal 6 kartu per layar (susunan 1-3 kolom, ukuran huruf menyesuaikan); lebih dari itu zona dibagi rata ke beberapa halaman yang berganti sendiri tiap 10 detik, dengan titik penanda "Halaman n dari m" di bawah nama gudang. Zona bermasalah (Bahaya, Offline, Waspada) diurutkan paling depan sehingga selalu di halaman pertama, dan pita status di atas selalu terlihat di semua halaman.
- **Layar tidak boleh menipu:** zona yang berhenti mengirim data tampil OFFLINE dan ikut menaikkan pita; bila TV gagal menghubungi server lebih dari 45 detik, muncul pita merah "TIDAK TERHUBUNG KE SERVER · data terakhir pukul ..." dan kartu diredupkan. Jam diambil dari waktu server. Halaman memuat ulang dirinya tiap 6 jam agar ikut versi aplikasi terbaru.
- **Keamanan:** kode disimpan hanya sebagai hash; respons `no-store`, `noindex`, dan `no-referrer`; dibatasi 60 (halaman) dan 300 (data) permintaan per menit per IP; berjalan tanpa sesi dan cookie sehingga tidak menulis ke tabel sesi. Alamat yang bocor cukup diganti. Isinya hanya suhu, kelembapan, batas, dan status.
- Kode: `routes/monitor.php`, `MonitorController`, `MonitorDataController`, `App\Services\MonitorSnapshot`, `resources/views/monitor/show.blade.php`, `resources/js/monitor.js`.

## Menjalankan

Dua proses harus hidup bersamaan:

```bash
php artisan serve --host=0.0.0.0 --port=8000   # web + API
php artisan schedule:work                      # WAJIB: deteksi sensor offline tiap menit + prune data
```

Di produksi, ganti `schedule:work` dengan cron tiap menit: `* * * * * php /path/artisan schedule:run`. Tanpa penjadwal, zona yang sensornya mati tidak akan pernah ditandai offline; dashboard menampilkan peringatan bila pemeriksaan terakhir lebih tua dari 5 menit.

## ESP32

Lihat [`firmware/esp32-telemetry/README.md`](firmware/esp32-telemetry/README.md) untuk wiring, library, dan konfigurasi.

## Uji API dengan curl

```bash
curl -X POST http://localhost:8000/api/v1/telemetry \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"device_id":"cikokol-fg","temperature":18.2,"humidity":62.4}'
```

Respons: `201` tersimpan, `401` token salah atau `device_id` bukan milik token, `422` data tidak valid (suhu −40..80, kelembapan 0..100), `429` terlalu sering (maks. 20/menit per perangkat).

## Aturan alert

- **Bahaya**: nilai di luar rentang min..max (tepat di batas belum bahaya). **Waspada**: dalam rentang tetapi dekat batas. **Offline**: tidak ada data > 2 menit.
- Insiden dibuka setelah **2 pembacaan berturut-turut** bahaya (jeda antar pembacaan ≤ 2 menit), sehingga satu pembacaan DHT22 yang meleset tidak memicu alarm.
- Satu notifikasi per insiden. Insiden ditutup setelah 2 pembacaan berturut-turut tidak bahaya; penutupan tidak mengirim notifikasi.
- Sensor offline memakai insiden dan notifikasi yang sama; tertutup begitu data masuk lagi.
- Tombol **Tandai Telah Ditangani** mencatat siapa dan kapan, tanpa menutup insiden.

## Menambah saluran WhatsApp nanti

Isi pesan ada di `App\Notifications\IncidentOpened`. Tambahkan saluran baru di `via()` dan method `toXxx()` yang sesuai; logika penilaian di `IncidentManager` tidak perlu diubah.

## Pengujian

```bash
php artisan test
```

## Batasan versi 1

HTTP tanpa HTTPS (jaringan lokal), satu role per user (berlaku di semua gudangnya; role per gudang belum ada), produk hanya berupa nama (tanpa stok), `device_id` zona tidak bisa diubah setelah dibuat, tanpa antrean offline di firmware, notifikasi hanya di dalam aplikasi, data sensor hanya disimpan 90 hari (laporan tidak bisa melihat lebih lama; unduh CSV berkala bila perlu arsip), tanpa laporan harian otomatis.

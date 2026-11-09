# Laporan Pengerjaan: Sistem Pemantauan Suhu & Kelembapan Gudang Produk Segar

Tanggal: 9 November 2026
Lokasi proyek: `D:\GMI\PROJECT\super-app-gmi\iot-gmi`
Repo tujuan: `git@github.com:faizrahmathidayat/sistem-pemantauan-suhu.git` (sudah di-push, branch `main`)

## 1. Ringkasan

Sistem pemantauan suhu dan kelembapan gudang telur, buah, dan sayur berbasis IoT sudah dibangun dari awal: firmware ESP32 + DHT22, API Laravel 13 (PHP 8.4), penilaian ambang batas otomatis, notifikasi in-app ke semua user, dan dashboard web yang responsif.

| Hal | Status |
|---|---|
| Backend Laravel 13 / PHP 8.4 | Selesai, 130 tes lulus (386 assertion) |
| Halaman Batas Zona + Log Perubahan | Selesai (ditambahkan setelah versi 1), diuji otomatis dan di browser |
| Dashboard web (desktop & HP) | Selesai, diperiksa langsung di browser |
| Notifikasi in-app + insiden | Selesai, diuji dari ujung ke ujung |
| Deteksi sensor offline | Selesai, diuji dengan scheduler sungguhan |
| Firmware ESP32 | Selesai ditulis, **belum dikompilasi dan belum diuji di perangkat asli** |
| Notifikasi WhatsApp | Tidak termasuk versi 1 (arsitektur sudah siap) |
| Push ke GitHub | Sudah, branch `main` |

## 2. Permintaan awal dan realisasinya

| Permintaan | Realisasi |
|---|---|
| ESP32 + DHT22, kirim `device_id`, `temperature`, `humidity` via HTTP POST JSON secara berkala | `firmware/esp32-telemetry/` (sketch modular, interval 30 detik, Bearer token per perangkat) |
| Migration dan model `sensor_logs` (id, device_id, temperature, humidity, timestamps) | Ada, ditambah tabel `zones`, `incidents`, `notifications` |
| `SensorController.php` invokable + Form Request | `app/Http/Controllers/Api/V1/SensorController.php` + `StoreSensorLogRequest` |
| Pengecekan batas aman memicu alert otomatis | `ZoneStatusEvaluator` + `IncidentManager` + notifikasi `IncidentOpened` ke semua user |
| Route di `routes/api.php` | `POST /api/v1/telemetry` (`Route::prefix('v1')`, middleware `device` dan `throttle:telemetry`) |
| Fitur PHP 8.4 (constructor promotion, typed properties, enum, arrow function) | Dipakai di service, notifikasi, enum, dan controller |
| Dashboard: status hijau/kuning/merah, grafik 24 jam, kartu per zona, banner kritis, tombol "Tandai Telah Ditangani", riwayat insiden, mobile-friendly | Seluruhnya ada (lihat bagian 4) |
| Alert WhatsApp | Ditunda; notifikasi dipisah dari saluran pengiriman sehingga WhatsApp tinggal ditambah di `via()` |

## 3. Arsitektur

```
ESP32 + DHT22 --POST JSON (Bearer token)--> /api/v1/telemetry --> sensor_logs
                                                   |
                              IncidentManager (2 pembacaan berturut-turut)
                                                   |
                       incidents + notifikasi database ke semua user
                                                   |
   Dashboard web: kartu zona, grafik 24 jam, banner kritis, riwayat insiden
   Scheduler tiap menit: deteksi sensor offline (zones:check-offline)
```

Stack: Laravel 13.40, PHP 8.4.27, MySQL 8 (tes memakai SQLite in-memory), Blade + Tailwind 4 + Chart.js lewat Vite, Arduino C++ (Adafruit DHT, ArduinoJson 7).

## 4. Fitur yang dibangun

**API dan keamanan perangkat**
- Token acak 48 karakter per zona, hanya hash sha256 yang disimpan; dibuat dan diputar ulang dengan `php artisan zone:token {device_id}`.
- Token milik zona A dengan `device_id` zona B ditolak (401) dan tidak menyimpan apa pun.
- Validasi: suhu −40..80, kelembapan 0..100; respons 201/401/422/429.

**Penilaian status**
- **Bahaya**: di luar rentang min..max (tepat di batas belum bahaya). **Waspada**: di dalam rentang tetapi dekat batas (margin suhu 2°C, kelembapan 5 poin). **Offline**: tidak ada data lebih dari 2 menit. **Belum ada data**: zona yang belum pernah mengirim apa pun.
- Batas kelembapan boleh dikosongkan; kelembapan lalu diabaikan.

**Insiden dan notifikasi**
- Insiden dibuka setelah **2 pembacaan berturut-turut** bahaya, supaya satu pembacaan DHT22 yang meleset tidak memicu alarm. Jeda antar pembacaan lebih dari 2 menit memutus rangkaian.
- **Satu notifikasi per insiden**; tidak ada notifikasi berulang selama suhu masih tinggi. Ditutup setelah 2 pembacaan berturut-turut tidak bahaya, tanpa notifikasi.
- Satu zona bisa punya beberapa insiden terbuka bersamaan bila penyebabnya berbeda (mis. suhu tinggi dan kelembapan rendah), masing-masing dengan notifikasinya.
- Sensor mati memakai alur yang sama (`sensor_offline`): dibuka oleh scheduler, tertutup otomatis begitu data masuk lagi.

**Dashboard (Blade + Tailwind + Chart.js)**
- Banner kritis menempel di atas layar, tombol **Tandai Telah Ditangani** mencatat siapa dan kapan (tidak menutup insiden); setelah ditandai banner menjadi "Sedang ditangani oleh {nama}".
- Satu kartu per zona dengan badge status berdenyut untuk BAHAYA, suhu besar, batas `(Max: …)`, panah naik/turun, kelembapan, dan "update X detik lalu". Penyebab kelembapan ikut disorot merah.
- Grafik tren 24 jam dengan garis batas dan chip filter zona/metrik yang bisa digeser di HP.
- Di HP kartu bertumpuk vertikal dengan zona bahaya otomatis di paling atas; tombol aksi selebar layar.
- Polling tiap 15 detik; data awal disisipkan di halaman sehingga tampil langsung tanpa menunggu fetch.
- Lonceng notifikasi dengan jumlah belum dibaca, halaman notifikasi, dan riwayat insiden (tabel di desktop, daftar kartu di HP).
- Login wajib untuk semua halaman; **tidak ada halaman pendaftaran publik** (user dibuat lewat `php artisan user:create`).

**Firmware**
- Modul terpisah: `SensorReader`, `WifiConnection`, `TelemetryClient`, loop berbasis `millis()`.
- Pembacaan NaN atau di luar rentang tidak dikirim; konfigurasi di `config.h` (di-gitignore) dengan contoh `config.example.h`.

**Batas Zona (menu `/zones`)**: semua pengguna yang sudah masuk dapat mengubah batas suhu, batas kelembapan, dan margin Waspada per zona; berlaku pada pembacaan berikutnya tanpa mengunggah ulang firmware. Validasi: maksimum harus lebih besar dari minimum, kelembapan diisi kedua-duanya atau dikosongkan, margin tidak boleh menghabiskan seluruh rentang, koma diterima sebagai pemisah desimal. Nama zona, `device_id`, dan token tidak bisa diubah dari halaman ini.

**Log Perubahan (menu `/audit-logs`)**: setiap perubahan Batas Zona dicatat (siapa, kapan, zona, nilai lama → baru). Catatan ditulis satu transaksi dengan perubahannya (gagal mencatat = perubahan dibatalkan), tidak dibuat bila tidak ada nilai yang berubah, dan nama pengguna/zona disalin sehingga riwayat tetap terbaca setelah akun atau zona dihapus. Perubahan lewat database, seeder, atau `tinker` tidak tercatat. Jenis perubahan baru ditambahkan lewat `AuditLog::EVENTS` dan `AuditLogger`.

## 5. Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Seluruh tes otomatis | 130 lulus, 386 assertion (102 untuk versi 1, 28 untuk Batas Zona dan Log Perubahan) |
| Alur alert nyata (server lokal + MySQL + curl) | Pembacaan bahaya ke-1: tidak ada insiden. Ke-2: 1 insiden + 2 notifikasi (2 user). 3 pembacaan bahaya berikutnya: tidak ada notifikasi baru, puncak 30,5. 2 pembacaan normal: insiden tertutup tanpa notifikasi. |
| Sensor offline sungguhan | Setelah 160 detik tanpa data, `schedule:work` membuka insiden `sensor_offline` + 2 notifikasi; banner offline muncul di dashboard |
| Tampilan | Diperiksa di browser pada lebar desktop dan 375px: banner menempel, zona bahaya di atas, tombol aksi bekerja, tidak ada scroll horizontal |
| Kontrak API dengan payload firmware (curl) | 201 dengan token, 401 tanpa token, 422 untuk kelembapan 150 |
| Review akhir (reviewer independen, model Opus) | Tidak ada temuan Critical atau Important; kelima fokus review lolos |

## 6. Perbaikan setelah review akhir

1. **Tautan notifikasi**: notifikasi buatan scheduler menunjuk ke `localhost` (dari `APP_URL`). Sekarang disimpan relatif dan URL lengkap dibentuk saat tampil, sehingga mengikuti alamat yang dipakai pembaca.
2. **Penyebab kelembapan**: bahaya karena kelembapan sekarang disorot di kartu zona (field `humidity_trend` pada data dashboard).

Keduanya diperbaiki dengan tes yang gagal dulu lalu lulus.

## 7. Keputusan yang saya ambil sendiri

| Keputusan | Alasan |
|---|---|
| PHP 8.4.27 yang sudah ada di Laragon dipakai, tidak ada yang diunduh | Sudah terpasang dengan semua ekstensi yang dibutuhkan |
| Repo git dibuat langsung di folder proyek, branch `main` | Folder masih kosong dan Anda meminta proyek dibuat di situ |
| Laravel Sanctum yang terpasang otomatis oleh `install:api` dihapus | Token perangkat memakai hash sendiri, Sanctum tidak terpakai |
| Tabel `notifications` ditambahkan di Task 2 | Rencana awal melewatkannya padahal dibutuhkan notifikasi |
| `device_id` kosong atau hilang menghasilkan 422, bukan 401 | Sesuai dataset tes di rencana; hanya `device_id` milik zona lain yang 401 |
| Tautan notifikasi memakai `url('/dashboard')` lalu diubah menjadi relatif | Rute bernama belum ada saat notifikasi dibuat; belakangan diperbaiki (bagian 6) |
| Logika dashboard dipindah ke `DashboardSnapshot` | Dipakai bersama oleh render awal halaman dan endpoint polling |
| Nilai suhu/kelembapan di model memakai cast `float`, kolom database tetap `decimal(5,2)` | Perbandingan angka lebih sederhana |
| Tes memanggil `withoutVite()`; font Bunny dihapus dari `vite.config.js` | Tes tidak bergantung pada `npm run build`; build tidak butuh internet |
| `tests/Feature/ExampleTest.php` dan `welcome.blade.php` bawaan dihapus | Menguji `/` yang kini menjadi redirect ke dashboard |
| Server uji dijalankan di port 8123, bukan 8000 | Port 8000 dipakai aplikasi `crm-tools` milik Anda; aplikasi itu tidak disentuh |

## 8. Temuan kecil yang ditunda

- Kemungkinan alert offline palsu dalam jeda beberapa milidetik saat sensor baru pulih (perlu pengecekan ulang di dalam lock).
- Perlu index `(device_id, id)` pada `sensor_logs` bila tabel sudah sangat besar.
- Daftar "Insiden terbaru" di dashboard belum ikut diperbarui oleh polling.
- Banner kritis menampilkan nilai puncak tanpa label "puncak".
- Batas kelembapan satu sisi (hanya min atau hanya max) membuat status Waspada dan teks RH tidak konsisten.
- `throttle:telemetry` berjalan setelah autentikasi perangkat, sehingga token salah tidak kena batas laju.
- `started_at` insiden sedikit lebih lambat dari kejadian sebenarnya (sekitar 30 detik untuk bahaya, sekitar 2 menit untuk offline).
- Satu baris kode mati di `ZoneReadingsController`.
- `php artisan db:seed` menimpa batas zona dan kata sandi admin (belum tercantum di README).
- Kegagalan tombol "Tandai Telah Ditangani" belum ditampilkan ke pengguna; grafik belum menampilkan celah saat sensor mati lama.
- Firmware: pembacaan DHT22 pertama sesaat setelah `begin()` biasanya NaN; `WiFi.disconnect()` dipanggil tiap percobaan sambung ulang.

## 9. Batasan versi 1

HTTP tanpa HTTPS (jaringan lokal), batas zona bisa diubah dari dashboard tetapi nama zona, perangkat, dan token belum punya layar admin, semua user setara (tanpa peran, jadi siapa pun yang masuk boleh mengubah batas), tanpa antrean offline di firmware, notifikasi hanya di dalam aplikasi, nilai batas zona di seeder hanya **contoh** dan harus disesuaikan.

## 10. Hal yang masih perlu Anda lakukan

1. **Kompilasi dan uji firmware di ESP32 asli.** Komputer pengembangan tidak punya `arduino-cli`. Langkahnya ada di `firmware/esp32-telemetry/README.md`.
2. **Sesuaikan batas suhu dan kelembapan** tiap zona dengan kebutuhan penyimpanan sebenarnya, lewat menu **Batas Zona** di dashboard.
3. **Ganti data uji**: database lokal `iot_gmi` berisi data demo dan akun `budi@example.com` / `sari@example.com` (kata sandi `password123`). Untuk pemakaian sungguhan jalankan `php artisan migrate:fresh --seed`, lalu `php artisan user:create`.
4. Di server produksi, **pastikan `schedule:work` atau cron tiap menit berjalan**; tanpanya sensor mati tidak akan pernah ditandai offline (dashboard menampilkan peringatan bila pemeriksaan berhenti lebih dari 5 menit).

## 11. Cara menjalankan

```bash
composer install
cp .env.example .env            # atur DB_*, ADMIN_EMAIL, ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed
npm ci && npm run build
php artisan user:create "Nama" email@contoh.com
php artisan zone:token gudang-zona-a
php artisan serve --host=0.0.0.0 --port=8123   # web + API
php artisan schedule:work                        # wajib: deteksi sensor offline
php artisan test                                 # 130 tes
```

Dokumentasi lengkap ada di `README.md`.

## 12. Riwayat commit

| Commit | Isi |
|---|---|
| `b5753cd` | Scaffold Laravel 13 dan dokumen rancangan |
| `f272026` | Skema, enum, model, seeder |
| `4b62ee3` | Penilai status zona |
| `6a88316` | Endpoint telemetry dan autentikasi perangkat |
| `5bc36b3` | Siklus insiden dan notifikasi |
| `1416869` | Deteksi sensor offline lewat scheduler |
| `b03e89b` | Login dan manajemen user |
| `c5f813a` | Endpoint dashboard, grafik, dan tombol tandai ditangani |
| `7c4b7a3` | Tampilan dashboard |
| `dd29785` | Notifikasi dan riwayat insiden |
| `17ecea7` | Firmware ESP32 |
| `bdf54d6` | README |
| `5df6cce` | Perbaikan hasil review (tautan notifikasi, sorotan kelembapan) |
| `efffafa` | Halaman Batas Zona |
| `6bb6abf` | Log Perubahan (audit) untuk Batas Zona |

## 13. Dokumen terkait

- Rancangan: `docs/superpowers/specs/2026-11-09-sistem-pemantauan-suhu-design.md`
- Rencana implementasi: `docs/superpowers/plans/2026-11-09-sistem-pemantauan-suhu.md`
- Panduan firmware: `firmware/esp32-telemetry/README.md`

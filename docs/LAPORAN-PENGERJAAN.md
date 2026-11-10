# Laporan Pengerjaan: Sistem Pemantauan Suhu & Kelembapan Gudang Produk Segar

Tanggal: 10 November 2026
Lokasi proyek: `D:\GMI\PROJECT\super-app-gmi\iot-gmi`
Repo tujuan: `git@github.com:faizrahmathidayat/sistem-pemantauan-suhu.git`, branch `main`.
Status git: commit fitur Laporan, data demo, dan laporan ini sudah ada di `main` lokal tetapi **belum di-push**; semua sebelumnya sudah di-push.

## 1. Ringkasan

Sistem pemantauan suhu dan kelembapan gudang telur, buah, dan sayur berbasis IoT dibangun dari awal dan terus dikembangkan: firmware ESP32 + DHT22, API Laravel 13 (PHP 8.4), penilaian ambang batas otomatis, notifikasi in-app, dashboard web responsif, **hak akses per role**, **multi-gudang** (Gudang → Zona → Produk), dan **laporan riwayat** dengan ekspor CSV.

| Hal | Status |
|---|---|
| Backend Laravel 13 / PHP 8.4 | Selesai. **345 tes** (344 lulus, 1 sengaja dilewati di SQLite), 18.554 assertion |
| Dashboard web (desktop dan HP), insiden, notifikasi | Selesai, diperiksa di browser |
| Deteksi sensor offline | Selesai, diuji dengan scheduler sungguhan |
| Halaman Batas Zona dan Log Perubahan (audit) | Selesai |
| Hak akses per role (Administrator, Supervisor, Operator, role buatan sendiri) | Selesai |
| Multi-gudang: gudang, zona, produk, user di-assign per gudang | Selesai, diperiksa di browser dengan MySQL |
| Laporan: ringkasan, grafik rentang bebas, ekspor CSV, cetak PDF lewat browser | Selesai, diperiksa di browser dengan MySQL dan 518 ribu baris data |
| Data demo (`demo:seed`, `demo:simulate`, `demo:clear`) | Selesai, sudah ada di database dev |
| Firmware ESP32 | Selesai ditulis, **belum dikompilasi dan belum diuji di perangkat asli** |
| Notifikasi WhatsApp / Telegram, laporan harian otomatis | Belum (ditunda; struktur notifikasi sudah siap) |

Perkembangan jumlah tes: 130 (setelah Batas Zona dan Log Perubahan), 261 (setelah hak akses dan multi-gudang), 335 (setelah Laporan dan perbaikan review), 345 (setelah data demo).

## 2. Permintaan dan realisasinya

| Urutan | Permintaan | Realisasi |
|---|---|---|
| 1 | ESP32 + DHT22 mengirim `device_id`, `temperature`, `humidity` via HTTP POST JSON; threshold alert; dashboard | Versi 1 (bagian 4.1) |
| 2 | Konfirmasi alat yang dibeli | ESP32 DevKit 30-pin (USB-C) dan modul DHT22 3-pin dengan kabel jumper betina. Wiring: `+` ke 3V3, `out` ke GPIO4 (D4), `-` ke GND |
| 3 | Batas suhu dan kelembapan bisa diatur, tidak hardcode | Halaman **Batas Zona** (4.2) |
| 4 | "Logs siapa saja yang sudah mengubah data" | **Log Perubahan** (4.3) |
| 5 | Modul hak akses, "hak akses per role" | **Role dan izin** (4.4) |
| 6 | Banyak gudang dan banyak zona (contoh: Gudang Cikokol (Telur) dengan Zona Inbound, FG, Loading); user di-assign ke gudang; produk per zona | **Multi-gudang** (4.5) |
| 7 | Laporan dan riwayat (rentang bebas, ringkasan, ekspor); laporan harian otomatis **ditunda** | **Laporan** (4.6) |
| 8 | Data dummy | **Data demo** (4.7) |

Permintaan awal dan pemetaannya ke kode (versi 1):

| Permintaan | Realisasi |
|---|---|
| Migration dan model `sensor_logs` | Ada, ditambah tabel `zones`, `incidents`, `notifications` |
| `SensorController` invokable + Form Request | `app/Http/Controllers/Api/V1/SensorController.php` + `StoreSensorLogRequest` |
| Pengecekan batas aman memicu alert otomatis | `ZoneStatusEvaluator` + `IncidentManager` + notifikasi `IncidentOpened` |
| Route `routes/api.php` | `POST /api/v1/telemetry` (middleware `device` dan `throttle:telemetry`) |
| Fitur PHP 8.4 (constructor promotion, typed property, enum, arrow function) | Dipakai di service, notifikasi, enum, dan controller |
| Dashboard hijau/kuning/merah, grafik 24 jam, banner kritis, tombol "Tandai Telah Ditangani", riwayat insiden, mobile-friendly | Seluruhnya ada |
| Alert WhatsApp | Ditunda; notifikasi dipisah dari saluran pengiriman sehingga tinggal menambah `via()` |

## 3. Arsitektur

```
ESP32 + DHT22 --POST JSON (Bearer token)--> /api/v1/telemetry --> sensor_logs
                                                   |
                              IncidentManager (2 pembacaan berturut-turut)
                                                   |
            incidents + notifikasi database ke user berizin notifikasi di gudang zona itu
                                                   |
   Dashboard (chip gudang, kartu zona, grafik 24 jam, banner kritis)  ·  Laporan (riwayat, CSV)
   Scheduler tiap menit: deteksi sensor offline (zones:check-offline) dan pembersihan data 90 hari

   Data:    Gudang -> Zona (1 sensor + batas sendiri) -> Produk (nama saja)
   Akses:   User -> 1 Role (izin) + 1 atau lebih Gudang (data yang boleh dilihat)
```

Stack: Laravel 13, PHP 8.4.27, MySQL 8 (tes memakai SQLite in-memory), Blade + Tailwind 4 + Chart.js lewat Vite, Arduino C++ (Adafruit DHT, ArduinoJson 7).

## 4. Fitur yang dibangun

### 4.1 Versi 1: pemantauan, insiden, dashboard

**API dan keamanan perangkat**
- Token acak 48 karakter per zona, hanya hash sha256 yang disimpan. Token zona A dengan `device_id` zona B ditolak (401) dan tidak menyimpan apa pun.
- Validasi suhu −40..80 dan kelembapan 0..100; respons 201/401/422/429 (maks. 20 per menit per perangkat).

**Penilaian status**
- **Bahaya**: di luar rentang min..max (tepat di batas belum bahaya). **Waspada**: di dalam rentang tetapi dekat batas (margin suhu 2 °C, kelembapan 5 poin). **Offline**: tidak ada data lebih dari 2 menit. **Belum ada data**: zona yang belum pernah mengirim.
- Batas kelembapan boleh dikosongkan; kelembapan lalu diabaikan.

**Insiden dan notifikasi**
- Insiden dibuka setelah **2 pembacaan berturut-turut** bahaya, sehingga satu pembacaan DHT22 yang meleset tidak memicu alarm. Jeda antar pembacaan lebih dari 2 menit memutus rangkaian.
- **Satu notifikasi per insiden**, tidak berulang selama suhu masih tinggi. Insiden ditutup setelah 2 pembacaan berturut-turut tidak bahaya, tanpa notifikasi.
- Satu zona bisa punya beberapa insiden terbuka bersamaan bila penyebabnya berbeda, masing-masing dengan notifikasinya.
- Sensor mati memakai alur yang sama (`sensor_offline`): dibuka oleh scheduler, tertutup otomatis begitu data masuk lagi.

**Dashboard**
- Banner kritis menempel di atas layar; tombol **Tandai Telah Ditangani** mencatat siapa dan kapan (tidak menutup insiden).
- Satu kartu per zona dengan badge status, suhu besar, batas, panah naik/turun, kelembapan, dan "update X detik lalu".
- Grafik tren 24 jam dengan garis batas dan chip filter zona dan metrik. Di HP kartu bertumpuk dengan zona bahaya paling atas.
- Polling tiap 15 detik; data awal disisipkan di halaman sehingga tampil langsung.
- Lonceng notifikasi, halaman notifikasi, dan riwayat insiden. Login wajib; tidak ada pendaftaran publik.

**Firmware** (`firmware/esp32-telemetry/`): modul `SensorReader`, `WifiConnection`, `TelemetryClient`, loop berbasis `millis()`, interval 30 detik. Pembacaan NaN atau di luar rentang tidak dikirim. Konfigurasi di `config.h` (di-gitignore) dengan contoh `config.example.h`.

### 4.2 Batas Zona (`/zones`)

Batas suhu, batas kelembapan, dan margin Waspada per zona bisa diubah dari dashboard (izin `zone.update`) dan langsung berlaku pada pembacaan berikutnya, tanpa mengunggah ulang firmware. Validasi: maksimum lebih besar dari minimum, kelembapan diisi kedua-duanya atau dikosongkan, margin tidak menghabiskan seluruh rentang, koma diterima sebagai pemisah desimal.

### 4.3 Log Perubahan (`/audit-logs`)

Setiap perubahan tercatat: siapa, kapan, apa, nilai lama → baru. Dicatat dalam satu transaksi dengan perubahannya (gagal mencatat = perubahan dibatalkan), tidak dibuat bila tidak ada nilai yang berubah, dan nama pengguna dan objek disalin sehingga riwayat tetap terbaca setelah akun atau zona dihapus. Kata sandi dan token tidak pernah dicatat (hanya "diubah"). Cakupan sekarang: batas zona, role, user, gudang, dan zona (dibuat, diubah, dihapus, token diputar). Perubahan langsung lewat database atau `tinker` tidak tercatat.

### 4.4 Hak akses per role

- Setiap user punya **satu role**; role menentukan fitur apa yang boleh dipakai, assignment gudang menentukan di gudang mana.
- **12 izin** yang menjaga route, menu, dan penerimaan alert: `dashboard.view`, `incident.view`, `incident.acknowledge`, `notification.view`, `report.view`, `warehouse.manage`, `zone.view`, `zone.update`, `zone.manage`, `auditlog.view`, `user.manage`, `role.manage`.
- Role **Administrator** (sistem): selalu semua izin, tidak bisa diubah atau dihapus. Role awal **Supervisor** dan **Operator**; role lain dibuat di menu **Role**.
- Aturan: selalu tersisa minimal satu Administrator; hanya Administrator yang boleh memberi role Administrator atau mengubah akun Administrator; role yang masih dipakai tidak bisa dihapus; user tanpa role hanya melihat halaman "belum punya hak akses"; pembuatan dan perubahan user serta role tercatat di Log Perubahan.
- Pemegang `user.manage` non-Administrator hanya boleh meng-assign gudang yang ia punya, dan hanya melihat atau mengubah user yang seluruh gudangnya termasuk gudangnya (supaya tidak bisa mengganti kata sandi user lain lalu masuk sebagai dia).
- `php artisan user:create "Nama" email --role=Operator`; instalasi lama yang di-`migrate` menjadikan semua user yang ada sebagai Administrator.

### 4.5 Multi-gudang (Gudang → Zona → Produk)

- **Gudang** hanya punya nama. **Zona** milik satu gudang: satu sensor (`device_id` + token) dan batas sendiri; satu sensor mengawasi semua produk di zona itu. **Produk** hanya daftar nama (tanpa stok).
- User di-assign ke satu atau lebih gudang dan hanya melihat serta menerima alert gudang itu. Administrator melihat semua gudang.
- Menu **Gudang** dan halaman Kelola zona: tambah gudang dan zona, edit produk, buat token sensor (tampil sekali; buat token baru bila hilang), hapus zona (menghapus seluruh pembacaan, insiden, produk setelah konfirmasi) dan gudang (hanya bila kosong).
- Dashboard: chip gudang dengan status terburuk dan jumlah insiden terbuka, pilihan tersimpan di `?gudang=`, kartu zona dengan produk, notifikasi berjudul "… di Gudang › Zona".
- Migrasi data: zona lama masuk ke "Gudang Utama", jenis produk lama (Telur/Buah/Sayur) menjadi satu produk per zona, user non-Administrator di-assign ke gudang itu; sensor, token, insiden, dan riwayat tetap utuh.
- Data contoh seeder: Gudang Cikokol (Telur) dengan zona Inbound, FG, Loading (batas contoh 10–20 °C dan 60–85 %; **placeholder**, sesuaikan).

### 4.6 Laporan (`/reports`, izin `report.view`)

- **Filter:** gudang, zona (untuk grafik dan data mentah), periode (24 jam, 7 hari bawaan, 30 hari, 90 hari, atau tanggal sendiri). Maksimal 90 hari karena data hanya disimpan 90 hari. Pilihan tersimpan di alamat halaman.
- **Ringkasan per zona:** suhu dan kelembapan terendah/tertinggi/rata-rata, jumlah pembacaan, jumlah insiden (tanpa sensor offline), lama insiden, lama sensor offline, dan persen pembacaan dalam batas (terhadap batas saat ini). Insiden yang melewati batas periode dipotong dan insiden yang tumpang tindih dihitung sekali.
- **Grafik** zona terpilih: garis rata-rata, terendah, tertinggi per interval (otomatis 5 menit sampai 12 jam, sekitar 300 titik atau kurang) plus garis batas zona, dan daftar insiden zona itu.
- **Ekspor CSV:** ringkasan semua zona di gudang dan data mentah satu zona (dialirkan bertahap, UTF-8 dengan BOM, pemisah koma). Nama berawalan `=`, `+`, `-`, `@` diamankan dari formula injection di Excel.
- **Cetak / Simpan PDF** lewat fitur cetak browser (menu dan formulir disembunyikan, judul memuat gudang, zona, periode, pencetak, waktu).
- Gudang atau zona di luar jangkauan user ditolak (403) di halaman maupun kedua unduhan; id yang tidak ada 404; isian salah (mis. tanggal) menampilkan pesan atau 422 untuk unduhan.
- Migrasi memberi `report.view` ke role yang sudah punya `dashboard.view`.

### 4.7 Data demo

`php artisan demo:seed` membuat "Gudang Demo" terpisah: 3 zona (`demo-*`), riwayat pembacaan tiap 15 menit (bawaan 30 hari, maks. 90) dan insiden yang cocok dengan datanya (lonjakan suhu, lonjakan kelembapan, sensor mati). `demo:simulate` mengirim pembacaan palsu tiap 30 detik lewat jalur yang sama dengan API supaya zona demo tidak offline. `demo:clear` menghapus semuanya tanpa menyentuh data asli. Saat dibuat di database dev: 3 zona, 8.637 pembacaan, 6 insiden.

## 5. Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Seluruh tes otomatis | 345 tes, 344 lulus, 1 sengaja dilewati di SQLite, 18.554 assertion; kode baru bersih di Pint (satu berkas lama, `IncidentLifecycleTest.php`, sudah punya selisih urutan import sejak versi 1 dan tidak disentuh) |
| Alur alert nyata (server lokal, MySQL, curl; versi 1) | Pembacaan bahaya ke-1: tidak ada insiden. Ke-2: 1 insiden + notifikasi. 3 pembacaan bahaya berikutnya: tidak ada notifikasi baru. 2 pembacaan normal: insiden tertutup tanpa notifikasi |
| Sensor offline sungguhan | Setelah 160 detik tanpa data, `schedule:work` membuka insiden `sensor_offline` dan banner offline muncul |
| Tampilan dashboard | Desktop dan 375 px: banner menempel, zona bahaya di atas, tidak ada scroll horizontal |
| Multi-gudang di MySQL dev | User operator terbatas hanya melihat gudangnya; token tampil sekali; telemetry 201 dengan token baru dan 401 setelah zona dihapus; migrasi mempertahankan 3 zona, 2 insiden, 8 pembacaan |
| Laporan di MySQL dev (browser) | Ringkasan, grafik tiga garis, kedua CSV, 422 untuk periode salah; operator gudang lain mendapat 403 untuk gudang, zona, dan kedua unduhan |
| Kinerja Laporan pada 518.400 pembacaan (2 perangkat × 90 hari) | Ekspor mentah 256.321 baris: 5,3 detik, memori puncak 40 MB. Ringkasan 90 hari 0,7 detik, grafik 0,4 detik, ekspor mentah 24 jam 0,1 detik |
| Review akhir independen (model Opus) | Dilakukan untuk setiap proyek (hak akses, multi-gudang, laporan); temuan di bagian 6 |

Data uji sementara yang dipakai saat verifikasi (gudang, zona, pembacaan, user uji, notifikasi) dihapus setelahnya; jumlah baris tabel dicek kembali seperti semula.

## 6. Hasil review akhir dan perbaikannya

Semua perbaikan memakai tes yang gagal dulu lalu lulus.

**Versi 1**
1. Tautan notifikasi buatan scheduler menunjuk ke `localhost`; sekarang disimpan relatif dan URL dibentuk saat tampil.
2. Bahaya karena kelembapan sekarang disorot di kartu zona.

**Hak akses**
3. Pemegang `user.manage` bisa menaikkan hak (memberi diri atau orang lain role Administrator); ditutup.

**Multi-gudang**
4. Pemegang `user.manage` non-Administrator bisa mengambil alih user di gudang lain (mengganti kata sandinya); sekarang hanya user yang seluruh gudangnya termasuk gudangnya.
5. Rollback migrasi di MySQL gagal (error 1553, FK memakai indeks unik); FK sekarang dilepas lebih dulu.
6. Halaman token zona bisa tersimpan di cache; sekarang `Cache-Control: no-store, private`.
7. Role dengan `zone.manage` saja tidak punya jalan di UI untuk membuat zona; ditambahkan.

**Laporan**
8. Kolom "Insiden" menghitung sensor offline padahal lama insidennya tidak; sekarang offline punya kolomnya sendiri.
9. Label grafik memakai zona waktu browser (pengguna WITA/WIT melihat jam bergeser); sekarang zona waktu aplikasi.
10. Judul cetak belum memuat zona; ditambahkan. Tiga tes yang tidak mungkin gagal dipertegas.

Satu temuan "penting" tidak diubah setelah diukur: kekhawatiran ekspor mentah 90 hari lambat ternyata hanya 5,3 detik (lihat bagian 5).

## 7. Keputusan yang saya ambil sendiri

**Versi 1**

| Keputusan | Alasan |
|---|---|
| PHP 8.4.27 dari Laragon dipakai | Sudah terpasang dengan ekstensi yang dibutuhkan |
| Repo git dibuat di folder proyek, branch `main` | Folder masih kosong |
| Laravel Sanctum dihapus | Token perangkat memakai hash sendiri |
| Tabel `notifications` ditambahkan | Rencana awal melewatkannya |
| `device_id` kosong atau hilang menghasilkan 422, bukan 401 | Sesuai dataset tes di rencana |
| Logika dashboard dipindah ke `DashboardSnapshot` | Dipakai bersama render awal dan endpoint polling |
| Tes memanggil `withoutVite()`; font Bunny dihapus | Tes tidak bergantung pada `npm run build`; build tidak butuh internet |
| Server uji di port 8123 | Port 8000 dipakai aplikasi lain milik Anda yang tidak disentuh |

**Hak akses dan multi-gudang**

| Keputusan | Alasan |
|---|---|
| Hak akses dibuat sendiri (enum + tabel pemetaan), tanpa paket | Izin kecil dan tetap di kode; yang dinamis hanya pemetaan role → izin |
| Satu role per user, berlaku di semua gudangnya | Sederhana; role per gudang tercatat sebagai pengembangan nanti |
| Administrator melihat semua gudang tanpa di-assign | Mencegah administrator terkunci |
| Pembuat gudang non-Administrator otomatis di-assign | Agar tidak kehilangan gudang yang baru dibuat |
| `device_id` zona **tidak bisa diubah** setelah dibuat (rancangan menyebutnya) | Butuh penulisan ulang `sensor_logs` secara transaksional dan perubahan firmware; README mendokumentasikannya |
| `zones.warehouse_id` boleh NULL di tingkat database; FK `restrict` hanya di non-SQLite | Di SQLite penambahan FK membangun ulang tabel dan cascade menghapus insiden dan produk; "wajib" ditegakkan di aplikasi |
| Menghapus zona menghapus seluruh datanya setelah konfirmasi | Konsisten dengan hapus gudang hanya bila kosong |
| Log Perubahan tetap global (bukan per gudang) | Keterlacakan lintas gudang |
| Pemegang `role.manage` tetap bisa memberi role-nya sendiri izin apa pun (kecuali Administrator) | Didokumentasikan sebagai izin berdaya besar |

**Laporan dan data demo**

| Keputusan | Alasan |
|---|---|
| Izin baru `report.view`, terpisah dari `dashboard.view` | Role bisa diatur boleh mengunduh data atau tidak |
| Tanggal awal rentang sendiri paling lama hari pertama yang seluruhnya di dalam "sekarang − 90 hari" (13 Agu bila hari ini 10 Nov) | Mengikuti batas pembersihan data; tidak ada aturan rentang terpisah |
| Ukuran bucket grafik ditulis langsung di SQL, bukan placeholder | MySQL (ONLY_FULL_GROUP_BY) menolak ekspresi ber-placeholder yang berbeda di SELECT dan GROUP BY |
| Ekspresi bucket memakai selisih detik dari titik tetap, bukan `UNIX_TIMESTAMP` | Tidak bergantung zona waktu sesi database |
| CSV standar (koma, titik desimal) | Alternatif `;` dengan koma desimal merusak alat lain; Excel Indonesia dibuka lewat Data → From Text/CSV |
| PDF lewat cetak browser | Menghindari dependensi pustaka PDF |
| Data demo di gudang `Gudang Demo` terpisah | Tidak mencemari data zona asli saat sensor sungguhan terpasang |

## 8. Temuan kecil yang ditunda

**Versi 1**
- Kemungkinan alert offline palsu dalam jeda beberapa milidetik saat sensor baru pulih.
- Perlu index `(device_id, id)` pada `sensor_logs` bila tabel sangat besar (pada pengukuran 518 ribu baris belum perlu).
- Daftar "Insiden terbaru" di dashboard belum ikut diperbarui oleh polling.
- Banner kritis menampilkan nilai puncak tanpa label "puncak".
- Batas kelembapan satu sisi (hanya min atau hanya max) membuat status Waspada dan teks RH tidak konsisten.
- `throttle:telemetry` berjalan setelah autentikasi perangkat, jadi token salah tidak kena batas laju.
- `started_at` insiden sedikit lebih lambat dari kejadian sebenarnya (sekitar 30 detik untuk bahaya, sekitar 2 menit untuk offline).
- `php artisan db:seed` menimpa batas zona dan kata sandi admin.
- Kegagalan tombol "Tandai Telah Ditangani" belum ditampilkan; grafik belum menampilkan celah saat sensor mati lama.
- Firmware: pembacaan DHT22 pertama setelah `begin()` biasanya NaN; `WiFi.disconnect()` dipanggil tiap percobaan sambung ulang.

**Hak akses dan multi-gudang**
- Pengecekan "Administrator terakhir" belum atomik (balapan teoretis); beberapa tautan tampil walau mengarah ke 403; menurunkan role sendiri berujung ke halaman 403; migrasi hak akses memakai Eloquent; daftar landing belum memuat role yang hanya `incident.acknowledge`; nama pada dialog konfirmasi ter-escape dua kali.
- Notifikasi tersimpan tetap terlihat setelah user tidak lagi di-assign ke gudangnya; pembacaan yatim bisa tersisa bila telemetry berbarengan dengan penghapusan zona; `CheckOfflineZones` bisa berhenti di tengah bila zona terhapus; notifikasi yatim setelah zona dihapus; token mentah tersimpan di session sampai ditampilkan; pesan validasi mengungkap apakah id gudang ada; `down()` migrasi tidak memulihkan data; migrasi tidak atomik di MySQL.

**Laporan**
- Zona tanpa gudang (NULL) yang diminta Administrator diberi label gudang pertama.
- `down()` migrasi izin menghapus semua `report.view`, termasuk yang dibuat role awal.
- Grafik dashboard lama juga memakai zona waktu browser.
- Belum ada tes tingkat database untuk pembacaan tepat pukul 23:59:59 di tanggal akhir.
- Ekspor tidak dibatasi laju dan ringkasan menjalankan dua kueri per zona (cukup untuk skala sekarang).

## 9. Batasan saat ini

HTTP tanpa HTTPS (jaringan lokal); satu role per user (berlaku di semua gudangnya); produk hanya nama; `device_id` zona tidak bisa diubah setelah dibuat; data sensor hanya 90 hari (unduh CSV berkala bila perlu arsip); notifikasi hanya di dalam aplikasi; belum ada laporan harian otomatis; tanpa antrean offline di firmware; nilai batas zona dari seeder hanya **contoh** dan harus disesuaikan.

## 10. Hal yang masih perlu Anda lakukan

1. **Kompilasi dan uji firmware di ESP32 asli** (belum pernah dikompilasi). Langkahnya di `firmware/esp32-telemetry/README.md`.
2. **Sesuaikan batas suhu dan kelembapan** tiap zona dengan kebutuhan penyimpanan sebenarnya (menu **Batas Zona**).
3. **Buat gudang, zona, dan token sensor asli** lewat menu **Gudang** (token tampil sekali; salin ke `config.h`), lalu assign user ke gudangnya lewat menu **User**.
4. **Bersihkan data uji dan demo** sebelum pemakaian sungguhan: `php artisan demo:clear`, lalu ganti atau hapus akun contoh dari versi 1 (`php artisan user:create`), atau mulai bersih dengan `php artisan migrate:fresh --seed`.
5. Di server produksi, **pastikan `schedule:work` atau cron tiap menit berjalan**; tanpanya sensor mati tidak pernah ditandai offline (dashboard memperingatkan bila pemeriksaan berhenti lebih dari 5 menit).
6. Memutuskan langkah berikut: notifikasi Telegram (di luar aplikasi), laporan harian otomatis, HTTPS dan backup untuk produksi.

## 11. Cara menjalankan

```bash
composer install
cp .env.example .env            # atur DB_*, ADMIN_EMAIL, ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed
npm ci && npm run build
php artisan user:create "Nama" email@contoh.com --role=Administrator
php artisan serve --host=0.0.0.0 --port=8000    # web + API
php artisan schedule:work                       # wajib: deteksi sensor offline
php artisan demo:seed                           # opsional: data demo
php artisan demo:simulate                       # opsional: sensor palsu (Ctrl+C untuk berhenti)
php artisan test                                # 345 tes
```

Dokumentasi lengkap ada di `README.md`.

## 12. Riwayat commit (41 commit)

| Commit | Isi |
|---|---|
| `b5753cd` | Scaffold Laravel 13 dan dokumen rancangan |
| `f272026` | Skema, enum, model, seeder |
| `4b62ee3` | Penilai status zona |
| `6a88316` | Endpoint telemetry dan autentikasi perangkat |
| `5bc36b3` | Siklus insiden dan notifikasi |
| `1416869` | Deteksi sensor offline lewat scheduler |
| `b03e89b` | Login dan manajemen user |
| `c5f813a` | Endpoint dashboard, grafik, tombol tandai ditangani |
| `7c4b7a3` | Tampilan dashboard |
| `dd29785` | Notifikasi dan riwayat insiden |
| `17ecea7` | Firmware ESP32 |
| `bdf54d6` | README |
| `5df6cce` | Perbaikan review (tautan notifikasi, sorotan kelembapan) |
| `efffafa` | Halaman Batas Zona |
| `6bb6abf` | Log Perubahan (audit) |
| `f9bede2` | Laporan pengerjaan versi pertama |
| `77599f9`, `6694a18` | Rancangan dan rencana modul hak akses |
| `70b4111` | Role, izin, pemeriksaan hak akses (skema dan model) |
| `2951348` | Izin menjaga route, menu, alert, halaman awal |
| `0857c17` | Pengelolaan role dan audit role |
| `073528c` | Pengelolaan user, perlindungan Administrator, `user:create --role` |
| `e53313d` | Perbaikan review: cegah naik hak lewat `user.manage` |
| `922a227`, `3a04387` | Rancangan dan rencana multi-gudang |
| `436579a` | Skema gudang, zona, produk, dan migrasi data |
| `f624d2f` | Pembatasan data per gudang |
| `dab4ff4` | Dashboard multi-gudang |
| `5bcfcf8` | Pengelolaan gudang dan izin `warehouse.manage`/`zone.manage` |
| `9a95f73` | Pengelolaan zona, produk, token sensor dari aplikasi |
| `0bd2d85` | Assignment user ke gudang dan dokumentasi |
| `e0bcfe5` | Perbaikan review: tutup ambil-alih user lintas gudang |
| `a50a9a1` | Rancangan dan rencana Riwayat & Laporan |
| `68c252b` | Izin `report.view` |
| `fbeb4f0` | Periode dan filter laporan |
| `6931b30` | Perhitungan ringkasan, deret waktu, insiden |
| `937892c` | Halaman laporan |
| `51fa5ce` | Ekspor CSV |
| `268f2bc` | Grafik, tampilan cetak, dokumentasi |
| `23c26ad` | Perbaikan review akhir laporan |
| `8e9c7de` | Perintah data demo |

(Commit pembaruan laporan ini menyusul setelahnya.)

## 13. Dokumen terkait

- Rancangan: `docs/superpowers/specs/` (sistem pemantauan suhu, hak akses, multi-gudang, riwayat & laporan)
- Rencana implementasi: `docs/superpowers/plans/` (keempatnya)
- Panduan firmware: `firmware/esp32-telemetry/README.md`
- Panduan pemakaian: `README.md`

## 15. Perombakan tampilan gaya TailAdmin (10 Nov 2026)

Seluruh antarmuka digambar ulang dengan pola TailAdmin tanpa mengubah backend (rute, izin, model, dan layanan tidak disentuh). Warna utama `#0a1f12` sesuai permintaan Anda.

| Bagian | Hasil |
|---|---|
| Layout | Sidebar kiri (panel geser di HP, Alpine.js), header menempel dengan lonceng notifikasi dan menu pengguna, banner kritis menempel di bawah header, tampilan cetak tanpa menu |
| Komponen | Ikon SVG buatan sendiri, kartu, badge status, header halaman dengan remah roti, alert, keadaan kosong, tabel, kelas formulir dan tombol seragam |
| Dashboard | Pilihan gudang (chip), kartu metrik per zona (badge hijau/kuning/merah berdenyut, suhu besar, batas, indikator tren, kelembapan, waktu pembaruan, produk), banner dengan tombol "Tandai Telah Ditangani" selebar layar di HP, grafik Chart.js dalam kartu |
| Halaman lain | Laporan, Riwayat Insiden, Notifikasi, Log Perubahan, Batas Zona, Gudang, Zona, User, dan Role: tabel di layar lebar dan daftar kartu di HP |
| Login, 403, belum ada akses | Login dua kolom (panel merek di layar lebar), halaman galat dengan komponen yang sama |

Verifikasi: 375 tes dan seluruhnya lulus kecuali 1 yang sengaja dilewati di SQLite (tambahan 30 tes untuk layout dan tiap kelompok halaman); semua halaman diperiksa di browser pada lebar 375 px dan 1440 px dengan data demo di MySQL (tanpa scroll mendatar, sidebar HP buka-tutup dengan tombol, Esc, dan klik latar, banner menempel saat gulir, tombol Tandai Telah Ditangani bekerja, operator hanya melihat menunya dan mendapat 403 untuk halaman lain). Akun uji sementara untuk pemeriksaan dihapus sesudahnya.

Keputusan: tabel baru tampil mulai lebar `lg` (4-5 kolom) atau `xl` (Riwayat Insiden dan ringkasan Laporan, 7-8 kolom) karena sidebar menyempitkan isi; di bawahnya daftar kartu. Seri grafik suhu memakai hijau `#4f7a5d` karena warna utama hampir hitam. Tidak ada mode gelap dan kolom pencarian.

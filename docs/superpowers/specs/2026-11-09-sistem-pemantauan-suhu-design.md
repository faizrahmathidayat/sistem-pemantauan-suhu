# Sistem Pemantauan Suhu & Kelembapan Gudang Produk Segar: Rancangan

Tanggal: 2026-11-09
Repo tujuan: `git@github.com:faizrahmathidayat/sistem-pemantauan-suhu.git`

Penanda: **[Anda]** = diputuskan pemilik proyek. **[Saya]** = keputusan penulis rancangan, belum disetujui siapa pun dan boleh dikoreksi.

## 1. Maksud dan alasan

Gudang menyimpan telur, buah, dan sayur. Produk ini rusak cepat kalau suhu atau kelembapan keluar dari rentang yang sesuai, dan kerusakan sering baru ketahuan setelah terlambat. Sistem ini mengukur suhu dan kelembapan tiap zona gudang secara terus-menerus, menampilkannya di dashboard web, dan memberi tahu semua user begitu ada zona yang keluar dari batas aman, sehingga orang di lapangan bisa bertindak sebelum produk rusak.

Ukuran keberhasilan **[Anda]**: dalam 3 detik pertama membuka dashboard, user tahu zona mana yang bermasalah, tanpa scroll, termasuk di HP.

## 2. Tujuan, bukan-tujuan, dan hal yang harus dihindari

**Tujuan**
- Satu ESP32 + DHT22 per zona mengirim data berkala ke backend Laravel.
- Backend menyimpan data, menilai statusnya (aman / waspada / bahaya), dan membuka insiden saat bahaya.
- Semua user terdaftar mendapat notifikasi di dalam aplikasi (lonceng + halaman notifikasi) **[Anda]**.
- Dashboard web responsif: kartu per zona, grafik 24 jam, banner kritis, log insiden **[Anda]**.

**Bukan-tujuan versi 1**
- Notifikasi WhatsApp. Arsitektur harus memungkinkan WhatsApp ditambahkan nanti sebagai saluran baru **[Anda]**.
- Peran/izin berbeda (petugas, supervisor, pemilik). Semua user setara **[Saya]**.
- Aplikasi mobile native, MQTT, WebSocket, OTA update firmware, banyak gudang **[Saya]**.
- Layar untuk mengelola zona, batas, dan perangkat. Di versi 1 semuanya lewat seeder/artisan/database **[Saya]**.

**Hal yang membuat sistem gagal walau secara teknis jalan**
- Alert berulang-ulang untuk satu kejadian yang sama (user jadi mengabaikan semua alert).
- Alert palsu karena satu pembacaan DHT22 yang meleset.
- Siapa pun bisa mengirim data palsu ke API atau mendaftar lalu melihat data gudang.
- Sensor mati diam-diam sehingga dashboard tetap tampak "aman" dengan angka lama (ditangani lewat insiden dan notifikasi sensor offline).

## 3. Batasan teknis

- Laravel 13, PHP 8.4 (constructor property promotion, typed properties, enum, readonly, arrow function bila cocok) **[Anda]**.
- ESP32 + DHT22, Arduino IDE / C++, kirim HTTP POST JSON `{device_id, temperature, humidity}` **[Anda]**.
- Tabel `sensor_logs`: id, device_id, temperature, humidity, timestamps **[Anda]**.
- `SensorController.php` berupa invokable action dengan Form Request **[Anda]**. Route di `routes/api.php` dengan gaya routing Laravel 13 **[Anda]**.
- Alert memakai sistem notifikasi bawaan Laravel, saluran `database` saja untuk sekarang **[Anda: hanya di aplikasi]**.
- Database MySQL (Laragon), test memakai SQLite in-memory. Tampilan Blade + Tailwind + Chart.js lewat Vite. Dashboard memperbarui diri dengan polling tiap 15 detik, tanpa WebSocket **[Saya]**.
- Zona waktu aplikasi `Asia/Jakarta` **[Saya]**.
- Notifikasi dikirim sinkron, tanpa queue worker, karena jumlah user kecil. Akun pertama dibuat seeder dari `ADMIN_EMAIL`/`ADMIN_PASSWORD` di `.env`, akun lain lewat `php artisan user:create`. Scheduler (pemeriksaan sensor offline tiap menit dan prune 90 hari) dijalankan dengan `php artisan schedule:work` atau cron; target pemakaian lokal/Laragon **[Saya]**.
- Lingkungan: komputer pengembang punya PHP 7.4, Composer 2.8, Node 24. PHP 8.4 harus dipasang dulu (lihat bagian 10).

## 4. Model data

| Tabel | Kolom penting |
|---|---|
| `zones` | id, name, product_type (enum: telur, buah, sayur), device_id (unik), device_token_hash (sha256, unik), temp_min, temp_max, humidity_min, humidity_max (kelembapan boleh null = tidak dicek), warning_margin (°C, default 2), humidity_warning_margin (poin persen, default 5), timestamps |
| `sensor_logs` | id, device_id (indeks), temperature, humidity, timestamps (indeks pada device_id + created_at) |
| `incidents` | id, zone_id, reason (enum: suhu_tinggi, suhu_rendah, lembap_tinggi, lembap_rendah, sensor_offline), started_at, ended_at (null = masih berlangsung), peak_value (nilai terburuk pada metrik `reason`; null untuk sensor_offline), acknowledged_by (user, null), acknowledged_at, timestamps. Satu zona hanya boleh punya satu insiden terbuka per `reason`. |
| `users`, `notifications` | bawaan Laravel (notifikasi `database`) |

Data `sensor_logs` yang lebih tua dari 90 hari dihapus otomatis (prunable + scheduler) **[Saya]**.

Nilai awal zona di seeder hanya contoh dan **harus dikoreksi pemilik**: Zona A Telur 10–20°C / RH 60–85%; Zona B Sayur Daun 15–25°C / RH 80–95%; Zona C Buah 8–13°C / RH 85–95%. Angka 25°C untuk sayur dan 28°C sebagai batas umum berasal dari contoh di permintaan awal.

## 5. API perangkat

`POST /api/v1/telemetry`, header `Authorization: Bearer <device token>`, body JSON:

```json
{ "device_id": "gudang-zona-a", "temperature": 18.2, "humidity": 62.4 }
```

- Form Request memvalidasi: device_id string wajib; temperature numerik −40 s/d 80; humidity numerik 0 s/d 100.
- Middleware menghitung sha256 token lalu mencari zona dengan hash itu (pencarian berindeks), kemudian memastikan `device_id` di body sama dengan zona tersebut. Gagal → 401. Data tak valid → 422. Berhasil → 201.
- Token dibuat dengan `php artisan zone:token {device_id}`: token acak panjang dicetak sekali, hash-nya disimpan, menjalankan ulang berarti rotasi token. Seeder hanya membuat zona; token tiap zona dibuat lewat perintah itu sesuai langkah di README **[Saya]**.
- Waktu data adalah waktu server saat menerima (`created_at`); perangkat tidak mengirim timestamp **[Saya]**.
- Dibatasi rate limit per perangkat. Detail angka diserahkan ke builder.
- Alur setelah simpan: `StoreSensorLogController` menyimpan log, lalu memanggil service `ThresholdEvaluator` yang menentukan status dan membuka atau menutup insiden. Controller tetap tipis.

## 6. Penilaian status dan insiden

Status zona dihitung dari pembacaan terbaru terhadap batas zona:
- **Bahaya**: suhu atau kelembapan di luar rentang `min`..`max`.
- **Waspada**: masih di dalam rentang tetapi dalam jarak `warning_margin` dari batas suhu, atau dalam jarak `humidity_warning_margin` dari batas kelembapan (bila kelembapan dicek) **[Saya: pakai selisih absolut, bukan persen, karena persen tidak masuk akal untuk rentang seperti 2–8°C]**.
- **Aman**: selain itu.
- **Offline**: pembacaan terakhir lebih tua dari 2 menit (4× interval kirim) **[Saya: angka 2 menit]**. Memicu insiden dan notifikasi **[Anda: sensor mati harus diberi tahu]**; aturannya di bawah.

Siklus insiden **[Saya, untuk mencegah alert palsu dan alert beruntun]**:
- Tidak ada penghitung tersimpan. Hitungan "berturut-turut" diturunkan dari 2 `sensor_logs` terakhir milik perangkat itu. Dua pembacaan hanya dianggap berturut-turut bila selisih waktunya paling lama 2 menit; jeda lebih lama dari itu memutus rangkaian.
- Insiden dibuka setelah **2 pembacaan berturut-turut** berstatus bahaya pada `reason` yang sama. Tepat saat dibuka, kirim satu notifikasi ke semua user.
- Selama insiden dengan `reason` itu terbuka tidak ada notifikasi tambahan, dan `peak_value` diperbarui bila nilai makin buruk. Bila metrik lain ikut keluar batas (mis. suhu tinggi lalu kelembapan rendah), itu insiden kedua dengan notifikasinya sendiri, supaya bahaya kedua tidak terbungkam.
- Insiden ditutup (`ended_at` diisi) setelah **2 pembacaan berturut-turut** yang tidak bahaya pada `reason` itu. Status waspada dihitung sebagai tidak bahaya. Tidak ada notifikasi saat ditutup.
- **Insiden offline** memakai tabel dan alur yang sama dengan `reason = sensor_offline` **[Saya]**. Karena saat sensor mati tidak ada data masuk, pemeriksaannya dijalankan scheduler tiap menit (`zones:check-offline`): zona yang pembacaan terakhirnya lebih tua dari 2 menit dan belum punya insiden offline terbuka mendapat insiden baru plus satu notifikasi ke semua user. Insiden ditutup begitu pembacaan baru masuk dari perangkat itu (tanpa menunggu 2 pembacaan), tanpa notifikasi. Zona yang belum pernah mengirim data tidak dianggap offline.
- Bila perangkat offline saat ada insiden suhu/kelembapan terbuka, insiden itu tetap terbuka (tidak ditutup otomatis) dan kartu zona menampilkan status Offline. Insiden offline berjalan berdampingan dengan itu.
- Scheduler **wajib berjalan** (`php artisan schedule:work` atau cron tiap menit), bukan hanya untuk prune. README harus menegaskan ini, dan dashboard menampilkan peringatan kecil bila pemeriksaan offline terakhir lebih tua dari 5 menit **[Saya]**.
- Kejadian yang berulang setelah ditutup membuka insiden baru dan notifikasi baru.

Isi notifikasi mengikuti contoh Anda: zona, suhu saat ini beserta batas, kelembapan, waktu kejadian, tindakan yang disarankan, dan tautan ke dashboard. Tindakan yang disarankan berupa kalimat tetap per `reason` **[Saya]**.

Class notifikasi memisahkan isi pesan dari saluran pengiriman (`via()`), supaya saluran WhatsApp bisa ditambahkan tanpa mengubah `ThresholdEvaluator`.

## 7. Dashboard dan halaman

Semua halaman di belakang login. **Tidak ada halaman pendaftaran publik**: user dibuat lewat perintah artisan dan seeder, karena semua user menerima alert dan melihat data gudang **[Saya]**. Mockup acuan: `.superpowers/brainstorm/2295-1794197799/content/dashboard-layout.html` (sementara; tidak di-commit).

- **Dashboard**:
  - Banner kritis merah menempel di atas layar selama ada insiden suhu/kelembapan terbuka, dengan tombol "Tandai Telah Ditangani". Insiden sensor offline memakai banner abu-oranye ("Sensor Zona B tidak mengirim data sejak 09:41") dengan tombol yang sama, dan kartu zona offline diurutkan tepat di bawah zona bahaya **[Saya]**.
  - Satu kartu per zona dengan badge status, suhu besar, batas `(Max: …)` berwarna kontras, panah naik/turun saat abnormal, kelembapan, dan "update X detik lalu".
  - Badge BAHAYA berdenyut halus.
  - Grafik tren 24 jam (suhu dan kelembapan, garis batas) dengan filter zona/metrik berupa chip yang bisa digeser di mobile.
  - Daftar insiden terbaru.
- **Mobile**: kartu bertumpuk vertikal dengan zona bahaya otomatis di urutan teratas, banner sticky, tombol aksi selebar layar, tabel insiden berubah menjadi daftar kartu.
- **Setelah "Tandai Telah Ditangani"**: pengklik dan waktunya dicatat. Banner berubah menjadi "Sedang ditangani oleh {nama}" dengan warna yang tidak seagresif merah, dan tetap tampil sampai insiden ditutup. Menandai tidak menutup insiden **[Saya]**.
- **Lonceng notifikasi** dengan jumlah belum dibaca, plus halaman daftar notifikasi dengan tombol tandai terbaca.
- **Riwayat insiden**: zona, mulai, selesai, durasi, puncak, status penanganan (siapa), dengan pagination.
- Data dashboard diambil lewat endpoint JSON yang di-poll tiap 15 detik. Data grafik diagregasi ke bucket beberapa menit supaya ringan (ukuran bucket diserahkan ke builder).

## 8. Firmware ESP32

Sketch Arduino modular, library: DHT sensor library (Adafruit), ArduinoJson, WiFi dan HTTPClient bawaan core ESP32.
- `config.example.h` berisi SSID, password Wi-Fi, URL API, `DEVICE_ID`, `DEVICE_TOKEN`, pin DHT, interval kirim (default 30 detik). `config.h` yang asli masuk `.gitignore`.
- Pemisahan modul: pembacaan sensor, koneksi Wi-Fi (reconnect otomatis), klien telemetri (POST JSON dengan timeout), dan loop utama berbasis `millis()` tanpa `delay()` panjang.
- Pembacaan `NaN` dari DHT22 tidak dikirim; coba lagi pada siklus berikutnya. Kegagalan kirim hanya dicatat ke Serial, tanpa antrean offline di versi 1 **[Saya]**.
- Target jaringan: HTTP di LAN lokal. HTTPS tidak termasuk versi 1 **[Saya]**.

## 9. Pengujian

Pengujian otomatis untuk backend (Pest atau PHPUnit, pilihan builder, memakai SQLite in-memory): validasi dan autentikasi API, penilaian status (aman/waspada/bahaya/offline), siklus insiden (buka setelah 2 pembacaan, tidak ada notifikasi beruntun, tutup setelah 2 pembacaan, jeda lebih dari 2 menit memutus rangkaian), insiden sensor offline (terbuka oleh `zones:check-offline`, satu notifikasi, tertutup saat data kembali), notifikasi ke semua user, "Tandai Telah Ditangani", akses halaman hanya untuk user login. Firmware diuji manual dengan perangkat nyata, panduan langkahnya ada di README.

## 10. Lingkungan dan pelaksanaan

- PHP di komputer ini 7.4.33 (Laragon). Pemilik meminta penulis yang menyiapkan PHP 8.4. Ini dikerjakan setelah dokumen ini disetujui, dan setiap unduhan dikonfirmasi dulu (nama file, sumber, ukuran) sebelum dilakukan.
- Folder `iot-gmi` belum repo git. Saat implementasi: `git init`, remote `origin` ke repo di atas, `.gitignore` memuat `.superpowers/`, `.env`, `vendor/`, `node_modules/`, `firmware/**/config.h`. Commit dilakukan lokal. Push ke GitHub hanya dengan konfirmasi pemilik.
- Struktur: aplikasi Laravel di root `iot-gmi`, firmware di `firmware/esp32-telemetry/`, README berisi langkah instalasi, pembuatan user, pemasangan perangkat, dan cara uji dengan `curl`.

## 11. Diserahkan ke builder

Detail rate limit, ukuran bucket grafik, pemilihan Pest vs PHPUnit, cara scaffolding autentikasi (selama hasilnya Blade dengan login saja), penamaan class, serta pemeriksaan dokumentasi Laravel 13 yang sebenarnya untuk `install:api` dan gaya routing terbaru setelah proyek dibuat.

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
php artisan migrate --seed    # membuat tabel, 3 zona contoh, dan akun admin bila ADMIN_* diisi
npm ci && npm run build
```

Zona contoh (`gudang-zona-a|b|c`) memakai batas **placeholder**. Sesuaikan dengan kebutuhan penyimpanan sebenarnya:

| Zona | Suhu | Kelembapan |
|---|---|---|
| A (Telur) | 10–20°C | 60–85% |
| B (Sayur Daun) | 15–25°C | 80–95% |
| C (Buah) | 8–13°C | 85–95% |

Ubah lewat menu **Batas Zona** di dashboard (`/zones`; perlu izin `zone.update`, perubahan langsung berlaku). Setiap perubahan lewat halaman ini tercatat (siapa, kapan, nilai lama → baru) di menu **Log Perubahan** (`/audit-logs`); mengubah langsung di database atau seeder tidak tercatat. Alternatif: `database/seeders/ZoneSeeder.php` (lalu `php artisan db:seed --class=ZoneSeeder`, yang menimpa nilai di database) atau langsung di tabel `zones` (`temp_min`, `temp_max`, `humidity_min`, `humidity_max`; isi kelembapan dengan NULL untuk tidak memeriksanya; `warning_margin` dan `humidity_warning_margin` mengatur batas status Waspada).

## User dan token perangkat

Tidak ada halaman pendaftaran publik. User dibuat oleh administrator lewat menu **User** (`/users`) atau lewat perintah di bawah.

### Role dan hak akses

Setiap user punya **satu role**; role menentukan fitur yang boleh dipakai. Role dikelola di menu **Role** (`/roles`, izin `role.manage`) dengan kotak centang izin per role. Izin yang tersedia: `dashboard.view`, `incident.view`, `incident.acknowledge`, `notification.view`, `zone.view`, `zone.update`, `auditlog.view`, `user.manage`, `role.manage`.

| Role awal | Izin |
|---|---|
| Administrator (sistem) | Semua izin, selalu (tidak bisa diubah atau dihapus) |
| Supervisor | Dashboard, insiden (lihat dan tandai ditangani), notifikasi, batas zona (lihat dan ubah), log perubahan |
| Operator | Dashboard, insiden (lihat dan tandai ditangani), notifikasi |

- Alert (notifikasi insiden) hanya dikirim ke user yang rolenya punya `notification.view`.
- Selalu tersisa minimal satu Administrator; Administrator terakhir tidak bisa dihapus atau dipindah role, dan user tidak bisa menghapus akunnya sendiri.
- Hanya Administrator yang boleh memberi role Administrator atau mengubah/menghapus akun Administrator; user non-Administrator juga tidak bisa mengubah role-nya sendiri.
- Izin `user.manage` dan `role.manage` bersifat kuat: pemegang `role.manage` bisa memberi role-nya sendiri izin apa pun (kecuali menjadi Administrator). Berikan hanya kepada orang yang dipercaya.
- Role yang masih dipakai user tidak bisa dihapus. User tanpa role hanya melihat halaman "belum punya hak akses".
- Pembuatan, perubahan, dan penghapusan user dan role tercatat di **Log Perubahan** (kata sandi tidak pernah dicatat).
- Instalasi yang sudah berjalan: `php artisan migrate` membuat role awal dan menjadikan semua user yang ada sebagai Administrator.

```bash
php artisan user:create "Budi Santoso" budi@example.com    # kata sandi akan ditanyakan; role bawaan Administrator
php artisan user:create "Sari" sari@example.com --role=Operator
php artisan zone:token gudang-zona-a                       # token ESP32 zona A, tampil sekali
```

Menjalankan `zone:token` lagi untuk zona yang sama memutar token (token lama langsung tidak berlaku).

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
  -d '{"device_id":"gudang-zona-a","temperature":18.2,"humidity":62.4}'
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

HTTP tanpa HTTPS (jaringan lokal), tanpa layar admin untuk nama zona/perangkat/token (batas zona sudah bisa diubah), satu role per user (berlaku di semua zona; role per gudang belum ada), tanpa antrean offline di firmware, notifikasi hanya di dalam aplikasi.

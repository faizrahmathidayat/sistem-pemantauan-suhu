# Firmware ESP32 + DHT22

Mengirim `{device_id, temperature, humidity}` tiap 30 detik ke `POST /api/v1/telemetry` dengan header `Authorization: Bearer <token>`.

## Perangkat keras

| DHT22 | ESP32 |
|---|---|
| VCC (+) | 3V3 |
| DATA | GPIO 4 (ubah `DHT_PIN` bila perlu) |
| GND (−) | GND |

Modul DHT22 berpapan 3 pin biasanya sudah punya resistor pull-up. Pada sensor 4 pin polos, pasang resistor 10 kΩ antara VCC dan DATA.

## Arduino IDE

1. Pasang dukungan board **esp32 by Espressif** (Boards Manager), pilih board **ESP32 Dev Module**.
2. Library Manager: **DHT sensor library** (Adafruit, beserta *Adafruit Unified Sensor*) dan **ArduinoJson** versi 7.x.
3. Salin `config.example.h` menjadi `config.h`, isi Wi-Fi, `API_URL`, `DEVICE_ID`, dan `DEVICE_TOKEN`.
4. Di server: `php artisan zone:token gudang-zona-a` lalu salin token yang tampil (hanya muncul sekali).
5. Upload, buka Serial Monitor (115200 baud). Keluaran normal:

```
[wifi] tersambung, IP 192.168.1.50
[http] terkirim: {"device_id":"gudang-zona-a","temperature":18.2,"humidity":62.4}
```

## Pemecahan masalah

- `status -1`: server tidak terjangkau. Jalankan Laravel dengan `php artisan serve --host=0.0.0.0 --port=8000`, pakai IP komputer (bukan `localhost`), dan izinkan port itu di firewall.
- `status 401`: token salah atau `DEVICE_ID` bukan milik token tersebut.
- `status 422`: nilai ditolak validasi (suhu −40..80, kelembapan 0..100).
- `[dht] pembacaan tidak valid`: periksa kabel dan pull-up. Pembacaan seperti ini tidak dikirim.
- Tidak ada antrean offline: data saat Wi-Fi/server mati hilang, dan server akan menandai zona **offline** setelah 2 menit tanpa data.
- Versi 1 memakai HTTP di jaringan lokal (tanpa HTTPS).

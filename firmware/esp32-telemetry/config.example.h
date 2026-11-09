#pragma once

// Salin file ini menjadi config.h lalu isi nilainya. config.h tidak ikut di-commit.

// Wi-Fi lokal (ESP32 hanya mendukung 2.4 GHz)
#define WIFI_SSID "nama-wifi"
#define WIFI_PASSWORD "password-wifi"

// Alamat API Laravel di jaringan lokal. Gunakan IP komputer/server, bukan "localhost".
#define API_URL "http://192.168.1.10:8000/api/v1/telemetry"

// device_id zona ini dan token-nya (php artisan zone:token gudang-zona-a)
#define DEVICE_ID "gudang-zona-a"
#define DEVICE_TOKEN "isi-dengan-token-dari-perintah-zone-token"

// Sensor DHT22 pada pin data berikut
#define DHT_PIN 4
#define DHT_TYPE DHT22

// Pengiriman tiap 30 detik; server menganggap sensor offline setelah 2 menit tanpa data.
#define SEND_INTERVAL_MS 30000UL
#define HTTP_TIMEOUT_MS 5000
#define WIFI_CONNECT_TIMEOUT_MS 15000

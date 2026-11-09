// Telemetri suhu & kelembapan gudang: ESP32 + DHT22 -> API Laravel (HTTP POST JSON).
// Salin config.example.h menjadi config.h dan isi nilainya sebelum meng-upload.

#include "SensorReader.h"
#include "TelemetryClient.h"
#include "WifiConnection.h"
#include "config.h"

SensorReader sensor(DHT_PIN, DHT_TYPE);
WifiConnection wifi(WIFI_SSID, WIFI_PASSWORD);
TelemetryClient telemetry(API_URL, DEVICE_ID, DEVICE_TOKEN, HTTP_TIMEOUT_MS);

uint32_t lastSendAt = 0;
bool firstCycle = true;

void sendOnce() {
    const Reading reading = sensor.read();
    if (!reading.valid) {
        Serial.println("[dht] pembacaan tidak valid, dilewati");
        return;
    }

    if (!wifi.ensureConnected(WIFI_CONNECT_TIMEOUT_MS)) {
        return;
    }

    telemetry.send(reading);
}

void setup() {
    Serial.begin(115200);
    sensor.begin();
    wifi.begin();
}

void loop() {
    const uint32_t now = millis();

    // Selisih unsigned tetap benar saat millis() melewati batas.
    if (firstCycle || now - lastSendAt >= SEND_INTERVAL_MS) {
        firstCycle = false;
        lastSendAt = now;
        sendOnce();
    }

    delay(50);
}

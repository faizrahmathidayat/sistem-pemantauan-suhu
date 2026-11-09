#include "TelemetryClient.h"

#include <ArduinoJson.h>
#include <HTTPClient.h>

TelemetryClient::TelemetryClient(const char* url, const char* deviceId, const char* token, uint32_t timeoutMs)
    : _url(url), _deviceId(deviceId), _token(token), _timeoutMs(timeoutMs) {}

bool TelemetryClient::send(const Reading& reading) {
    JsonDocument doc;
    doc["device_id"] = _deviceId;
    doc["temperature"] = round(reading.temperature * 100.0) / 100.0;
    doc["humidity"] = round(reading.humidity * 100.0) / 100.0;

    String body;
    serializeJson(doc, body);

    HTTPClient http;
    http.setTimeout(_timeoutMs);
    if (!http.begin(_url)) {
        Serial.println("[http] URL tidak valid");
        return false;
    }
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");
    http.addHeader("Authorization", String("Bearer ") + _token);

    const int status = http.POST(body);
    http.end();

    if (status == 201) {
        Serial.printf("[http] terkirim: %s\n", body.c_str());
        return true;
    }

    // status < 0 berarti gagal terhubung/timeout; 401 token salah; 422 data ditolak.
    Serial.printf("[http] gagal, status %d\n", status);
    return false;
}

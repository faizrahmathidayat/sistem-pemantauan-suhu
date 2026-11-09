#include "WifiConnection.h"

#include <WiFi.h>

WifiConnection::WifiConnection(const char* ssid, const char* password)
    : _ssid(ssid), _password(password) {}

void WifiConnection::begin() {
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
}

bool WifiConnection::ensureConnected(uint32_t timeoutMs) {
    if (WiFi.status() == WL_CONNECTED) {
        return true;
    }

    Serial.printf("[wifi] menyambung ke %s ...\n", _ssid);
    WiFi.disconnect();
    WiFi.begin(_ssid, _password);

    const uint32_t startedAt = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - startedAt < timeoutMs) {
        delay(250);
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.printf("[wifi] tersambung, IP %s\n", WiFi.localIP().toString().c_str());
        return true;
    }

    Serial.println("[wifi] gagal tersambung, dicoba lagi pada siklus berikutnya");
    return false;
}

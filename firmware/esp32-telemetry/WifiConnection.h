#pragma once

#include <Arduino.h>

// Menjaga koneksi Wi-Fi: menyambung ulang bila terputus, dengan batas waktu.
class WifiConnection {
public:
    WifiConnection(const char* ssid, const char* password);

    void begin();
    bool ensureConnected(uint32_t timeoutMs);

private:
    const char* _ssid;
    const char* _password;
};

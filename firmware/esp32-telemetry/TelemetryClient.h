#pragma once

#include <Arduino.h>

#include "SensorReader.h"

// Mengirim satu pembacaan ke API Laravel lewat HTTP POST JSON ber-Bearer token.
class TelemetryClient {
public:
    TelemetryClient(const char* url, const char* deviceId, const char* token, uint32_t timeoutMs);

    // true bila server menjawab 201 Created.
    bool send(const Reading& reading);

private:
    const char* _url;
    const char* _deviceId;
    const char* _token;
    uint32_t _timeoutMs;
};

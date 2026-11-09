#pragma once

#include <Arduino.h>
#include <DHT.h>

struct Reading {
    float temperature;
    float humidity;
    bool valid;
};

// Membaca DHT22. Pembacaan NaN atau di luar rentang fisik sensor ditandai tidak valid.
class SensorReader {
public:
    SensorReader(uint8_t pin, uint8_t type);

    void begin();
    Reading read();

private:
    DHT _dht;
};

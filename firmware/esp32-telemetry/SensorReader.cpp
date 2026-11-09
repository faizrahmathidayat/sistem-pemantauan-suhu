#include "SensorReader.h"

SensorReader::SensorReader(uint8_t pin, uint8_t type) : _dht(pin, type) {}

void SensorReader::begin() {
    _dht.begin();
}

Reading SensorReader::read() {
    Reading reading{_dht.readTemperature(), _dht.readHumidity(), false};

    // Rentang yang sama dengan validasi API: suhu -40..80, kelembapan 0..100.
    reading.valid = !isnan(reading.temperature) && !isnan(reading.humidity)
                    && reading.temperature >= -40.0f && reading.temperature <= 80.0f
                    && reading.humidity >= 0.0f && reading.humidity <= 100.0f;

    return reading;
}

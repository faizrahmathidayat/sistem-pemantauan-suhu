<?php

namespace Tests\Feature;

use App\Models\SensorLog;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_A = 'token-zona-a';

    private const TOKEN_B = 'token-zona-b';

    protected function setUp(): void
    {
        parent::setUp();

        Zone::factory()->create(['device_id' => 'gudang-zona-a', 'device_token_hash' => hash('sha256', self::TOKEN_A)]);
        Zone::factory()->create(['device_id' => 'gudang-zona-b', 'device_token_hash' => hash('sha256', self::TOKEN_B)]);
    }

    private function sendTelemetry(array $payload, ?string $token = self::TOKEN_A)
    {
        $headers = $token === null ? [] : ['Authorization' => 'Bearer '.$token];

        return $this->postJson('/api/v1/telemetry', $payload, $headers);
    }

    public function test_valid_payload_with_token_stores_log_and_returns_201(): void
    {
        $response = $this->sendTelemetry(['device_id' => 'gudang-zona-a', 'temperature' => 18.2, 'humidity' => 62.4]);

        $response->assertCreated()->assertJsonStructure(['data' => ['id']]);
        $this->assertDatabaseHas('sensor_logs', ['device_id' => 'gudang-zona-a', 'temperature' => 18.2, 'humidity' => 62.4]);
    }

    public function test_numeric_strings_are_accepted(): void
    {
        $this->sendTelemetry(['device_id' => 'gudang-zona-a', 'temperature' => '18.2', 'humidity' => '62'])->assertCreated();
    }

    public function test_missing_token_returns_401(): void
    {
        $this->sendTelemetry(['device_id' => 'gudang-zona-a', 'temperature' => 18, 'humidity' => 60], null)->assertUnauthorized();
        $this->assertSame(0, SensorLog::count());
    }

    public function test_unknown_token_returns_401(): void
    {
        $this->sendTelemetry(['device_id' => 'gudang-zona-a', 'temperature' => 18, 'humidity' => 60], 'salah')->assertUnauthorized();
    }

    public function test_token_of_zone_a_with_device_id_of_zone_b_returns_401_and_stores_nothing(): void
    {
        $this->sendTelemetry(['device_id' => 'gudang-zona-b', 'temperature' => 18, 'humidity' => 60], self::TOKEN_A)->assertUnauthorized();

        $this->assertSame(0, SensorLog::count());
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_return_422_and_store_nothing(array $payload): void
    {
        $this->sendTelemetry($payload)->assertUnprocessable();

        $this->assertSame(0, SensorLog::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'humidity above 100' => [['device_id' => 'gudang-zona-a', 'temperature' => 20, 'humidity' => 150]],
            'humidity below 0' => [['device_id' => 'gudang-zona-a', 'temperature' => 20, 'humidity' => -1]],
            'temperature not numeric' => [['device_id' => 'gudang-zona-a', 'temperature' => 'abc', 'humidity' => 60]],
            'temperature below -40' => [['device_id' => 'gudang-zona-a', 'temperature' => -41, 'humidity' => 60]],
            'temperature above 80' => [['device_id' => 'gudang-zona-a', 'temperature' => 81, 'humidity' => 60]],
            'humidity missing' => [['device_id' => 'gudang-zona-a', 'temperature' => 20]],
            'device_id empty' => [['device_id' => '', 'temperature' => 20, 'humidity' => 60]],
            'device_id missing' => [['temperature' => 20, 'humidity' => 60]],
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSensorLogRequest;
use App\Models\SensorLog;
use App\Models\Zone;
use App\Services\IncidentManager;
use Illuminate\Http\JsonResponse;

class SensorController extends Controller
{
    public function __construct(private readonly IncidentManager $incidents) {}

    public function __invoke(StoreSensorLogRequest $request): JsonResponse
    {
        /** @var Zone $zone */
        $zone = $request->attributes->get('zone');

        $log = SensorLog::create([
            'device_id' => $zone->device_id,
            'temperature' => $request->validated('temperature'),
            'humidity' => $request->validated('humidity'),
        ]);

        $this->incidents->handleReading($zone, $log);

        return response()->json(['data' => ['id' => $log->id]], 201);
    }
}

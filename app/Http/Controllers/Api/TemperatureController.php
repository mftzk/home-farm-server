<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TemperatureReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemperatureController extends Controller
{
    private const RANGE_MAP = [
        '1h'  => [1, 'HOUR'],
        '6h'  => [6, 'HOUR'],
        '24h' => [24, 'HOUR'],
        '7d'  => [7, 'DAY'],
        '30d' => [30, 'DAY'],
    ];

    public function index(Request $request): JsonResponse
    {
        $range = $request->input('range', '24h');
        $intervalParts = self::RANGE_MAP[$range] ?? self::RANGE_MAP['24h'];
        $interval = "{$intervalParts[0]} {$intervalParts[1]}";

        $limit = min(max((int) $request->input('limit', 500), 1), 5000);

        $data = TemperatureReading::where('recorded_at', '>=', DB::raw("NOW() - INTERVAL {$interval}"))
            ->orderBy('recorded_at')
            ->limit($limit)
            ->get(['temperature', 'humidity', 'recorded_at']);

        $result = ['data' => $data];

        if ($request->has('stats')) {
            $stats = TemperatureReading::where('recorded_at', '>=', DB::raw("NOW() - INTERVAL {$interval}"))
                ->selectRaw('MIN(temperature) AS min_temp, MAX(temperature) AS max_temp, ROUND(AVG(temperature), 1) AS avg_temp, MIN(humidity) AS min_hum, MAX(humidity) AS max_hum, ROUND(AVG(humidity), 1) AS avg_hum, COUNT(*) AS total')
                ->first();

            $result['stats'] = $stats;
        }

        $result['latest'] = TemperatureReading::orderByDesc('id')->first(['temperature', 'humidity', 'recorded_at']);

        return response()->json($result);
    }
}

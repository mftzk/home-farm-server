<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LightReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadingController extends Controller
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

        $data = LightReading::where('recorded_at', '>=', DB::raw("NOW() - INTERVAL {$interval}"))
            ->orderBy('recorded_at')
            ->limit($limit)
            ->get(['lux', 'recorded_at']);

        $result = ['data' => $data];

        if ($request->has('stats')) {
            $stats = LightReading::where('recorded_at', '>=', DB::raw("NOW() - INTERVAL {$interval}"))
                ->selectRaw('MIN(lux) AS min_lux, MAX(lux) AS max_lux, ROUND(AVG(lux), 1) AS avg_lux, COUNT(*) AS total')
                ->first();

            $result['stats'] = $stats;
        }

        $result['latest'] = LightReading::orderByDesc('id')->first(['lux', 'recorded_at']);

        return response()->json($result);
    }
}

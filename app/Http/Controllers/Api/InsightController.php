<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LightReading;
use App\Models\RelayAutoConfig;
use App\Models\TemperatureReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InsightController extends Controller
{
    public function daily(): JsonResponse
    {
        return response()->json([
            'light' => $this->lightInsight(),
            'temperature' => $this->temperatureInsight(),
            'humidity' => $this->humidityInsight(),
            'relay' => $this->relayInsight(),
        ]);
    }

    private function lightInsight(): array
    {
        $today = LightReading::whereRaw('DATE(recorded_at) = CURDATE()')
            ->selectRaw("
                MIN(lux) as min_val, MAX(lux) as max_val, ROUND(AVG(lux), 1) as avg_val, COUNT(*) as total,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM light_readings r WHERE DATE(r.recorded_at) = CURDATE() ORDER BY r.lux ASC, r.id ASC LIMIT 1) as min_at,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM light_readings r WHERE DATE(r.recorded_at) = CURDATE() ORDER BY r.lux DESC, r.id ASC LIMIT 1) as max_at
            ")
            ->first();

        $last24h = LightReading::whereRaw('recorded_at >= NOW() - INTERVAL 24 HOUR')
            ->selectRaw("
                MIN(lux) as min_val, MAX(lux) as max_val, ROUND(AVG(lux), 1) as avg_val,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM light_readings r WHERE r.recorded_at >= NOW() - INTERVAL 24 HOUR ORDER BY r.lux ASC, r.id ASC LIMIT 1) as min_at,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM light_readings r WHERE r.recorded_at >= NOW() - INTERVAL 24 HOUR ORDER BY r.lux DESC, r.id ASC LIMIT 1) as max_at
            ")
            ->first();

        $yesterday = LightReading::whereRaw('DATE(recorded_at) = CURDATE() - INTERVAL 1 DAY')
            ->selectRaw('MIN(lux) as min_val, MAX(lux) as max_val, ROUND(AVG(lux), 1) as avg_val, COUNT(*) as total')
            ->first();

        return $this->buildInsight($today, $yesterday, $last24h);
    }

    private function temperatureInsight(): array
    {
        $today = TemperatureReading::whereRaw('DATE(recorded_at) = CURDATE()')
            ->where('temperature', '>=', 5)
            ->selectRaw("
                MIN(temperature) as min_val, MAX(temperature) as max_val, ROUND(AVG(temperature), 1) as avg_val, COUNT(*) as total,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE DATE(r.recorded_at) = CURDATE() AND r.temperature >= 5 ORDER BY r.temperature ASC, r.id ASC LIMIT 1) as min_at,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE DATE(r.recorded_at) = CURDATE() AND r.temperature >= 5 ORDER BY r.temperature DESC, r.id ASC LIMIT 1) as max_at
            ")
            ->first();

        $last24h = TemperatureReading::whereRaw('recorded_at >= NOW() - INTERVAL 24 HOUR')
            ->where('temperature', '>=', 5)
            ->selectRaw("
                MIN(temperature) as min_val, MAX(temperature) as max_val, ROUND(AVG(temperature), 1) as avg_val,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE r.recorded_at >= NOW() - INTERVAL 24 HOUR AND r.temperature >= 5 ORDER BY r.temperature ASC, r.id ASC LIMIT 1) as min_at,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE r.recorded_at >= NOW() - INTERVAL 24 HOUR AND r.temperature >= 5 ORDER BY r.temperature DESC, r.id ASC LIMIT 1) as max_at
            ")
            ->first();

        $yesterday = TemperatureReading::whereRaw('DATE(recorded_at) = CURDATE() - INTERVAL 1 DAY')
            ->where('temperature', '>=', 5)
            ->selectRaw('MIN(temperature) as min_val, MAX(temperature) as max_val, ROUND(AVG(temperature), 1) as avg_val, COUNT(*) as total')
            ->first();

        return $this->buildInsight($today, $yesterday, $last24h);
    }

    private function humidityInsight(): array
    {
        $today = TemperatureReading::whereRaw('DATE(recorded_at) = CURDATE()')
            ->whereBetween('humidity', [5, 100])
            ->selectRaw("
                MIN(humidity) as min_val, MAX(humidity) as max_val, ROUND(AVG(humidity), 1) as avg_val, COUNT(*) as total,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE DATE(r.recorded_at) = CURDATE() AND r.humidity >= 5 AND r.humidity <= 100 ORDER BY r.humidity ASC, r.id ASC LIMIT 1) as min_at,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE DATE(r.recorded_at) = CURDATE() AND r.humidity >= 5 AND r.humidity <= 100 ORDER BY r.humidity DESC, r.id ASC LIMIT 1) as max_at
            ")
            ->first();

        $last24h = TemperatureReading::whereRaw('recorded_at >= NOW() - INTERVAL 24 HOUR')
            ->whereBetween('humidity', [5, 100])
            ->selectRaw("
                MIN(humidity) as min_val, MAX(humidity) as max_val, ROUND(AVG(humidity), 1) as avg_val,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE r.recorded_at >= NOW() - INTERVAL 24 HOUR AND r.humidity >= 5 AND r.humidity <= 100 ORDER BY r.humidity ASC, r.id ASC LIMIT 1) as min_at,
                (SELECT TIME_FORMAT(r.recorded_at, '%H:%i') FROM temperature_readings r WHERE r.recorded_at >= NOW() - INTERVAL 24 HOUR AND r.humidity >= 5 AND r.humidity <= 100 ORDER BY r.humidity DESC, r.id ASC LIMIT 1) as max_at
            ")
            ->first();

        $yesterday = TemperatureReading::whereRaw('DATE(recorded_at) = CURDATE() - INTERVAL 1 DAY')
            ->whereBetween('humidity', [5, 100])
            ->selectRaw('MIN(humidity) as min_val, MAX(humidity) as max_val, ROUND(AVG(humidity), 1) as avg_val, COUNT(*) as total')
            ->first();

        return $this->buildInsight($today, $yesterday, $last24h);
    }

    private function buildInsight($today, $yesterday, $last24h = null): array
    {
        $todayAvg = $today->avg_val ? (float) $today->avg_val : null;
        $yesterdayAvg = $yesterday->avg_val ? (float) $yesterday->avg_val : null;

        $trend = ['direction' => 'stable', 'percent' => 0];
        if ($todayAvg !== null && $yesterdayAvg !== null && $yesterdayAvg != 0) {
            $change = (($todayAvg - $yesterdayAvg) / $yesterdayAvg) * 100;
            $trend = [
                'direction' => $change > 0.5 ? 'up' : ($change < -0.5 ? 'down' : 'stable'),
                'percent' => round(abs($change), 1),
            ];
        }

        $result = [
            'today' => [
                'min' => $today->min_val !== null ? (float) $today->min_val : null,
                'max' => $today->max_val !== null ? (float) $today->max_val : null,
                'avg' => $todayAvg,
                'total' => (int) $today->total,
                'min_at' => $today->min_at,
                'max_at' => $today->max_at,
            ],
            'yesterday' => [
                'min' => $yesterday->min_val !== null ? (float) $yesterday->min_val : null,
                'max' => $yesterday->max_val !== null ? (float) $yesterday->max_val : null,
                'avg' => $yesterdayAvg,
                'total' => (int) $yesterday->total,
            ],
            'trend' => $trend,
        ];

        if ($last24h) {
            $result['last24h'] = [
                'min' => $last24h->min_val !== null ? (float) $last24h->min_val : null,
                'max' => $last24h->max_val !== null ? (float) $last24h->max_val : null,
                'avg' => $last24h->avg_val !== null ? (float) $last24h->avg_val : null,
                'min_at' => $last24h->min_at,
                'max_at' => $last24h->max_at,
            ];
        }

        return $result;
    }

    private function relayInsight(): array
    {
        $configs = RelayAutoConfig::all(['relay_id', 'auto_enabled', 'sensor_type', 'condition']);
        $autoCount = $configs->where('auto_enabled', true)->count();

        return [
            'auto_enabled_count' => $autoCount,
            'total' => $configs->count(),
            'configs' => $configs,
        ];
    }
}

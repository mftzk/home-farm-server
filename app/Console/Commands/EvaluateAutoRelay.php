<?php

namespace App\Console\Commands;

use App\Models\LightReading;
use App\Models\RelayAutoConfig;
use App\Models\TemperatureReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvaluateAutoRelay extends Command
{
    protected $signature = 'app:evaluate-auto-relay';

    protected $description = 'Evaluate sensor data and control relays in auto-mode';

    public function handle(): int
    {
        $latestLight = LightReading::orderByDesc('recorded_at')->first();
        $latestTemp = TemperatureReading::orderByDesc('recorded_at')->first();

        $configs = RelayAutoConfig::where('auto_enabled', true)->get();

        if ($configs->isEmpty()) {
            $this->info('No relays with auto-mode enabled.');

            return self::SUCCESS;
        }

        $relayIp = config('esp.relay_ip');
        $timeout = config('esp.timeout');

        foreach ($configs as $config) {
            $relayId = $config->relay_id;
            $sensorType = $config->sensor_type;

            $latest = $sensorType === 'light' ? $latestLight : $latestTemp;

            if (! $latest) {
                $this->warn("Relay {$relayId}: no {$sensorType} readings found, skipping.");

                continue;
            }

            if ($latest->recorded_at->diffInMinutes(now()) > 3) {
                $this->warn("Relay {$relayId}: latest {$sensorType} reading is older than 3 minutes ({$latest->recorded_at}), skipping.");

                continue;
            }

            $value = $sensorType === 'light' ? $latest->lux : $latest->temperature;
            $condition = $config->condition;
            $thresholdOn = $config->threshold_on;
            $thresholdOff = $config->threshold_off;

            $shouldTurnOn = false;
            $shouldTurnOff = false;

            if ($condition === 'below') {
                $shouldTurnOn = $value < $thresholdOn && $config->last_auto_state !== true;
                $shouldTurnOff = $value > $thresholdOff && $config->last_auto_state !== false;
            } else {
                $shouldTurnOn = $value > $thresholdOn && $config->last_auto_state !== true;
                $shouldTurnOff = $value < $thresholdOff && $config->last_auto_state !== false;
            }

            if ($shouldTurnOn) {
                $this->switchRelay($relayId, 1, $config, $relayIp, $timeout, $sensorType, $value);
            } elseif ($shouldTurnOff) {
                $this->switchRelay($relayId, 0, $config, $relayIp, $timeout, $sensorType, $value);
            } else {
                $this->info("Relay {$relayId}: no action ({$sensorType}={$value}, state={$config->last_auto_state})");
            }
        }

        return self::SUCCESS;
    }

    private function switchRelay(int $relayId, int $state, RelayAutoConfig $config, string $ip, int $timeout, string $sensorType, float $value): void
    {
        $action = $state ? 'ON' : 'OFF';

        try {
            $response = Http::timeout($timeout)
                ->get("http://{$ip}/relay", ['id' => $relayId, 'state' => $state]);

            if (! $response->successful()) {
                $this->error("Relay {$relayId}: HTTP {$response->status()} trying to turn {$action}");
                Log::warning("EvaluateAutoRelay: Relay {$relayId} HTTP {$response->status()}");

                return;
            }

            $config->update(['last_auto_state' => (bool) $state]);
            $this->info("Relay {$relayId}: turned {$action} ({$sensorType}={$value})");
            Log::info("EvaluateAutoRelay: Relay {$relayId} turned {$action} ({$sensorType}={$value})");
        } catch (\Exception $e) {
            $this->error("Relay {$relayId}: {$e->getMessage()}");
            Log::warning("EvaluateAutoRelay: Relay {$relayId} error: {$e->getMessage()}");
        }
    }
}

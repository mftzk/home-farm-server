<?php

namespace App\Console\Commands;

use App\Models\LightReading;
use App\Models\RelayAutoConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvaluateAutoRelay extends Command
{
    protected $signature = 'app:evaluate-auto-relay';

    protected $description = 'Evaluate light sensor data and control relays in auto-mode';

    public function handle(): int
    {
        $latest = LightReading::orderByDesc('recorded_at')->first();

        if (! $latest) {
            $this->warn('No light readings found, skipping.');

            return self::SUCCESS;
        }

        if ($latest->recorded_at->diffInMinutes(now()) > 3) {
            $this->warn("Latest reading is older than 3 minutes ({$latest->recorded_at}), skipping.");

            return self::SUCCESS;
        }

        $lux = $latest->lux;
        $configs = RelayAutoConfig::where('auto_enabled', true)->get();

        if ($configs->isEmpty()) {
            $this->info('No relays with auto-mode enabled.');

            return self::SUCCESS;
        }

        $relayIp = config('esp.relay_ip');
        $timeout = config('esp.timeout');

        foreach ($configs as $config) {
            $relayId = $config->relay_id;

            if ($lux < $config->lux_on_below && $config->last_auto_state !== true) {
                $this->switchRelay($relayId, 1, $config, $relayIp, $timeout, $lux);
            } elseif ($lux > $config->lux_off_above && $config->last_auto_state !== false) {
                $this->switchRelay($relayId, 0, $config, $relayIp, $timeout, $lux);
            } else {
                $this->info("Relay {$relayId}: no action (lux={$lux}, state={$config->last_auto_state})");
            }
        }

        return self::SUCCESS;
    }

    private function switchRelay(int $relayId, int $state, RelayAutoConfig $config, string $ip, int $timeout, float $lux): void
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
            $this->info("Relay {$relayId}: turned {$action} (lux={$lux})");
            Log::info("EvaluateAutoRelay: Relay {$relayId} turned {$action} (lux={$lux})");
        } catch (\Exception $e) {
            $this->error("Relay {$relayId}: {$e->getMessage()}");
            Log::warning("EvaluateAutoRelay: Relay {$relayId} error: {$e->getMessage()}");
        }
    }
}

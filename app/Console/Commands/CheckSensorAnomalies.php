<?php

namespace App\Console\Commands;

use App\Models\LightReading;
use App\Models\TemperatureReading;
use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckSensorAnomalies extends Command
{
    protected $signature = 'app:check-sensor-anomalies';

    protected $description = 'Detect sensor/relay disconnects and send Discord alerts';

    /**
     * Minutes without a new reading before a sensor is considered disconnected.
     */
    private const STALE_THRESHOLD_MINUTES = 5;

    /**
     * How long (seconds) to suppress repeat alerts for the same anomaly.
     */
    private const ALERT_COOLDOWN_SECONDS = 1800; // 30 minutes

    public function handle(DiscordService $discord): int
    {
        $anomalies = [];

        $anomalies = array_merge(
            $anomalies,
            $this->checkLightSensor(),
            $this->checkTemperatureSensor(),
            $this->checkRelay(),
        );

        if (empty($anomalies)) {
            $this->info('All sensors OK.');
            $this->clearAllAlerts();

            return self::SUCCESS;
        }

        foreach ($anomalies as $anomaly) {
            $this->error($anomaly['message']);
            Log::warning("CheckSensorAnomalies: {$anomaly['message']}");
        }

        $this->sendDiscordAlert($discord, $anomalies);

        return self::FAILURE;
    }

    private function checkLightSensor(): array
    {
        $latest = LightReading::orderByDesc('recorded_at')->first();

        if (! $latest) {
            return $this->buildAnomaly(
                'light_sensor',
                'Sensor Cahaya',
                'Tidak ada data pembacaan sama sekali.'
            );
        }

        $age = (int) $latest->recorded_at->diffInMinutes(now());

        if ($age > self::STALE_THRESHOLD_MINUTES) {
            return $this->buildAnomaly(
                'light_sensor',
                'Sensor Cahaya',
                "Tidak ada pembacaan baru selama {$age} menit (terakhir: {$latest->recorded_at->format('H:i:s')})."
            );
        }

        $this->resolveAlert('light_sensor');

        return [];
    }

    private function checkTemperatureSensor(): array
    {
        $latest = TemperatureReading::orderByDesc('recorded_at')->first();

        if (! $latest) {
            return $this->buildAnomaly(
                'temp_sensor',
                'Sensor Suhu/Kelembaban',
                'Tidak ada data pembacaan sama sekali.'
            );
        }

        $age = (int) $latest->recorded_at->diffInMinutes(now());

        if ($age > self::STALE_THRESHOLD_MINUTES) {
            return $this->buildAnomaly(
                'temp_sensor',
                'Sensor Suhu/Kelembaban',
                "Tidak ada pembacaan baru selama {$age} menit (terakhir: {$latest->recorded_at->format('H:i:s')})."
            );
        }

        $this->resolveAlert('temp_sensor');

        return [];
    }

    private function checkRelay(): array
    {
        $ip = config('esp.relay_ip');
        $timeout = config('esp.timeout');

        try {
            $response = Http::timeout($timeout)->get("http://{$ip}/status");

            if (! $response->successful()) {
                return $this->buildAnomaly(
                    'relay',
                    'Relay Controller',
                    "Tidak merespons — HTTP {$response->status()} dari {$ip}."
                );
            }

            $this->resolveAlert('relay');

            return [];
        } catch (\Exception $e) {
            return $this->buildAnomaly(
                'relay',
                'Relay Controller',
                "Tidak dapat terhubung ke {$ip}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Build an anomaly entry, but only return it if not currently in cooldown.
     */
    private function buildAnomaly(string $key, string $label, string $detail): array
    {
        $cacheKey = "anomaly_alerted_{$key}";

        $alreadyAlerted = Cache::has($cacheKey);

        // Always store the latest state so it refreshes the cooldown window
        Cache::put($cacheKey, true, self::ALERT_COOLDOWN_SECONDS);

        if ($alreadyAlerted) {
            $this->warn("{$label}: anomaly persists but alert already sent recently, skipping Discord.");

            return [];
        }

        return [[
            'key' => $key,
            'label' => $label,
            'message' => "{$label} disconnect: {$detail}",
            'detail' => $detail,
        ]];
    }

    private function resolveAlert(string $key): void
    {
        $cacheKey = "anomaly_alerted_{$key}";

        if (Cache::has($cacheKey)) {
            Cache::forget($cacheKey);
            $this->info("Alert cleared for: {$key}");
        }
    }

    private function clearAllAlerts(): void
    {
        foreach (['light_sensor', 'temp_sensor', 'relay'] as $key) {
            Cache::forget("anomaly_alerted_{$key}");
        }
    }

    private function sendDiscordAlert(DiscordService $discord, array $anomalies): void
    {
        if (! $discord->isConfigured()) {
            return;
        }

        $fields = array_map(fn ($a) => [
            'name' => "⚠️ {$a['label']}",
            'value' => $a['detail'],
            'inline' => false,
        ], $anomalies);

        $discord->sendEmbed([
            'title' => '🚨 Peringatan Anomali Sensor',
            'description' => 'Satu atau lebih perangkat terdeteksi tidak merespons.',
            'color' => 0xED4245, // merah Discord
            'fields' => $fields,
            'footer' => ['text' => 'Home Farm Monitor'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

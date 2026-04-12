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
     * How long (seconds) to suppress repeat Discord alerts for the same anomaly.
     */
    private const ALERT_COOLDOWN_SECONDS = 1800; // 30 minutes

    public function handle(DiscordService $discord): int
    {
        $anomalies = array_values(array_filter([
            $this->checkLightSensor(),
            $this->checkTemperatureSensor(),
            $this->checkRelay(),
        ]));

        if (empty($anomalies)) {
            $this->info('All sensors OK.');
            $this->clearAllAlerts();

            return self::SUCCESS;
        }

        // Log and print every active anomaly
        foreach ($anomalies as $anomaly) {
            $msg = "{$anomaly['label']} disconnect: {$anomaly['detail']}";
            $this->error($msg);
            Log::warning("CheckSensorAnomalies: {$msg}");
        }

        // Only send Discord for anomalies whose cooldown has expired
        $toAlert = array_values(array_filter($anomalies, fn ($a) => $this->shouldAlert($a['key'])));

        if (! empty($toAlert)) {
            $this->sendDiscordAlert($discord, $toAlert);
        }

        return self::FAILURE;
    }

    // -------------------------------------------------------------------------

    private function checkLightSensor(): ?array
    {
        $latest = LightReading::orderByDesc('recorded_at')->first();

        if (! $latest) {
            return $this->anomaly('light_sensor', 'Sensor Cahaya', 'Tidak ada data pembacaan sama sekali.');
        }

        $age = (int) $latest->recorded_at->diffInMinutes(now());

        if ($age > self::STALE_THRESHOLD_MINUTES) {
            return $this->anomaly(
                'light_sensor',
                'Sensor Cahaya',
                "Tidak ada pembacaan baru selama {$age} menit (terakhir: {$latest->recorded_at->format('H:i:s')})."
            );
        }

        $this->resolveAlert('light_sensor');

        return null;
    }

    private function checkTemperatureSensor(): ?array
    {
        $latest = TemperatureReading::orderByDesc('recorded_at')->first();

        if (! $latest) {
            return $this->anomaly('temp_sensor', 'Sensor Suhu/Kelembaban', 'Tidak ada data pembacaan sama sekali.');
        }

        $age = (int) $latest->recorded_at->diffInMinutes(now());

        if ($age > self::STALE_THRESHOLD_MINUTES) {
            return $this->anomaly(
                'temp_sensor',
                'Sensor Suhu/Kelembaban',
                "Tidak ada pembacaan baru selama {$age} menit (terakhir: {$latest->recorded_at->format('H:i:s')})."
            );
        }

        $this->resolveAlert('temp_sensor');

        return null;
    }

    private function checkRelay(): ?array
    {
        $ip = config('esp.relay_ip');
        $timeout = config('esp.timeout');

        try {
            $response = Http::timeout($timeout)->get("http://{$ip}/status");

            if (! $response->successful()) {
                return $this->anomaly(
                    'relay',
                    'Relay Controller',
                    "Tidak merespons — HTTP {$response->status()} dari {$ip}."
                );
            }

            $this->resolveAlert('relay');

            return null;
        } catch (\Exception $e) {
            return $this->anomaly(
                'relay',
                'Relay Controller',
                "Tidak dapat terhubung ke {$ip}: {$e->getMessage()}"
            );
        }
    }

    // -------------------------------------------------------------------------

    private function anomaly(string $key, string $label, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'detail' => $detail];
    }

    /**
     * Returns true if a Discord alert should be sent (i.e. not in cooldown).
     * Marks the anomaly as alerted (starts cooldown) if it wasn't already.
     */
    private function shouldAlert(string $key): bool
    {
        $cacheKey = "anomaly_alerted_{$key}";

        if (Cache::has($cacheKey)) {
            $this->warn("{$key}: alert already sent recently, skipping Discord.");

            return false;
        }

        Cache::put($cacheKey, true, self::ALERT_COOLDOWN_SECONDS);

        return true;
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
            'color' => 0xED4245,
            'fields' => $fields,
            'footer' => ['text' => 'Home Farm Monitor'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

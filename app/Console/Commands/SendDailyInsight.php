<?php

namespace App\Console\Commands;

use App\Models\LightReading;
use App\Models\RelayAutoConfig;
use App\Models\TemperatureReading;
use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyInsight extends Command
{
    protected $signature = 'app:send-daily-insight';

    protected $description = 'Send daily sensor insight summary to Discord';

    public function handle(DiscordService $discord): int
    {
        if (! $discord->isConfigured()) {
            $this->warn('DISCORD_WEBHOOK_URL not configured. Skipping.');

            return self::SUCCESS;
        }

        $light = $this->lightInsight();
        $temperature = $this->temperatureInsight();
        $humidity = $this->humidityInsight();
        $relay = $this->relayInsight();

        $date = now()->subDay()->format('d F Y'); // yesterday's data (run at midnight)

        $fields = [];

        // Light
        $fields[] = [
            'name' => '💡 Cahaya (lux)',
            'value' => $this->formatMetric($light),
            'inline' => true,
        ];

        // Temperature
        $fields[] = [
            'name' => '🌡️ Suhu (°C)',
            'value' => $this->formatMetric($temperature),
            'inline' => true,
        ];

        // Humidity
        $fields[] = [
            'name' => '💧 Kelembaban (%)',
            'value' => $this->formatMetric($humidity),
            'inline' => true,
        ];

        // Relay
        $fields[] = [
            'name' => '🔌 Relay',
            'value' => "{$relay['auto_enabled_count']} dari {$relay['total']} relay dalam mode otomatis.",
            'inline' => false,
        ];

        $discord->sendEmbed([
            'title' => "📊 Daily Insight — {$date}",
            'description' => 'Rangkuman data sensor selama 24 jam terakhir.',
            'color' => 0x57F287, // hijau Discord
            'fields' => $fields,
            'footer' => ['text' => 'Home Farm Monitor'],
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->info('Daily insight sent to Discord.');
        Log::info('SendDailyInsight: sent successfully.');

        return self::SUCCESS;
    }

    private function lightInsight(): array
    {
        $yesterday = now()->subDay()->toDateString();

        $row = LightReading::whereDate('recorded_at', $yesterday)
            ->selectRaw('MIN(lux) as min_val, MAX(lux) as max_val, ROUND(AVG(lux), 1) as avg_val, COUNT(*) as total')
            ->first();

        return $this->extractStats($row);
    }

    private function temperatureInsight(): array
    {
        $yesterday = now()->subDay()->toDateString();

        $row = TemperatureReading::whereDate('recorded_at', $yesterday)
            ->where('temperature', '>=', 5)
            ->selectRaw('MIN(temperature) as min_val, MAX(temperature) as max_val, ROUND(AVG(temperature), 1) as avg_val, COUNT(*) as total')
            ->first();

        return $this->extractStats($row);
    }

    private function humidityInsight(): array
    {
        $yesterday = now()->subDay()->toDateString();

        $row = TemperatureReading::whereDate('recorded_at', $yesterday)
            ->whereBetween('humidity', [5, 100])
            ->selectRaw('MIN(humidity) as min_val, MAX(humidity) as max_val, ROUND(AVG(humidity), 1) as avg_val, COUNT(*) as total')
            ->first();

        return $this->extractStats($row);
    }

    private function extractStats($row): array
    {
        if (! $row || (int) $row->total === 0) {
            return ['min' => null, 'max' => null, 'avg' => null, 'total' => 0];
        }

        return [
            'min' => (float) $row->min_val,
            'max' => (float) $row->max_val,
            'avg' => (float) $row->avg_val,
            'total' => (int) $row->total,
        ];
    }

    private function relayInsight(): array
    {
        $configs = RelayAutoConfig::all(['relay_id', 'auto_enabled']);
        $autoCount = $configs->where('auto_enabled', true)->count();

        return [
            'auto_enabled_count' => $autoCount,
            'total' => $configs->count(),
        ];
    }

    private function formatMetric(array $stats): string
    {
        if ($stats['total'] === 0) {
            return '_Tidak ada data_';
        }

        return implode("\n", [
            "Min: **{$stats['min']}**",
            "Maks: **{$stats['max']}**",
            "Rata-rata: **{$stats['avg']}**",
            "Sampel: {$stats['total']}",
        ]);
    }
}

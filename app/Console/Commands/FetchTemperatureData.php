<?php

namespace App\Console\Commands;

use App\Models\TemperatureReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchTemperatureData extends Command
{
    protected $signature = 'app:fetch-temperature-data';

    protected $description = 'Pull temperature & humidity from ESP32 SHT40 sensor and store it';

    public function handle(): int
    {
        $ip = config('esp.temp_ip');
        $timeout = config('esp.timeout');
        $url = "http://{$ip}/data";

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (\Exception $e) {
            $this->error("FETCH FAILED: {$e->getMessage()}");
            Log::error("FetchTemperatureData: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("HTTP {$response->status()} from {$url}");
            Log::error("FetchTemperatureData: HTTP {$response->status()}");

            return self::FAILURE;
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['t']) || ! isset($data['h'])) {
            $this->error("INVALID JSON: {$response->body()}");
            Log::error("FetchTemperatureData: invalid JSON response");

            return self::FAILURE;
        }

        if ($data['t'] === 'err' || ! is_numeric($data['t'])) {
            $this->error("SENSOR ERROR: t={$data['t']}");
            Log::error("FetchTemperatureData: sensor error t={$data['t']}");

            return self::FAILURE;
        }

        if ($data['h'] === 'err' || ! is_numeric($data['h'])) {
            $this->error("SENSOR ERROR: h={$data['h']}");
            Log::error("FetchTemperatureData: sensor error h={$data['h']}");

            return self::FAILURE;
        }

        $temperature = (float) $data['t'];
        $humidity = (float) $data['h'];

        if ($temperature < 5) {
            $this->error("SENSOR GLITCH: t={$temperature} (below minimum 5°C)");
            Log::error("FetchTemperatureData: temperature below minimum t={$temperature}");

            return self::FAILURE;
        }

        if ($humidity < 5 || $humidity > 100) {
            $this->error("SENSOR GLITCH: h={$humidity} (out of range 5-100%)");
            Log::error("FetchTemperatureData: humidity out of range h={$humidity}");

            return self::FAILURE;
        }

        TemperatureReading::create([
            'temperature' => $temperature,
            'humidity' => $humidity,
        ]);

        $this->info("OK temp={$temperature}°C humidity={$humidity}%");

        return self::SUCCESS;
    }
}

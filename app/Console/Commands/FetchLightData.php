<?php

namespace App\Console\Commands;

use App\Models\LightReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchLightData extends Command
{
    protected $signature = 'app:fetch-light-data';

    protected $description = 'Pull lux reading from ESP8266 BH1750 sensor and store it';

    public function handle(): int
    {
        $ip = config('esp.ip');
        $timeout = config('esp.timeout');
        $url = "http://{$ip}/data";

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (\Exception $e) {
            $this->error("FETCH FAILED: {$e->getMessage()}");
            Log::error("FetchLightData: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("HTTP {$response->status()} from {$url}");
            Log::error("FetchLightData: HTTP {$response->status()}");

            return self::FAILURE;
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['lux'])) {
            $this->error("INVALID JSON: {$response->body()}");
            Log::error("FetchLightData: invalid JSON response");

            return self::FAILURE;
        }

        if ($data['lux'] === 'err' || ! is_numeric($data['lux']) || $data['lux'] < 0) {
            $this->error("SENSOR ERROR: lux={$data['lux']}");
            Log::error("FetchLightData: sensor error lux={$data['lux']}");

            return self::FAILURE;
        }

        $lux = (float) $data['lux'];

        LightReading::create(['lux' => $lux]);

        $this->info("OK lux={$lux}");

        return self::SUCCESS;
    }
}

<?php

namespace Tests\Feature;

use App\Console\Commands\CheckSensorAnomalies;
use App\Models\LightReading;
use App\Models\TemperatureReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CheckSensorAnomalies::class)]
class CheckSensorAnomaliesTest extends TestCase
{
    use RefreshDatabase;

    private string $discordUrl = 'https://discord.com/api/webhooks/test/token';

    private string $relayIp = '192.168.1.101';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.webhook_url' => $this->discordUrl,
            'esp.relay_ip' => $this->relayIp,
            'esp.timeout' => 2,
        ]);

        Http::preventStrayRequests();
        Cache::flush();
    }

    // ---- Sensor Cahaya -------------------------------------------------

    public function test_alerts_when_no_light_readings_exist(): void
    {
        TemperatureReading::create(['temperature' => 28, 'humidity' => 70]);

        Http::fake([
            "http://{$this->relayIp}/status" => Http::response('ok', 200),
            $this->discordUrl => Http::response('', 204),
        ]);

        $this->artisan('app:check-sensor-anomalies')->assertFailed();

        Http::assertSent(fn (Request $req) => $req->url() === $this->discordUrl
            && str_contains(json_encode($req['embeds'][0]['fields']), 'Sensor Cahaya')
        );
    }

    public function test_alerts_when_light_reading_is_stale(): void
    {
        LightReading::create(['lux' => 500]);
        LightReading::query()->update(['recorded_at' => now()->subMinutes(10)]);
        TemperatureReading::create(['temperature' => 28, 'humidity' => 70]);

        Http::fake([
            "http://{$this->relayIp}/status" => Http::response('ok', 200),
            $this->discordUrl => Http::response('', 204),
        ]);

        $this->artisan('app:check-sensor-anomalies')->assertFailed();

        Http::assertSent(fn (Request $req) => $req->url() === $this->discordUrl
            && str_contains(json_encode($req['embeds'][0]['fields']), 'Sensor Cahaya')
        );
    }

    public function test_no_alert_when_all_sensors_are_fresh(): void
    {
        // Explicitly set recorded_at to now() so SQLite UTC default doesn't
        // cause a timezone mismatch (useCurrent() stores UTC, Laravel reads
        // it as Asia/Jakarta → appears 7 h old → false stale alarm).
        LightReading::create(['lux' => 500]);
        LightReading::query()->update(['recorded_at' => now()]);

        TemperatureReading::create(['temperature' => 28, 'humidity' => 70]);
        TemperatureReading::query()->update(['recorded_at' => now()]);

        Http::fake(['*' => Http::response('ok', 200)]);

        $this->artisan('app:check-sensor-anomalies')->assertSuccessful();

        Http::assertNotSent(fn (Request $req) => $req->url() === $this->discordUrl);
    }

    // ---- Sensor Suhu ---------------------------------------------------

    public function test_alerts_when_temperature_reading_is_stale(): void
    {
        LightReading::create(['lux' => 100]);
        TemperatureReading::create(['temperature' => 28, 'humidity' => 70]);
        TemperatureReading::query()->update(['recorded_at' => now()->subMinutes(10)]);

        Http::fake([
            "http://{$this->relayIp}/status" => Http::response('ok', 200),
            $this->discordUrl => Http::response('', 204),
        ]);

        $this->artisan('app:check-sensor-anomalies')->assertFailed();

        Http::assertSent(fn (Request $req) => $req->url() === $this->discordUrl
            && str_contains(json_encode($req['embeds'][0]['fields']), 'Sensor Suhu')
        );
    }

    // ---- Relay ---------------------------------------------------------

    public function test_alerts_when_relay_is_unreachable(): void
    {
        LightReading::create(['lux' => 100]);
        TemperatureReading::create(['temperature' => 28, 'humidity' => 70]);

        Http::fake([
            "http://{$this->relayIp}/status" => fn () => throw new \Exception('Connection refused'),
            $this->discordUrl => Http::response('', 204),
        ]);

        $this->artisan('app:check-sensor-anomalies')->assertFailed();

        Http::assertSent(fn (Request $req) => $req->url() === $this->discordUrl
            && str_contains(json_encode($req['embeds'][0]['fields']), 'Relay Controller')
        );
    }

    public function test_alerts_when_relay_returns_http_error(): void
    {
        LightReading::create(['lux' => 100]);
        TemperatureReading::create(['temperature' => 28, 'humidity' => 70]);

        Http::fake([
            "http://{$this->relayIp}/status" => Http::response('error', 500),
            $this->discordUrl => Http::response('', 204),
        ]);

        $this->artisan('app:check-sensor-anomalies')->assertFailed();

        Http::assertSent(fn (Request $req) => $req->url() === $this->discordUrl
            && str_contains(json_encode($req['embeds'][0]['fields']), 'Relay Controller')
        );
    }

    // ---- Cooldown ------------------------------------------------------

    public function test_still_returns_failure_in_cooldown_but_skips_discord(): void
    {
        Cache::put('anomaly_alerted_light_sensor', true, 1800);
        Cache::put('anomaly_alerted_temp_sensor', true, 1800);
        Cache::put('anomaly_alerted_relay', true, 1800);

        Http::fake([
            "http://{$this->relayIp}/status" => fn () => throw new \Exception('down'),
        ]);

        // Anomalies exist → FAILURE, but Discord is NOT called (cooldown)
        $this->artisan('app:check-sensor-anomalies')->assertFailed();

        Http::assertNotSent(fn (Request $req) => $req->url() === $this->discordUrl);
    }

    public function test_discord_not_called_when_webhook_not_configured(): void
    {
        config(['services.discord.webhook_url' => null]);

        Http::fake([
            "http://{$this->relayIp}/status" => fn () => throw new \Exception('down'),
        ]);

        $this->artisan('app:check-sensor-anomalies')->assertFailed();

        Http::assertNothingSent();
    }
}

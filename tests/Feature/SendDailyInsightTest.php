<?php

namespace Tests\Feature;

use App\Console\Commands\SendDailyInsight;
use App\Models\LightReading;
use App\Models\RelayAutoConfig;
use App\Models\TemperatureReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendDailyInsight::class)]
class SendDailyInsightTest extends TestCase
{
    use RefreshDatabase;

    private string $discordUrl = 'https://discord.com/api/webhooks/test/token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.discord.webhook_url' => $this->discordUrl]);

        Http::fake([$this->discordUrl => Http::response('', 204)]);
    }

    public function test_sends_daily_insight_embed_to_discord(): void
    {
        $this->seedYesterdayData();

        $this->artisan('app:send-daily-insight')->assertSuccessful();

        Http::assertSent(fn (Request $req) => $req->url() === $this->discordUrl
            && isset($req['embeds'][0]['title'])
            && str_contains($req['embeds'][0]['title'], 'Daily Insight')
        );
    }

    public function test_embed_contains_light_temperature_humidity_fields(): void
    {
        $this->seedYesterdayData();

        $this->artisan('app:send-daily-insight')->assertSuccessful();

        Http::assertSent(function (Request $req) {
            $fields = $req['embeds'][0]['fields'] ?? [];
            $names = array_column($fields, 'name');

            return str_contains(implode(' ', $names), 'Cahaya')
                && str_contains(implode(' ', $names), 'Suhu')
                && str_contains(implode(' ', $names), 'Kelembaban')
                && str_contains(implode(' ', $names), 'Relay');
        });
    }

    public function test_embed_shows_no_data_when_no_readings(): void
    {
        // No DB records at all

        $this->artisan('app:send-daily-insight')->assertSuccessful();

        Http::assertSent(function (Request $req) {
            $json = json_encode($req['embeds'][0]['fields']);

            return str_contains($json, 'Tidak ada data');
        });
    }

    public function test_does_not_send_when_webhook_not_configured(): void
    {
        config(['services.discord.webhook_url' => null]);
        Http::fake(); // reset

        $this->artisan('app:send-daily-insight')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_embed_uses_green_color(): void
    {
        $this->seedYesterdayData();

        $this->artisan('app:send-daily-insight')->assertSuccessful();

        Http::assertSent(fn (Request $req) => ($req['embeds'][0]['color'] ?? null) === 0x57F287
        );
    }

    public function test_relay_insight_counts_auto_enabled_relays(): void
    {
        $this->seedYesterdayData();

        // Migration already seeded relay_auto_configs; just update auto_enabled
        RelayAutoConfig::whereIn('relay_id', [0, 2])->update(['auto_enabled' => true]);
        RelayAutoConfig::whereIn('relay_id', [1, 3])->update(['auto_enabled' => false]);

        $this->artisan('app:send-daily-insight')->assertSuccessful();

        Http::assertSent(fn (Request $req) => str_contains(
            json_encode($req['embeds'][0]['fields']),
            '2 dari 4 relay'
        ));
    }

    // ---- Helpers -------------------------------------------------------

    private function seedYesterdayData(): void
    {
        // Use DB::table to bypass $fillable and insert recorded_at directly.
        // Carbon timestamps are formatted as app-timezone strings so they match
        // what whereDate('recorded_at', yesterday) expects.
        $yesterday = now()->subDay();

        \Illuminate\Support\Facades\DB::table('light_readings')->insert([
            ['lux' => 100, 'recorded_at' => $yesterday->copy()->setTime(8, 0)->toDateTimeString()],
            ['lux' => 800, 'recorded_at' => $yesterday->copy()->setTime(12, 0)->toDateTimeString()],
            ['lux' => 300, 'recorded_at' => $yesterday->copy()->setTime(18, 0)->toDateTimeString()],
        ]);

        \Illuminate\Support\Facades\DB::table('temperature_readings')->insert([
            ['temperature' => 24, 'humidity' => 65, 'recorded_at' => $yesterday->copy()->setTime(8, 0)->toDateTimeString()],
            ['temperature' => 30, 'humidity' => 80, 'recorded_at' => $yesterday->copy()->setTime(12, 0)->toDateTimeString()],
            ['temperature' => 27, 'humidity' => 72, 'recorded_at' => $yesterday->copy()->setTime(18, 0)->toDateTimeString()],
        ]);
    }
}

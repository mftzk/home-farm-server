<?php

namespace Tests\Unit;

use App\Services\DiscordService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DiscordService::class)]
class DiscordServiceTest extends TestCase
{
    public function test_is_not_configured_when_webhook_url_is_empty(): void
    {
        config(['services.discord.webhook_url' => null]);

        $service = new DiscordService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_when_webhook_url_is_set(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.com/api/webhooks/test/token']);

        $service = new DiscordService;

        $this->assertTrue($service->isConfigured());
    }

    public function test_send_returns_false_when_not_configured(): void
    {
        config(['services.discord.webhook_url' => null]);
        Http::fake();

        $result = (new DiscordService)->send('hello');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_send_posts_content_to_webhook(): void
    {
        $url = 'https://discord.com/api/webhooks/test/token';
        config(['services.discord.webhook_url' => $url]);
        Http::fake([$url => Http::response('', 204)]);

        $result = (new DiscordService)->send('ping');

        $this->assertTrue($result);
        Http::assertSent(fn (Request $req) => $req->url() === $url
            && $req['content'] === 'ping'
        );
    }

    public function test_send_embed_posts_embeds_to_webhook(): void
    {
        $url = 'https://discord.com/api/webhooks/test/token';
        config(['services.discord.webhook_url' => $url]);
        Http::fake([$url => Http::response('', 204)]);

        $result = (new DiscordService)->sendEmbed([
            'title' => 'Test Alert',
            'color' => 0xED4245,
        ]);

        $this->assertTrue($result);
        Http::assertSent(fn (Request $req) => $req->url() === $url
            && isset($req['embeds'][0]['title'])
            && $req['embeds'][0]['title'] === 'Test Alert'
        );
    }

    public function test_send_returns_false_on_http_error(): void
    {
        $url = 'https://discord.com/api/webhooks/test/token';
        config(['services.discord.webhook_url' => $url]);
        Http::fake([$url => Http::response('Bad Request', 400)]);

        $result = (new DiscordService)->send('fail');

        $this->assertFalse($result);
    }

    public function test_send_returns_false_on_connection_failure(): void
    {
        $url = 'https://discord.com/api/webhooks/test/token';
        config(['services.discord.webhook_url' => $url]);
        Http::fake([$url => fn () => throw new \Exception('Connection refused')]);

        $result = (new DiscordService)->send('fail');

        $this->assertFalse($result);
    }
}

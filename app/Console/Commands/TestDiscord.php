<?php

namespace App\Console\Commands;

use App\Services\DiscordService;
use Illuminate\Console\Command;

class TestDiscord extends Command
{
    protected $signature = 'app:test-discord';

    protected $description = 'Check Discord webhook config and send a test message';

    public function handle(DiscordService $discord): int
    {
        $url = config('services.discord.webhook_url');

        $this->line('Checking Discord webhook configuration...');
        $this->newLine();

        if (empty($url)) {
            $this->error('DISCORD_WEBHOOK_URL is not set in .env');
            $this->line('  Add this to your .env file:');
            $this->line('  DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/YOUR_ID/YOUR_TOKEN');

            return self::FAILURE;
        }

        // Mask token for display (show first 40 chars)
        $masked = strlen($url) > 40
            ? substr($url, 0, 40).'...'
            : $url;

        $this->info("Webhook URL : {$masked}");
        $this->line('Sending test message...');

        $ok = $discord->sendEmbed([
            'title' => '✅ Test Berhasil',
            'description' => 'Koneksi Discord webhook dari Home Farm Monitor berjalan normal.',
            'color' => 0x57F287,
            'fields' => [
                ['name' => 'App',      'value' => config('app.name'), 'inline' => true],
                ['name' => 'Timezone', 'value' => config('app.timezone'), 'inline' => true],
            ],
            'footer' => ['text' => 'Home Farm Monitor — app:test-discord'],
            'timestamp' => now()->toIso8601String(),
        ]);

        if ($ok) {
            $this->info('Test message sent successfully. Check your Discord channel.');

            return self::SUCCESS;
        }

        $this->error('Failed to send test message. Check the webhook URL and your network connection.');

        return self::FAILURE;
    }
}

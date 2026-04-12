<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    private ?string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.discord.webhook_url');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->webhookUrl);
    }

    /**
     * Send a plain message to Discord.
     */
    public function send(string $content): bool
    {
        return $this->post(['content' => $content]);
    }

    /**
     * Send an embed message to Discord.
     *
     * @param  array{title?: string, description?: string, color?: int, fields?: array, footer?: array, timestamp?: string}  $embed
     */
    public function sendEmbed(array $embed): bool
    {
        return $this->post(['embeds' => [$embed]]);
    }

    private function post(array $payload): bool
    {
        if (! $this->isConfigured()) {
            Log::debug('DiscordService: webhook URL not configured, skipping.');

            return false;
        }

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $payload);

            if (! $response->successful()) {
                Log::warning("DiscordService: webhook returned HTTP {$response->status()}");

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("DiscordService: failed to send webhook — {$e->getMessage()}");

            return false;
        }
    }
}

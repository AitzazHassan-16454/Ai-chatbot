<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends messages to the n8n workflow webhooks.
 * n8n is an automation tool that can react to these messages (send emails,
 * log chats, alert on high token usage, etc.).
 */
class N8nService
{
    private string $webhookUrl;

    private string $feedbackUrl;

    private ?string $webhookSecret;

    public function __construct()
    {
        $this->webhookUrl = config('services.n8n.webhook_url');
        $this->feedbackUrl = config('services.n8n.feedback_url');
        $this->webhookSecret = config('services.n8n.webhook_secret');
    }

    /** Tells n8n about a chat message that was sent. */
    public function notifyChatMessage(array $data): bool
    {
        return $this->sendWebhook('chat-message', $data);
    }

    /** Tells n8n when a user gets close to their token limit. */
    public function notifyTokenAlert(array $data): bool
    {
        return $this->sendWebhook('token-alert', $data);
    }

    /** Tells n8n about new user feedback. */
    public function notifyFeedback(array $data): bool
    {
        return $this->sendWebhook('feedback', $data, $this->feedbackUrl);
    }

    /** Tells n8n to send a reply email about feedback. */
    public function sendFeedbackResponseEmail(array $data): bool
    {
        return $this->sendWebhook('feedback-response', $data, $this->feedbackUrl);
    }

    /**
     * Sends one message to an n8n webhook.
     * Returns true if n8n accepted it, false otherwise (it never throws).
     */
    private function sendWebhook(string $event, array $payload, ?string $webhookUrl = null): bool
    {
        $url = $webhookUrl ?? $this->webhookUrl;

        if (empty($url)) {
            Log::warning('n8n webhook URL not configured');

            return false;
        }

        try {
            $body = [
                'event' => $event,
                'payload' => $payload,
                'timestamp' => now()->toIso8601String(),
            ];

            $headers = ['Content-Type' => 'application/json'];

            if ($this->webhookSecret) {
                $headers['X-Webhook-Secret'] = $this->webhookSecret;
            }

            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->post($url, $body);

            if ($response->successful()) {
                Log::info("n8n webhook sent: $event", ['status' => $response->status()]);

                return true;
            }

            Log::error("n8n webhook failed: $event", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error("n8n webhook error: $event", ['error' => $e->getMessage()]);

            return false;
        }
    }
}

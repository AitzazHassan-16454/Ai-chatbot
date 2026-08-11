<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

use function Laravel\Ai\agent;

/**
 * Talks to the Google Gemini API using the Laravel AI SDK.
 *
 * Two ways to get an answer from the AI:
 *   - generateContent()  waits for the whole reply and returns it as an array.
 *   - streamContent()    returns a generator that yields each piece of text
 *                        as it arrives, so the browser can stream it live.
 *
 * Both return ['error' => ..., 'status' => ...] instead of throwing, so the
 * controller can show a friendly message to the user.
 */
class GeminiService
{
    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash');
    }

    /** Asks Gemini for one complete answer and returns it as an array. */
    public function generateContent(array $history): array
    {
        [$messages, $prompt, $attachments] = $this->splitHistory($history);

        try {
            // The Laravel AI SDK builds the Gemini request for us.
            $response = agent('', $messages)->prompt(
                $prompt,
                $attachments,
                provider: 'gemini',
                model: $this->model,
                timeout: 120,
            );

            $reply = $response->text;

            if (empty($reply)) {
                return ['error' => 'Empty response from AI.', 'status' => 500];
            }

            return [
                'reply' => $reply,
                'tokens_used' => $this->totalTokens($response->usage),
                'usage_metadata' => $this->usageMetadata($response->usage),
            ];
        } catch (Throwable $e) {
            Log::error('Gemini error: '.$e->getMessage());

            return $this->errorResult($e);
        }
    }

    /**
     * Asks Gemini for an answer, but yields each piece of text as it is
     * generated instead of waiting for the whole reply.
     *
     * @return \Generator<int, array{type: string, text?: string, tokens_used?: int, usage_metadata?: array, message?: string, status?: int}>
     */
    public function streamContent(array $history): \Generator
    {
        [$messages, $prompt, $attachments] = $this->splitHistory($history);

        try {
            $stream = agent('', $messages)->stream(
                $prompt,
                $attachments,
                provider: 'gemini',
                model: $this->model,
                timeout: 120,
            );

            // Walk through the SDK's stream events and turn them into the
            // same chunks the browser already understands.
            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    yield ['type' => 'text', 'text' => $event->delta];
                } elseif ($event instanceof StreamEnd) {
                    yield [
                        'type' => 'done',
                        'tokens_used' => $this->totalTokens($event->usage),
                        'usage_metadata' => $this->usageMetadata($event->usage),
                    ];
                } elseif ($event instanceof StreamError) {
                    yield [
                        'type' => 'error',
                        'message' => $event->message,
                        'status' => is_numeric($event->type) ? (int) $event->type : 500,
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::error('Gemini stream error: '.$e->getMessage());

            $result = $this->errorResult($e);

            yield ['type' => 'error', 'message' => $result['error'], 'status' => $result['status']];
        }
    }

    /** True when an API key is set in the config. */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /** The name of the AI model we are using. */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * Splits the Gemini-style history (from ChatController::buildHistory)
     * into what the Laravel AI SDK expects: the past messages, plus the
     * current user message (text + files) which becomes the prompt.
     *
     * @return array{0: array<int, mixed>, 1: string, 2: array<int, mixed>}
     */
    private function splitHistory(array $history): array
    {
        $messages = $this->historyToMessages($history);

        // The last turn is the message the user just sent.
        $lastMessage = array_pop($messages) ?? new UserMessage('');

        return [
            $messages,
            $lastMessage->content ?? '',
            $lastMessage->attachments->all(),
        ];
    }

    /**
     * Converts the Gemini-style history into the message objects the
     * Laravel AI SDK understands.
     *
     * @return array<int, UserMessage|AssistantMessage>
     */
    private function historyToMessages(array $history): array
    {
        $messages = [];

        foreach ($history as $turn) {
            $role = $turn['role'] ?? 'user';
            $parts = $turn['parts'] ?? [];

            // Pull the text and any files out of the 'parts' list.
            $text = '';
            $attachments = [];

            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $text = $part['text'];
                }

                if (isset($part['inline_data'])) {
                    $attachments[] = $this->attachmentFromInlineData($part['inline_data']);
                }
            }

            // Gemini calls the assistant role 'model'.
            $messages[] = $role === 'model'
                ? new AssistantMessage($text)
                : new UserMessage($text, $attachments);
        }

        return $messages;
    }

    /**
     * Turns a file stored as 'inline_data' into an attachment the SDK accepts.
     */
    private function attachmentFromInlineData(array $inlineData): Base64Image|Base64Document
    {
        $mimeType = $inlineData['mime_type'] ?? '';
        $data = $inlineData['data'] ?? '';

        return str_starts_with($mimeType, 'image/')
            ? new Base64Image($data, $mimeType)
            : new Base64Document($data, $mimeType);
    }

    /** Counts the total tokens the SDK reported for a request. */
    private function totalTokens(Usage $usage): int
    {
        return $usage->promptTokens + $usage->completionTokens + $usage->reasoningTokens;
    }

    /**
     * Converts the SDK's token counts into the shape the rest of the app
     * expects (promptTokenCount, candidatesTokenCount, ...).
     */
    private function usageMetadata(Usage $usage): array
    {
        return [
            'promptTokenCount' => $usage->promptTokens,
            'candidatesTokenCount' => $usage->completionTokens,
            'thoughtsTokenCount' => $usage->reasoningTokens,
            'totalTokenCount' => $this->totalTokens($usage),
        ];
    }

    /** Turns an SDK exception into the ['error' => ..., 'status' => ...] shape. */
    private function errorResult(Throwable $e): array
    {
        $status = match (true) {
            $e instanceof RateLimitedException => 429,
            $e instanceof ProviderOverloadedException => 503,
            $e instanceof RequestException => $e->response?->status() ?? 500,
            default => 500,
        };

        return [
            'error' => $this->errorMessageFrom($e, $status),
            'status' => $status,
        ];
    }

    /** Picks a friendly message for an SDK exception. */
    private function errorMessageFrom(Throwable $e, int $status): string
    {
        // The SDK sometimes wraps the original HTTP response.
        $response = $e instanceof RequestException
            ? $e->response
            : ($e->getPrevious() instanceof RequestException ? $e->getPrevious()->response : null);

        $body = $response?->json() ?? [];

        // Gemini sometimes tells us the problem directly (e.g. 'quota').
        if (isset($body['error']) && is_string($body['error'])) {
            return $this->friendlyApiError($body['error']);
        }

        return $this->friendlyError($status);
    }

    /** Turns an error code sent by the Gemini API into a readable message. */
    private function friendlyApiError(string $code): string
    {
        return match ($code) {
            'quota' => 'API quota exceeded.',
            'api_key_invalid' => 'Invalid API key.',
            default => 'Gemini error: '.$code,
        };
    }

    /** Turns an HTTP status code into a friendly message for the user. */
    private function friendlyError(?int $status, string $fallback = 'Something went wrong. Please try again.'): string
    {
        return match ($status) {
            429 => "You've reached the AI request limit. Please wait a minute and try again.",
            403 => 'The AI service is rejecting requests right now. Please try again later.',
            400 => 'That message was blocked by safety filters. Please rephrase and try again.',
            500, 502, 503, 504 => 'The AI service is temporarily unavailable. Please try again in a moment.',
            0, null => 'Could not reach the AI service. Check your connection and try again.',
            default => $fallback,
        };
    }
}

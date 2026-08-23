<?php

namespace App\Services;

use App\Agents\AssistantAgent;
use App\Agents\CodeReviewerAgent;
use App\Agents\LanguageTutorAgent;
use App\Agents\SummarizerAgent;
use App\Models\User;
use Laravel\Ai\Contracts\Agent;
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

    /** Asks the AI agent for one complete answer and returns it as an array. */
    public function generateContent(array $history, ?User $user = null, ?int $conversationId = null, string $mode = 'general'): array
    {
        [$messages, $prompt, $attachments] = $this->splitHistory($history);
        $this->indexTextAttachments($attachments, $user, $conversationId);

        try {
            $agent = $this->resolveAgent($mode, $messages, $user, $conversationId);
            $response = $agent->prompt(
                $prompt,
                $attachments,
                provider: $this->providers(),
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
            Log::error('AI error: '.$e->getMessage());

            return $this->errorResult($e);
        }
    }

    /**
     * Asks the AI agent for an answer, yielding each piece of text as it is
     * generated instead of waiting for the whole reply.
     *
     * @return \Generator<int, array{type: string, text?: string, tokens_used?: int, usage_metadata?: array, message?: string, status?: int}>
     */
    public function streamContent(array $history, ?User $user = null, ?int $conversationId = null, string $mode = 'general'): \Generator
    {
        [$messages, $prompt, $attachments] = $this->splitHistory($history);
        $this->indexTextAttachments($attachments, $user, $conversationId);

        try {
            $agent = $this->resolveAgent($mode, $messages, $user, $conversationId);
            $stream = $agent->stream(
                $prompt,
                $attachments,
                provider: $this->providers(),
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
            Log::error('AI stream error: '.$e->getMessage());

            $result = $this->errorResult($e);

            yield ['type' => 'error', 'message' => $result['error'], 'status' => $result['status']];
        }
    }

    /**
     * Get the primary provider or fallback providers list for automatic failover.
     *
     * @return array<int, string>|string
     */
    private function providers(): array|string
    {
        $providers = ['gemini'];

        if (config('ai.providers.openai.key')) {
            $providers[] = 'openai';
        }
        if (config('ai.providers.groq.key')) {
            $providers[] = 'groq';
        }
        if (config('ai.providers.deepseek.key')) {
            $providers[] = 'deepseek';
        }
        if (config('ai.providers.anthropic.key')) {
            $providers[] = 'anthropic';
        }

        return count($providers) > 1 ? $providers : 'gemini';
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

    /**
     * Auto-indexes uploaded text/PDF files into the document knowledge store.
     */
    private function indexTextAttachments(array $attachments, ?User $user, ?int $conversationId): void
    {
        if (empty($attachments)) {
            return;
        }

        foreach ($attachments as $att) {
            $mime = $att->mimeType ?? '';
            $name = $att->name ?? 'document.txt';

            if (str_contains($mime, 'text/') || str_contains($mime, 'pdf') || str_contains($mime, 'json') || str_contains($mime, 'csv')) {
                try {
                    $content = method_exists($att, 'content') ? $att->content() : '';
                    if (! empty($content)) {
                        app(DocumentKnowledgeService::class)->indexDocument($name, $content, $user, $conversationId);
                    }
                } catch (Throwable $e) {
                    Log::warning("Failed to index attachment {$name}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Resolves the appropriate Agent class based on the requested mode.
     */
    private function resolveAgent(string $mode, array $messages, ?User $user, ?int $conversationId): Agent
    {
        return match ($mode) {
            'code' => new CodeReviewerAgent($messages, $user, $conversationId),
            'tutor' => new LanguageTutorAgent($messages, $user, $conversationId),
            'summary' => new SummarizerAgent($messages, $user, $conversationId),
            default => new AssistantAgent($messages, $user, $conversationId),
        };
    }
}

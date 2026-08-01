<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model = 'gemini-2.5-flash';
    }

    public function generateContent(array $history): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $maxRetries = 3;
        $response = null;
        $status = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::withBody(
                json_encode(['contents' => $history]),
                'application/json'
            )->post($url);

            if ($response->successful()) {
                break;
            }

            $status = $response->status();

            if ($status === 429 || $status === 403 || $status === 400) {
                break;
            }

            if ($status === 503 && $attempt < $maxRetries) {
                usleep($attempt * 1000000);

                continue;
            }

            break;
        }

        if (! $response || ! $response->successful()) {
            $error = $this->friendlyError($status);

            Log::error('Gemini error: '.($response?->body() ?? 'no response'));

            return ['error' => $error, 'status' => $status];
        }

        $data = $response->json();

        if (isset($data['error'])) {
            $errorCode = $data['error'];
            $error = match ($errorCode) {
                'quota' => 'API quota exceeded.',
                'api_key_invalid' => 'Invalid API key.',
                default => 'Gemini error: '.$errorCode,
            };
            Log::error('Gemini API error: '.$errorCode);

            return ['error' => $error, 'status' => 429];
        }

        if (isset($data['promptFeedback']['blockReason'])) {
            return ['error' => 'Message blocked by safety filters.', 'status' => 400];
        }

        $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty($reply)) {
            Log::warning('Gemini empty: '.json_encode($data));

            return ['error' => 'Empty response from AI.', 'status' => 500];
        }

        $tokensUsed = $data['usageMetadata']['totalTokenCount'] ?? 0;

        return [
            'reply' => $reply,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * @return \Generator<int, array{type: string, text?: string, tokens_used?: int, usage_metadata?: array, message?: string, status?: int}>
     */
    public function streamContent(array $history): \Generator
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:streamGenerateContent?alt=sse";

        $body = ['contents' => $history];

        $maxRetries = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $client = new Client([
                    'timeout' => 120,
                    'stream' => true,
                ]);

                $response = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $this->apiKey,
                    ],
                    'json' => $body,
                ]);

                $stream = $response->getBody();
                $buffer = '';
                $usageMetadata = null;

                while (! $stream->eof()) {
                    $buffer .= $stream->read(4096);

                    while (($newlinePos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $newlinePos);
                        $buffer = substr($buffer, $newlinePos + 1);
                        $line = trim($line);

                        if ($line === '' || ! str_starts_with($line, 'data: ')) {
                            continue;
                        }

                        $jsonStr = substr($line, 6);
                        $chunk = json_decode($jsonStr, true);

                        if ($chunk === null) {
                            continue;
                        }

                        if (isset($chunk['error'])) {
                            $errorCode = $chunk['error'];
                            $error = match ($errorCode) {
                                'quota' => 'API quota exceeded.',
                                'api_key_invalid' => 'Invalid API key.',
                                default => 'Gemini error: '.$errorCode,
                            };
                            Log::error('Gemini stream API error: '.$errorCode);
                            yield ['type' => 'error', 'message' => $error, 'status' => 429];

                            return;
                        }

                        if (isset($chunk['promptFeedback']['blockReason'])) {
                            yield ['type' => 'error', 'message' => 'Message blocked by safety filters.', 'status' => 400];

                            return;
                        }

                        if (! empty($chunk['candidates'][0]['content']['parts'])) {
                            foreach ($chunk['candidates'][0]['content']['parts'] as $part) {
                                if (isset($part['text']) && $part['text'] !== '') {
                                    yield ['type' => 'text', 'text' => $part['text']];
                                }
                            }
                        }

                        if (isset($chunk['usageMetadata'])) {
                            $usageMetadata = $chunk['usageMetadata'];
                        }

                        if (isset($chunk['candidates'][0]['finishReason'])) {
                            yield [
                                'type' => 'done',
                                'tokens_used' => $usageMetadata['totalTokenCount'] ?? 0,
                                'usage_metadata' => $usageMetadata,
                            ];

                            return;
                        }
                    }
                }

                yield [
                    'type' => 'done',
                    'tokens_used' => $usageMetadata['totalTokenCount'] ?? 0,
                    'usage_metadata' => $usageMetadata,
                ];

                return;

            } catch (ConnectException $e) {
                $lastException = $e;
                if ($attempt < $maxRetries) {
                    usleep($attempt * 1000000);

                    continue;
                }
            } catch (ClientException $e) {
                $status = $e->getResponse()->getStatusCode();
                yield ['type' => 'error', 'message' => $this->friendlyError($status), 'status' => $status];

                return;
            } catch (ServerException $e) {
                $status = $e->getResponse()->getStatusCode();
                if ($status === 503 && $attempt < $maxRetries) {
                    usleep($attempt * 1000000);

                    continue;
                }
                yield ['type' => 'error', 'message' => $this->friendlyError($status), 'status' => $status];

                return;
            } catch (\Throwable $e) {
                Log::error('Gemini stream error: '.$e->getMessage());
                yield ['type' => 'error', 'message' => $this->friendlyError(500), 'status' => 500];

                return;
            }
        }

        Log::error('Gemini stream failed after retries', ['error' => $lastException?->getMessage()]);
        yield ['type' => 'error', 'message' => $this->friendlyError(0), 'status' => 503];
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

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

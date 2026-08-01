<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_is_configured_returns_true_when_api_key_set(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $service = new GeminiService();

        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_missing(): void
    {
        config(['services.gemini.api_key' => '']);

        $service = new GeminiService();

        $this->assertFalse($service->isConfigured());
    }

    public function test_generate_content_returns_reply_on_success(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $responseBody = [
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Hello there!']]]],
            ],
            'usageMetadata' => ['totalTokenCount' => 5],
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($responseBody),
        ]);

        $service = new GeminiService();
        $result = $service->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'Hello']]],
        ]);

        $this->assertArrayHasKey('reply', $result);
        $this->assertEquals('Hello there!', $result['reply']);
        $this->assertEquals(5, $result['tokens_used']);
    }

    public function test_generate_content_returns_error_on_api_failure(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
        ]);

        $service = new GeminiService();
        $result = $service->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'Hello']]],
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('API quota exceeded.', $result['error']);
    }

    public function test_generate_content_returns_error_on_empty_response(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $responseBody = [
            'candidates' => [
                ['content' => ['parts' => [['text' => '']]]],
            ],
            'usageMetadata' => ['totalTokenCount' => 0],
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($responseBody),
        ]);

        $service = new GeminiService();
        $result = $service->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'Hello']]],
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Empty response from AI.', $result['error']);
    }

    public function test_generate_content_returns_error_on_safety_block(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $responseBody = [
            'promptFeedback' => ['blockReason' => 'SAFETY'],
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($responseBody),
        ]);

        $service = new GeminiService();
        $result = $service->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'Bad content']]],
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Message blocked by safety filters.', $result['error']);
    }

    public function test_generate_content_retries_on_503(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $callCount = 0;
        Http::fake(function ($request) use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return Http::response(['error' => 'overloaded'], 503);
            }

            $successBody = [
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Recovered!']]]],
                ],
                'usageMetadata' => ['totalTokenCount' => 3],
            ];

            return Http::response($successBody);
        });

        $service = new GeminiService();
        $result = $service->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'Hello']]],
        ]);

        $this->assertArrayHasKey('reply', $result);
        $this->assertEquals('Recovered!', $result['reply']);
        $this->assertEquals(3, $callCount);
    }
}

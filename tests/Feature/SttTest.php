<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SttTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('stt');
    }

    public function test_audio_is_required(): void
    {
        $response = $this->postJson('/stt', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['audio']);
    }

    public function test_unsupported_audio_format_is_rejected(): void
    {
        Http::fake();

        $file = UploadedFile::fake()->createWithContent('recording.exe', str_repeat('x', 1024));

        $response = $this->postJson('/stt', ['audio' => $file]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['audio']);
    }

    public function test_guest_can_transcribe_audio(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Hello world']]]],
                ],
            ]),
        ]);

        $file = UploadedFile::fake()->createWithContent('recording.wav', str_repeat('x', 4096));

        $response = $this->postJson('/stt', ['audio' => $file]);

        $response->assertOk();
        $response->assertJson(['text' => 'Hello world']);
    }

    public function test_empty_transcript_returns_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '   ']]]],
                ],
            ]),
        ]);

        $file = UploadedFile::fake()->createWithContent('recording.wav', str_repeat('x', 4096));

        $response = $this->postJson('/stt', ['audio' => $file]);

        $response->assertUnprocessable();
        $response->assertJson(['error' => 'No speech detected in the audio. Please try again.']);
    }

    public function test_gemini_failure_returns_server_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $file = UploadedFile::fake()->createWithContent('recording.wav', str_repeat('x', 4096));

        $response = $this->postJson('/stt', ['audio' => $file]);

        $response->assertStatus(500);
        $response->assertJson(['error' => 'Could not transcribe audio. Try again.']);
    }
}

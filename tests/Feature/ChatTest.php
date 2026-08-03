<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('chat');
    }

    private function geminiResponse(string $text, int $tokens = 10): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [['text' => $text]]]],
            ],
            'usageMetadata' => [
                'promptTokenCount' => (int) round($tokens / 2),
                'candidatesTokenCount' => (int) round($tokens / 2),
                'totalTokenCount' => $tokens,
            ],
        ];
    }

    public function test_guest_can_send_message(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('Hello! How can I help you?')),
        ]);

        $response = $this->postJson('/chat', [
            'message' => 'Hello',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'reply',
            'tokens_used',
            'api_status',
        ]);
        $response->assertJson(['reply' => 'Hello! How can I help you?']);
    }

    public function test_message_is_required(): void
    {
        $response = $this->postJson('/chat', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_message_cannot_exceed_5000_characters(): void
    {
        $response = $this->postJson('/chat', [
            'message' => str_repeat('a', 5001),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_message_with_valid_length_is_accepted(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('Response')),
        ]);

        $response = $this->postJson('/chat', [
            'message' => str_repeat('a', 5000),
        ]);

        $response->assertOk();
    }

    public function test_invalid_attachment_type_is_rejected(): void
    {
        $response = $this->postJson('/chat', [
            'message' => 'Check this file',
            'attachments' => [
                [
                    'mime_type' => 'application/msword',
                    'data' => base64_encode('test'),
                    'name' => 'test.doc',
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['attachments.*.mime_type']);
    }

    public function test_valid_image_attachment_is_accepted(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('I see an image.', 15)),
        ]);

        $response = $this->postJson('/chat', [
            'message' => 'What is this?',
            'attachments' => [
                [
                    'mime_type' => 'image/jpeg',
                    'data' => base64_encode('fake-image-data'),
                    'name' => 'photo.jpg',
                ],
            ],
        ]);

        $response->assertOk();
    }

    public function test_authenticated_user_message_is_saved(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('AI reply', 20)),
        ]);

        $response = $this->actingAs($user)->postJson('/chat', [
            'message' => 'Hello AI',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('conversations', ['user_id' => $user->id]);
        $this->assertDatabaseHas('messages', ['role' => 'user', 'content' => 'Hello AI']);
        $this->assertDatabaseHas('messages', ['role' => 'assistant', 'content' => 'AI reply']);
    }

    public function test_guest_message_is_not_saved(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('Reply')),
        ]);

        $this->postJson('/chat', ['message' => 'Hello']);

        $this->assertEquals(0, Conversation::count());
        $this->assertEquals(0, Message::count());
    }

    public function test_every_api_call_is_logged_with_token_breakdown(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('AI reply', 20)),
        ]);

        $this->actingAs($user)->postJson('/chat', ['message' => 'Hello AI'])->assertOk();

        $this->assertDatabaseHas('token_usage_logs', [
            'user_id' => $user->id,
            'endpoint' => 'generateContent',
            'model' => 'gemini-2.5-flash',
            'prompt_tokens' => 10,
            'candidates_tokens' => 10,
            'total_tokens' => 20,
            'status' => 200,
        ]);
    }

    public function test_token_log_records_failed_api_call(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
        ]);

        $this->postJson('/chat', ['message' => 'Hello']);

        $this->assertDatabaseHas('token_usage_logs', [
            'endpoint' => 'generateContent',
            'status' => 429,
            'total_tokens' => 0,
        ]);
    }

    public function test_user_token_limit_is_enforced(): void
    {
        $user = User::factory()->create([
            'tokens_used' => 500000,
            'tokens_limit' => 500000,
        ]);

        $response = $this->actingAs($user)->postJson('/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(429);
        $response->assertJsonFragment(['error' => 'Token limit reached. Used 500,000 of 500,000.']);
    }

    public function test_conversation_id_allows_continuing_conversation(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Existing Chat',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('Continuing!', 10)),
        ]);

        $response = $this->actingAs($user)->postJson('/chat', [
            'message' => 'Continue please',
            'conversation_id' => $conversation->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Continue please',
        ]);
    }
}

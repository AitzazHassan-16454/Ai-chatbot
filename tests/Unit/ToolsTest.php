<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Tools\CurrentTimeTool;
use App\Tools\GetUserTokenUsageTool;
use App\Tools\SearchConversationsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_time_tool_returns_time(): void
    {
        $tool = new CurrentTimeTool;
        $response = $tool->handle(new Request([]));

        $data = json_decode($response, true);
        $this->assertArrayHasKey('current_time', $data);
        $this->assertArrayHasKey('timezone', $data);
        $this->assertEquals('Asia/Karachi', $data['timezone']);
    }

    public function test_get_user_token_usage_tool_for_guest(): void
    {
        $tool = new GetUserTokenUsageTool(null);
        $response = $tool->handle(new Request([]));

        $data = json_decode($response, true);
        $this->assertEquals('guest', $data['user_type']);
        $this->assertArrayHasKey('system_api_status', $data);
    }

    public function test_get_user_token_usage_tool_for_user(): void
    {
        $user = User::factory()->create([
            'tokens_used' => 500,
            'tokens_limit' => 1000,
        ]);

        $tool = new GetUserTokenUsageTool($user);
        $response = $tool->handle(new Request([]));

        $data = json_decode($response, true);
        $this->assertEquals($user->name, $data['user_name']);
        $this->assertEquals(500, $data['tokens_used']);
        $this->assertEquals(500, $data['tokens_remaining']);
    }

    public function test_search_conversations_tool_finds_matching_message(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Python Basics',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'How do I use list comprehensions in Python?',
        ]);

        $tool = new SearchConversationsTool($user);
        $response = $tool->handle(new Request(['query' => 'Python']));

        $data = json_decode($response, true);
        $this->assertEquals('Python', $data['query']);
        $this->assertEquals(1, $data['count']);
        $this->assertCount(1, $data['results']);
        $this->assertEquals('Python Basics', $data['results'][0]['conversation']);
    }
}

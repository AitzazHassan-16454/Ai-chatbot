<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_conversations(): void
    {
        $user = User::factory()->create();

        Conversation::create(['user_id' => $user->id, 'title' => 'Chat 1']);
        Conversation::create(['user_id' => $user->id, 'title' => 'Chat 2']);

        $response = $this->actingAs($user)->get('/conversations');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_user_can_only_see_own_conversations(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Conversation::create(['user_id' => $user1->id, 'title' => 'User 1 Chat']);
        Conversation::create(['user_id' => $user2->id, 'title' => 'User 2 Chat']);

        $response = $this->actingAs($user1)->get('/conversations');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['title' => 'User 1 Chat']);
    }

    public function test_user_can_load_conversation_with_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Test Chat']);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hi there!',
        ]);

        $response = $this->actingAs($user)->get("/conversations/{$conversation->id}");

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Test Chat']);
        $response->assertJsonCount(2, 'messages');
    }

    public function test_user_cannot_load_other_users_conversation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user2->id, 'title' => 'Private Chat']);

        $response = $this->actingAs($user1)->get("/conversations/{$conversation->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_delete_own_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'To Delete']);

        $response = $this->actingAs($user)->delete("/conversations/{$conversation->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    public function test_user_cannot_delete_other_users_conversation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user2->id, 'title' => 'Protected']);

        $response = $this->actingAs($user1)->delete("/conversations/{$conversation->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    public function test_conversations_are_ordered_by_most_recent(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(now()->subDays(2));
        $old = Conversation::create(['user_id' => $user->id, 'title' => 'Old']);

        Carbon::setTestNow(now()->addDays(2));
        $recent = Conversation::create(['user_id' => $user->id, 'title' => 'Recent']);

        Carbon::setTestNow();

        $response = $this->actingAs($user)->get('/conversations');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertEquals($recent->id, $data[0]['id']);
    }
}

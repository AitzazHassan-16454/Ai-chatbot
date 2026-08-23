<?php

namespace App\Tools;

use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchConversationsTool implements Tool
{
    public function __construct(
        protected ?User $user = null
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return "Searches the logged-in user's past chat conversations and messages for specific keywords, topics, or past questions.";
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        if (! $this->user) {
            return (string) json_encode(['status' => 'guest', 'message' => 'User is a guest, no saved history available.']);
        }

        $query = $request['query'] ?? '';
        if (empty($query)) {
            return (string) json_encode(['status' => 'empty_query', 'messages' => []]);
        }

        $results = Message::whereHas('conversation', function ($q) {
            $q->where('user_id', $this->user->id);
        })
        ->where('content', 'like', "%{$query}%")
        ->with('conversation:id,title')
        ->latest()
        ->limit(5)
        ->get(['id', 'conversation_id', 'role', 'content', 'created_at']);

        $formatted = $results->map(fn ($msg) => [
            'conversation' => $msg->conversation?->title ?? 'Untitled',
            'role' => $msg->role,
            'snippet' => mb_substr($msg->content, 0, 200),
            'date' => $msg->created_at?->toDateTimeString(),
        ]);

        return (string) json_encode([
            'query' => $query,
            'count' => $formatted->count(),
            'results' => $formatted,
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('The search keyword or topic to search for in past chat history.'),
        ];
    }
}

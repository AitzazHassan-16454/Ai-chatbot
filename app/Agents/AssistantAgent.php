<?php

namespace App\Agents;

use App\Models\User;
use App\Tools\AskDocumentTool;
use App\Tools\CurrentTimeTool;
use App\Tools\GetUserTokenUsageTool;
use App\Tools\SearchConversationsTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class AssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param Message[] $history
     */
    public function __construct(
        public array $history = [],
        public ?User $user = null,
        public ?int $conversationId = null
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a helpful, intelligent, and friendly AI assistant. Provide clear, accurate, and concise answers. You have access to tools to search uploaded documents (RAG), search past user conversations, check token usage, and get current time when appropriate.';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array
     */
    public function tools(): iterable
    {
        return [
            new CurrentTimeTool,
            new SearchConversationsTool($this->user),
            new GetUserTokenUsageTool($this->user),
            new AskDocumentTool($this->user, $this->conversationId),
        ];
    }
}

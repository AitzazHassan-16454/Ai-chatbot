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

class LanguageTutorAgent implements Agent, Conversational, HasTools
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
        return 'You are an encouraging and skilled Language & Grammar Tutor specializing in English, Urdu, and Roman Urdu. Help users improve their vocabulary, grammar, spelling, and phrasing. Point out mistakes gently, provide clear corrections, and give practical examples.';
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

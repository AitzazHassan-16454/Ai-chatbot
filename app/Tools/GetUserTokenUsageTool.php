<?php

namespace App\Tools;

use App\Models\SystemUsage;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetUserTokenUsageTool implements Tool
{
    public function __construct(
        protected ?User $user = null
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Gets the current user personal token budget, tokens used, remaining quota, and system status.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $systemUsage = SystemUsage::getUsage();

        if (! $this->user) {
            return (string) json_encode([
                'user_type' => 'guest',
                'system_api_status' => $systemUsage->apiStatusArray(),
            ], JSON_PRETTY_PRINT);
        }

        return (string) json_encode([
            'user_name' => $this->user->name,
            'tokens_used' => $this->user->tokens_used,
            'tokens_limit' => $this->user->tokens_limit,
            'tokens_remaining' => $this->user->tokensRemaining(),
            'usage_percentage' => round(($this->user->tokens_used / max(1, $this->user->tokens_limit)) * 100, 1),
            'system_api_status' => $systemUsage->apiStatusArray(),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

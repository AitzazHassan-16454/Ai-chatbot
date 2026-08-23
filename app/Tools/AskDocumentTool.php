<?php

namespace App\Tools;

use App\Models\User;
use App\Services\DocumentKnowledgeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AskDocumentTool implements Tool
{
    public function __construct(
        protected ?User $user = null,
        protected ?int $conversationId = null
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Searches uploaded PDF files, text documents, and knowledge bases using RAG vector similarity search to answer user questions about documents.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = $request['query'] ?? '';

        if (blank($query)) {
            return (string) json_encode(['status' => 'empty_query', 'passages' => []]);
        }

        $service = app(DocumentKnowledgeService::class);
        $passages = $service->searchSimilar($query, $this->user, $this->conversationId, topK: 3);

        return (string) json_encode([
            'query' => $query,
            'count' => count($passages),
            'passages' => $passages,
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('The question or topic to search within uploaded documents.'),
        ];
    }
}

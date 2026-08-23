<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DocumentKnowledgeService;
use App\Tools\AskDocumentTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class DocumentKnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunk_text_splits_long_content(): void
    {
        $service = new DocumentKnowledgeService;
        $text = str_repeat('This is a test document paragraph for RAG indexing. ', 50);

        $chunks = $service->chunkText($text, chunkSize: 200, overlap: 50);

        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_cosine_similarity_calculation(): void
    {
        $service = new DocumentKnowledgeService;

        $vecA = [1.0, 0.0, 0.0];
        $vecB = [1.0, 0.0, 0.0];
        $vecC = [0.0, 1.0, 0.0];

        $this->assertEquals(1.0, $service->cosineSimilarity($vecA, $vecB));
        $this->assertEquals(0.0, $service->cosineSimilarity($vecA, $vecC));
    }

    public function test_index_and_search_document_with_faked_embeddings(): void
    {
        Embeddings::fake();

        $user = User::factory()->create();
        $service = new DocumentKnowledgeService;

        $fileName = 'laravel_guide.txt';
        $content = 'Laravel AI SDK makes integrating artificial intelligence models seamless and powerful with agents, tools, and embeddings vector search.';

        $indexedCount = $service->indexDocument($fileName, $content, $user);

        $this->assertGreaterThan(0, $indexedCount);
        $this->assertDatabaseHas('document_chunks', [
            'file_name' => 'laravel_guide.txt',
            'user_id' => $user->id,
        ]);

        $tool = new AskDocumentTool($user);
        $response = $tool->handle(new Request(['query' => 'Laravel AI']));

        $data = json_decode($response, true);
        $this->assertEquals('Laravel AI', $data['query']);
        $this->assertGreaterThan(0, $data['count']);
        $this->assertEquals('laravel_guide.txt', $data['passages'][0]['file_name']);
    }
}

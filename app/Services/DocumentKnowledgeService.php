<?php

namespace App\Services;

use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

class DocumentKnowledgeService
{
    /**
     * Splits document text into chunks, generates vector embeddings using Laravel AI SDK,
     * and saves them to the database for RAG retrieval.
     *
     * @return int Number of chunks indexed.
     */
    public function indexDocument(
        string $fileName,
        string $content,
        ?User $user = null,
        ?int $conversationId = null,
        int $chunkSize = 600,
        int $overlap = 100
    ): int {
        if (blank($content)) {
            return 0;
        }

        $chunks = $this->chunkText($content, $chunkSize, $overlap);

        if (empty($chunks)) {
            return 0;
        }

        $embeddings = [];
        try {
            // Generate vector embeddings via Laravel AI Embeddings facade
            $response = Embeddings::for($chunks)->generate();
            $embeddings = array_map(fn ($item) => $item->vector, $response->embeddings);
        } catch (Throwable $e) {
            Log::warning("Embedding generation failed for {$fileName}: {$e->getMessage()}. Saving chunks without embeddings.");
        }

        $count = 0;
        foreach ($chunks as $index => $chunkText) {
            DocumentChunk::create([
                'user_id' => $user?->id,
                'conversation_id' => $conversationId,
                'file_name' => $fileName,
                'chunk_index' => $index,
                'chunk_text' => $chunkText,
                'embedding' => $embeddings[$index] ?? null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Searches indexed document chunks using Cosine Similarity against the query vector.
     *
     * @return array<int, array{file_name: string, snippet: string, similarity: float}>
     */
    public function searchSimilar(
        string $query,
        ?User $user = null,
        ?int $conversationId = null,
        int $topK = 3
    ): array {
        if (blank($query)) {
            return [];
        }

        $queryVector = null;
        try {
            $response = Embeddings::for([$query])->generate();
            $queryVector = $response->embeddings[0]->vector ?? null;
        } catch (Throwable $e) {
            Log::warning("Query embedding failed: {$e->getMessage()}. Falling back to keyword search.");
        }

        $queryBuilder = DocumentChunk::query();

        if ($user) {
            $queryBuilder->where('user_id', $user->id);
        }

        if ($conversationId) {
            $queryBuilder->where('conversation_id', $conversationId);
        }

        $chunks = $queryBuilder->get();

        if ($chunks->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($chunks as $chunk) {
            $similarity = 0.0;

            if ($queryVector && ! empty($chunk->embedding)) {
                $similarity = $this->cosineSimilarity($queryVector, $chunk->embedding);
            } else {
                // Fallback keyword relevance score
                $similarity = (float) (mb_stripos($chunk->chunk_text, $query) !== false ? 0.5 : 0.0);
            }

            if ($similarity > 0.1) {
                $results[] = [
                    'file_name' => $chunk->file_name,
                    'snippet' => $chunk->chunk_text,
                    'similarity' => round($similarity, 4),
                ];
            }
        }

        // Sort by similarity score descending
        usort($results, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Splits text into overlapping chunks.
     *
     * @return array<int, string>
     */
    public function chunkText(string $text, int $chunkSize = 600, int $overlap = 100): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $length = mb_strlen($text);

        if ($length <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;

        while ($start < $length) {
            $chunk = mb_substr($text, $start, $chunkSize);
            $chunks[] = $chunk;
            $start += ($chunkSize - $overlap);
        }

        return $chunks;
    }

    /**
     * Calculates Cosine Similarity between two vector arrays.
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = min(count($vecA), count($vecB));

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}

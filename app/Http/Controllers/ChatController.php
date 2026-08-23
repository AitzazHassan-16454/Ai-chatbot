<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SystemUsage;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\N8nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    // The file types the AI is allowed to read, and the biggest allowed size.
    private const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff',
        'application/pdf',
        'text/plain', 'text/csv',
    ];

    private const MAX_SIZE = 20 * 1024 * 1024;

    /*
     * The chat page sends the user's message here, and we reply with the AI's
     * full answer as one JSON response. There is also a streaming version
     * (askStreaming) that sends the answer piece by piece.
     *
     * The flow for both is the same:
     *   1. Read the message and any attached files.
     *   2. Check the limits (user tokens, system quota, AI configured).
     *   3. Find or create the conversation and build the chat history.
     *   4. Ask the AI for an answer.
     *   5. Save the chat and update the usage counters.
     */
    public function ask(AskRequest $request, GeminiService $gemini): JsonResponse
    {
        // Step 1 - Read the message, conversation id, mode and any attached files.
        $message = $request->validated('message');
        $conversationId = $request->validated('conversation_id');
        $mode = $request->validated('mode', 'general');
        $attachments = $this->validateAttachments($request->validated('attachments', []));

        // If an attachment was not allowed, return the error to the browser.
        if ($this->hasError($attachments)) {
            return response()->json($attachments, 422);
        }

        $user = $request->user();

        // Step 2 - Check that the user and system are allowed to use the AI now.
        $limitError = $this->checkLimits($user, $gemini);
        if ($limitError) {
            return response()->json($limitError['body'], $limitError['status']);
        }

        try {
            // Step 3 - Find the existing conversation (or create a new one)
            // and build the full history the AI needs to remember the chat.
            [$conversation, $history] = $this->buildHistory($user, $message, $attachments, $conversationId);

            // Step 4 - Ask the AI for an answer.
            $result = $gemini->generateContent($history, $user, $conversation?->id, $mode);

            // Step 5 - If the AI returned an error, report it to the browser.
            if (isset($result['error'])) {
                $this->handleError($result, $user, $message, $attachments, $conversation);
                $this->logTokenUsage($user, $conversation, 'generateContent', $gemini->model(), $result, $result['status'] ?? 500);

                return response()->json([
                    'error' => $result['error'],
                    'api_status' => SystemUsage::getUsage()->apiStatusArray(),
                ], $result['status'] ?? 500);
            }

            // Step 6 - Save the chat and update all the usage counters.
            $this->saveMessages($conversation, $message, $result['reply'], $attachments);
            $this->afterSuccessfulReply($user, $conversation, $message, $result['reply'], $attachments, $result, 'generateContent', $gemini->model());

            // Step 7 - Send the AI's answer back to the browser.
            return response()->json([
                'reply' => $result['reply'],
                'conversation_id' => $conversation?->id,
                'tokens_used' => $result['tokens_used'],
                'user_tokens_remaining' => $user ? $user->tokensRemaining() : null,
                'api_status' => SystemUsage::getUsage()->apiStatusArray(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat failed: '.$e->getMessage());

            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    /*
     * Same chat flow as ask(), but the AI's answer is streamed back to the
     * browser piece by piece (using Server-Sent Events) so the user sees text
     * appear as it is generated, instead of waiting for the whole reply.
     */
    public function askStreaming(AskRequest $request, GeminiService $gemini): StreamedResponse
    {
        // Step 1 - Read the message, conversation id, mode and any attached files.
        $message = $request->validated('message');
        $conversationId = $request->validated('conversation_id');
        $mode = $request->validated('mode', 'general');
        $attachments = $this->validateAttachments($request->validated('attachments', []));

        if ($this->hasError($attachments)) {
            return $this->streamError($attachments['error'], 422);
        }

        $user = $request->user();

        // Step 2 - Check the limits.
        $limitError = $this->checkLimits($user, $gemini);
        if ($limitError) {
            return $this->streamError(
                $limitError['body']['error'],
                $limitError['status'],
                $limitError['body']['api_status'] ?? null
            );
        }

        // Step 3 - Find or create the conversation and build the history.
        [$conversation, $history] = $this->buildHistory($user, $message, $attachments, $conversationId);

        // Step 4 - Start the AI stream.
        $stream = $gemini->streamContent($history, $user, $conversation?->id, $mode);

        // Step 5 - Send the reply to the browser one piece at a time.
        return response()->stream(function () use ($stream, $user, $message, $attachments, $conversation, $gemini) {
            set_time_limit(0);

            $fullReply = '';
            $tokensUsed = 0;
            $finalChunk = null;

            foreach ($stream as $chunk) {
                if ($chunk['type'] === 'text') {
                    // Send each new piece of text as soon as it arrives.
                    $fullReply .= $chunk['text'];
                    $this->sendSse('chunk', ['text' => $chunk['text']]);
                } elseif ($chunk['type'] === 'error') {
                    // The AI failed mid-stream - report the error and stop.
                    $this->handleStreamError($chunk['message'], $chunk['status'] ?? 500, $user, $message, $attachments, $conversation, $gemini);

                    return;
                } elseif ($chunk['type'] === 'done') {
                    // The AI finished - remember how many tokens it used.
                    $tokensUsed = $chunk['tokens_used'] ?? 0;
                    $finalChunk = $chunk;
                }
            }

            // If the AI produced no text at all, treat it as a quota error.
            if (empty($fullReply)) {
                $this->handleStreamError('API quota exceeded.', 429, $user, $message, $attachments, $conversation, $gemini);

                return;
            }

            // Save the chat and update all the usage counters.
            $this->saveMessages($conversation, $message, $fullReply, $attachments);
            $this->afterSuccessfulReply(
                $user,
                $conversation,
                $message,
                $fullReply,
                $attachments,
                $finalChunk ?? ['tokens_used' => $tokensUsed],
                'streamGenerateContent',
                $gemini->model()
            );

            // Tell the browser the stream is finished.
            $this->sendSse('done', [
                'conversation_id' => $conversation?->id,
                'tokens_used' => $tokensUsed,
                'usage_metadata' => $finalChunk['usage_metadata'] ?? null,
                'system_tokens_used' => TokenUsageLog::totalTokens(),
                'user_tokens_remaining' => $user ? $user->tokensRemaining() : null,
                'api_status' => SystemUsage::getUsage()->apiStatusArray(),
            ]);
        }, 200, $this->sseHeaders());
    }

    /**
     * Checks the three limits before the AI is called.
     * Returns an array of ['status' => ..., 'body' => [...]] if something is
     * wrong, or null if the user is allowed to chat right now.
     */
    private function checkLimits(?User $user, GeminiService $gemini): ?array
    {
        // 1. Each user has a personal token budget.
        if ($user && ! $user->hasTokensRemaining()) {
            return [
                'status' => 429,
                'body' => [
                    'error' => 'Token limit reached. Used '
                        .number_format($user->tokens_used)
                        .' of '.number_format($user->tokens_limit).'.',
                ],
            ];
        }

        // 2. The whole system has a daily request budget.
        $systemUsage = SystemUsage::getUsage();

        if ($systemUsage->isExhausted()) {
            $message = 'System busy, try later.';

            if ($resetsAt = $systemUsage->resetsAt()) {
                $message .= ' Quota resets at '.$resetsAt->timezone('Asia/Karachi')->format('g:i A').' (tomorrow).';
            }

            return [
                'status' => 503,
                'body' => [
                    'error' => $message,
                    'api_status' => $systemUsage->apiStatusArray(),
                ],
            ];
        }

        // 3. The AI service needs an API key to be configured.
        if (! $gemini->isConfigured()) {
            return ['status' => 500, 'body' => ['error' => 'AI service not configured.']];
        }

        return null;
    }

    /**
     * Saves the user's message and the AI's reply to the database.
     * Guests are not saved (their conversation is null).
     */
    private function saveMessages(?Conversation $conversation, string $userMessage, string $aiReply, array $attachments): void
    {
        if (! $conversation) {
            return;
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'attachments' => $attachments ?: null,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $aiReply,
        ]);

        // Bump the timestamp so the conversation moves to the top of the list.
        $conversation->touch();
    }

    /**
     * Runs after every successful AI reply: logs the token usage, counts the
     * request towards the system quota, counts tokens towards the user's
     * budget, and notifies the n8n webhook.
     */
    private function afterSuccessfulReply(
        ?User $user,
        ?Conversation $conversation,
        string $userMessage,
        string $aiReply,
        array $attachments,
        array $result,
        string $endpoint,
        string $model
    ): void {
        $tokensUsed = $result['tokens_used'] ?? 0;

        $this->logTokenUsage($user, $conversation, $endpoint, $model, $result, 200);

        // Count this request towards the system's daily limit.
        SystemUsage::incrementRequests();

        // Count the tokens towards the user's personal budget.
        if ($tokensUsed > 0 && $user) {
            $user->increment('tokens_used', $tokensUsed);
        }

        // Warn the owner when a user gets close to their token limit (70%).
        if ($tokensUsed > 0 && $user && $user->tokens_limit > 0) {
            $percentage = ($user->tokens_used / $user->tokens_limit) * 100;

            if ($percentage >= 70) {
                app(N8nService::class)->notifyTokenAlert([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'email' => $user->email,
                    'tokens_used' => $user->tokens_used,
                    'tokens_limit' => $user->tokens_limit,
                    'percentage' => round($percentage),
                    'remaining' => $user->tokensRemaining(),
                ]);
            }
        }

        // Send a short summary of the chat to the n8n webhook.
        app(N8nService::class)->notifyChatMessage([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Guest',
            'conversation_id' => $conversation?->id,
            'message' => Str::limit($userMessage, 200),
            'reply' => Str::limit($aiReply, 200),
            'tokens_used' => $tokensUsed,
            'has_attachments' => ! empty($attachments),
        ]);
    }

    /**
     * Checks each attached file against the allowed types and size.
     * Returns either a clean list of attachments, or an array with an
     * 'error' key if a file was rejected.
     */
    private function validateAttachments(array $attachments): array
    {
        $clean = [];

        foreach ($attachments as $att) {
            $type = $att['mime_type'] ?? '';
            $data = $att['data'] ?? '';

            if (! in_array($type, self::ALLOWED_TYPES)) {
                return ['error' => "File type not allowed: {$type}"];
            }

            $bytes = base64_decode($data, true);

            if ($bytes === false || strlen($bytes) > self::MAX_SIZE) {
                return ['error' => 'File too large. Max 20MB.'];
            }

            $clean[] = ['mime_type' => $type, 'data' => $data, 'name' => $att['name'] ?? 'image'];
        }

        return $clean;
    }

    /** True when an array came back from validateAttachments as an error. */
    private function hasError(array $result): bool
    {
        return isset($result['error']);
    }

    /**
     * Finds the conversation the user wants to continue (or creates a new one),
     * then builds the full chat history in the format Gemini expects.
     * Returns [$conversation, $history]. The conversation is null for guests.
     */
    private function buildHistory(?User $user, string $message, array $attachments, ?int $conversationId): array
    {
        $conversation = null;
        $history = [];

        if ($user) {
            // Find the existing conversation, or create a brand new one.
            if ($conversationId) {
                $conversation = Conversation::where('id', $conversationId)
                    ->where('user_id', $user->id)
                    ->first();
            }

            if (! $conversation) {
                $conversation = Conversation::create([
                    'user_id' => $user->id,
                    'title' => Str::limit(strip_tags($message), 50),
                ]);
            }

            // Load the old messages so the AI remembers what was said before.
            $history = $conversation->messages()
                ->orderBy('created_at')
                ->get()
                ->map(fn ($m) => [
                    'role' => $m->role === 'assistant' ? 'model' : 'user',
                    'parts' => $this->messageParts($m->content, $m->attachments ?? []),
                ])
                ->values()
                ->all();
        }

        // Add the current message (and any files) as the newest turn.
        $history[] = [
            'role' => 'user',
            'parts' => $this->messageParts($message, $attachments),
        ];

        return [$conversation, $history];
    }

    /** Converts text + files into the 'parts' format the Gemini API expects. */
    private function messageParts(string $text, array $attachments): array
    {
        $parts = [['text' => $text]];

        foreach ($attachments as $att) {
            $parts[] = ['inline_data' => ['mime_type' => $att['mime_type'], 'data' => $att['data'] ?? '']];
        }

        return $parts;
    }

    /**
     * Handles a failed AI reply: marks the quota as used up on a 429, deletes
     * empty conversations, and tells n8n about the error.
     */
    private function handleError(array $result, ?User $user, string $message, array $attachments, ?Conversation $conversation): void
    {
        $error = $result['error'];
        $status = $result['status'] ?? 500;

        // If we hit the request limit, mark today's quota as used so the next
        // visitor gets a friendly "busy" message instead of more AI errors.
        if ($status === 429) {
            $usage = SystemUsage::getUsage();
            $usage->update(['requests_today' => $usage->requests_day_limit]);
        }

        // If the AI failed before anything was saved, remove the empty conversation.
        if ($conversation && $conversation->messages()->count() === 0) {
            $conversation->delete();
        }

        app(N8nService::class)->notifyChatMessage([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Guest',
            'conversation_id' => $conversation?->id,
            'message' => Str::limit($message, 200),
            'reply' => null,
            'tokens_used' => 0,
            'has_attachments' => ! empty($attachments),
            'error' => $error,
            'error_code' => $status,
        ]);
    }

    /** Used by the streaming chat: handles the error, then sends it to the browser. */
    private function handleStreamError(
        string $message,
        int $status,
        ?User $user,
        string $userMessage,
        array $attachments,
        ?Conversation $conversation,
        GeminiService $gemini
    ): void {
        $result = ['error' => $message, 'status' => $status];

        $this->handleError($result, $user, $userMessage, $attachments, $conversation);
        $this->logTokenUsage($user, $conversation, 'streamGenerateContent', $gemini->model(), $result, $status);

        $this->sendSse('error', [
            'message' => $message,
            'status' => $status,
            'api_status' => SystemUsage::getUsage()->apiStatusArray(),
        ]);
    }

    /**
     * Records how many tokens the AI used, so we can keep track of spending.
     * Wrapped in try/catch so a logging problem never breaks the chat.
     */
    private function logTokenUsage(
        ?User $user,
        ?Conversation $conversation,
        string $endpoint,
        string $model,
        array $data = [],
        ?int $status = null
    ): void {
        try {
            $usageMetadata = $data['usage_metadata'] ?? [];

            TokenUsageLog::record([
                'user_id' => $user?->id,
                'conversation_id' => $conversation?->id,
                'endpoint' => $endpoint,
                'model' => $model,
                'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                'candidates_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                'thoughts_tokens' => $usageMetadata['thoughtsTokenCount'] ?? 0,
                'total_tokens' => $data['tokens_used'] ?? $usageMetadata['totalTokenCount'] ?? 0,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            Log::error('Token usage log failed: '.$e->getMessage());
        }
    }

    /** Sends a Server-Sent Event (one message in the stream) to the browser. */
    private function sendSse(string $event, array $data): void
    {
        echo "event: {$event}\ndata: ".json_encode($data)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    /** The special HTTP headers needed for streaming. */
    private function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    /** Sends an error to the browser as a streamed response. */
    private function streamError(string $message, int $status, ?array $apiStatus = null): StreamedResponse
    {
        return response()->stream(function () use ($message, $status, $apiStatus) {
            $this->sendSse('error', [
                'message' => $message,
                'status' => $status,
                'api_status' => $apiStatus,
            ]);
        }, $status === 200 ? 200 : $status, $this->sseHeaders());
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SystemUsage;
use App\Services\GeminiService;
use App\Services\N8nService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    private const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff',
        'application/pdf',
        'text/plain', 'text/csv',
    ];
    private const MAX_SIZE = 20 * 1024 * 1024;

    public function ask(AskRequest $request, GeminiService $gemini)
    {
        $message = $request->validated('message');
        $conversationId = $request->validated('conversation_id');
        $attachments = $request->validated('attachments', []);

        $cleanAttachments = $this->validateAttachments($attachments);
        if (is_array($cleanAttachments) && isset($cleanAttachments['error'])) {
            return response()->json($cleanAttachments, 422);
        }

        $user = $request->user();

        if ($user && !$user->hasTokensRemaining()) {
            return response()->json([
                'error' => "Token limit reached. Used " . number_format($user->tokens_used) . " of " . number_format($user->tokens_limit) . ".",
            ], 429);
        }

        $systemUsage = SystemUsage::getUsage();

        if ($systemUsage->isExhausted()) {
            $msg = 'System busy, try later.';
            if ($resetsAt = $systemUsage->resetsAt()) {
                $msg .= " Quota resets at " . $resetsAt->timezone('Asia/Karachi')->format('g:i A') . " (tomorrow).";
            }
            return response()->json([
                'error' => $msg,
                'api_status' => $systemUsage->apiStatusArray(),
            ], 503);
        }

        if (!$gemini->isConfigured()) {
            return response()->json(['error' => 'AI service not configured.'], 500);
        }

        try {
            $conversation = null;
            $history = $this->buildHistory($user, $message, $cleanAttachments, $conversationId, $conversation);

            $result = $gemini->generateContent($history);

            if (isset($result['error'])) {
                $this->handleError($result, $user, $message, $cleanAttachments, $conversation, $systemUsage);
                return response()->json([
                    'error' => $result['error'],
                    'api_status' => $systemUsage->fresh()->apiStatusArray(),
                ], $result['status'] ?? 500);
            }

            $reply = $result['reply'];
            $tokensUsed = $result['tokens_used'];

            if ($conversation) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $message,
                    'attachments' => $cleanAttachments ?: null,
                ]);
                Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $reply]);
                $conversation->touch();
            }

            SystemUsage::incrementRequests();
            $systemUsage->refresh();

            if ($tokensUsed > 0 && $user) {
                $user->increment('tokens_used', $tokensUsed);

                if ($user->tokens_limit > 0) {
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
            }

            app(N8nService::class)->notifyChatMessage([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Guest',
                'conversation_id' => $conversation?->id,
                'message' => Str::limit($message, 200),
                'reply' => Str::limit($reply, 200),
                'tokens_used' => $tokensUsed,
                'has_attachments' => !empty($cleanAttachments),
            ]);

            return response()->json([
                'reply' => $reply,
                'conversation_id' => $conversation?->id,
                'tokens_used' => $tokensUsed,
                'user_tokens_remaining' => $user ? $user->tokensRemaining() : null,
                'api_status' => $systemUsage->fresh()->apiStatusArray(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat failed: ' . $e->getMessage());
            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    public function askStreaming(AskRequest $request, GeminiService $gemini): StreamedResponse
    {
        $message = $request->validated('message');
        $conversationId = $request->validated('conversation_id');
        $attachments = $request->validated('attachments', []);

        $cleanAttachments = $this->validateAttachments($attachments);
        if (is_array($cleanAttachments) && isset($cleanAttachments['error'])) {
            return $this->streamError($cleanAttachments['error'], 422);
        }

        $user = $request->user();

        if ($user && !$user->hasTokensRemaining()) {
            $errorMsg = "Token limit reached. Used " . number_format($user->tokens_used) . " of " . number_format($user->tokens_limit) . ".";
            return $this->streamError($errorMsg, 429);
        }

        $systemUsage = SystemUsage::getUsage();

        if ($systemUsage->isExhausted()) {
            $msg = 'System busy, try later.';
            if ($resetsAt = $systemUsage->resetsAt()) {
                $msg .= " Quota resets at " . $resetsAt->timezone('Asia/Karachi')->format('g:i A') . " (tomorrow).";
            }
            return $this->streamError($msg, 503, $systemUsage->apiStatusArray());
        }

        if (!$gemini->isConfigured()) {
            return $this->streamError('AI service not configured.', 500);
        }

        $conversation = null;
        $history = $this->buildHistory($user, $message, $cleanAttachments, $conversationId, $conversation);

        $stream = $gemini->streamContent($history);
        $fullReply = '';
        $tokensUsed = 0;

        return response()->stream(function () use ($stream, &$fullReply, &$tokensUsed, $user, $message, $cleanAttachments, $conversation, $systemUsage) {
            set_time_limit(0);

            foreach ($stream as $chunk) {
                if ($chunk['type'] === 'text') {
                    $fullReply .= $chunk['text'];
                    echo "event: chunk\ndata: " . json_encode(['text' => $chunk['text']]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                } elseif ($chunk['type'] === 'error') {
                    $this->handleError([
                        'error' => $chunk['message'],
                        'status' => $chunk['status'] ?? 500,
                    ], $user, $message, $cleanAttachments, $conversation, $systemUsage);

                    echo "event: error\ndata: " . json_encode([
                        'message' => $chunk['message'],
                        'status' => $chunk['status'] ?? 500,
                        'api_status' => $systemUsage->fresh()->apiStatusArray(),
                    ]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    return;
                } elseif ($chunk['type'] === 'done') {
                    $tokensUsed = $chunk['tokens_used'] ?? 0;
                }
            }

            if (empty($fullReply)) {
                $this->handleError([
                    'error' => 'API quota exceeded.',
                    'status' => 429,
                ], $user, $message, $cleanAttachments, $conversation, $systemUsage);

                echo "event: error\ndata: " . json_encode([
                    'message' => 'API quota exceeded.',
                    'status' => 429,
                    'api_status' => $systemUsage->fresh()->apiStatusArray(),
                ]) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                return;
            }

            if ($conversation) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $message,
                    'attachments' => $cleanAttachments ?: null,
                ]);
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $fullReply,
                ]);
                $conversation->touch();
            }

            SystemUsage::incrementRequests();
            $systemUsage->refresh();

            if ($tokensUsed > 0 && $user) {
                $user->increment('tokens_used', $tokensUsed);

                if ($user->tokens_limit > 0) {
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
            }

            app(N8nService::class)->notifyChatMessage([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Guest',
                'conversation_id' => $conversation?->id,
                'message' => Str::limit($message, 200),
                'reply' => Str::limit($fullReply, 200),
                'tokens_used' => $tokensUsed,
                'has_attachments' => !empty($cleanAttachments),
            ]);

            echo "event: done\ndata: " . json_encode([
                'conversation_id' => $conversation?->id,
                'tokens_used' => $tokensUsed,
                'user_tokens_remaining' => $user ? $user->tokensRemaining() : null,
                'api_status' => $systemUsage->fresh()->apiStatusArray(),
            ]) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function streamError(string $message, int $status, ?array $apiStatus = null): StreamedResponse
    {
        return response()->stream(function () use ($message, $status, $apiStatus) {
            echo "event: error\ndata: " . json_encode([
                'message' => $message,
                'status' => $status,
                'api_status' => $apiStatus,
            ]) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, $status === 200 ? 200 : $status, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function validateAttachments(array $attachments): array|object
    {
        $clean = [];
        foreach ($attachments as $att) {
            $type = $att['mime_type'] ?? '';
            $data = $att['data'] ?? '';

            if (!in_array($type, self::ALLOWED_TYPES)) {
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

    private function buildHistory(
        $user,
        string $message,
        array $cleanAttachments,
        ?int $conversationId,
        ?Conversation &$conversation
    ): array {
        $history = [];

        if ($user) {
            if ($conversationId) {
                $conversation = Conversation::where('id', $conversationId)->where('user_id', $user->id)->first();
            }

            if (!$conversation) {
                $conversation = Conversation::create([
                    'user_id' => $user->id,
                    'title' => Str::limit(strip_tags($message), 50),
                ]);
            }

            $history = $conversation->messages()->orderBy('created_at')->get()->map(function ($m) {
                $parts = [['text' => $m->content]];
                if (!empty($m->attachments)) {
                    foreach ($m->attachments as $att) {
                        $parts[] = ['inline_data' => ['mime_type' => $att['mime_type'], 'data' => $att['data'] ?? '']];
                    }
                }
                return ['role' => $m->role === 'assistant' ? 'model' : 'user', 'parts' => $parts];
            })->values()->all();
        }

        $currentParts = [['text' => $message]];
        foreach ($cleanAttachments as $att) {
            $currentParts[] = ['inline_data' => ['mime_type' => $att['mime_type'], 'data' => $att['data']]];
        }
        $history[] = ['role' => 'user', 'parts' => $currentParts];

        return $history;
    }

    private function handleError(
        array $result,
        $user,
        string $message,
        array $cleanAttachments,
        ?Conversation $conversation,
        SystemUsage $systemUsage
    ): void {
        $error = $result['error'];
        $status = $result['status'] ?? 500;

        if ($status === 429) {
            $systemUsage->update([
                'requests_today' => $systemUsage->requests_day_limit,
            ]);
        }

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
            'has_attachments' => !empty($cleanAttachments),
            'error' => $error,
            'error_code' => $status,
        ]);
    }
}

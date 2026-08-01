<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\SystemUsage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;
use function Laravel\Prompts\spin;

class ChatCommand extends Command
{
    protected $signature = 'chat';
    protected $description = 'Start an interactive chat session with Gemini AI';

    private ?User $user = null;
    private ?Conversation $conversation = null;

    public function handle(): int
    {
        $this->printBanner();

        while (true) {
            $input = $this->getInput();

            if ($input === null) {
                continue;
            }

            if ($this->handleCommand($input)) {
                break;
            }
        }

        return self::SUCCESS;
    }

    private function printBanner(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║       🤖 AI Chatbot (CLI)           ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->newLine();
        $this->line('Type <comment>/help</comment> for commands, or just start chatting.');
        $this->line('Type <comment>/quit</comment> to exit.');
        $this->newLine();
    }

    private function getInput(): ?string
    {
        $input = text(
            label: $this->getPromptPrefix(),
            placeholder: 'Type a message or /help',
        );

        return trim($input);
    }

    private function getPromptPrefix(): string
    {
        if ($this->user && $this->conversation) {
            return "Logged in | #{$this->conversation->id} | ➤";
        }

        if ($this->user) {
            return "Logged in | ➤";
        }

        if ($this->conversation) {
            return "Guest | #{$this->conversation->id} | ➤";
        }

        return "Guest | ➤";
    }

    private function handleCommand(string $input): bool
    {
        if (empty($input)) {
            return false;
        }

        $parts = explode(' ', $input, 2);
        $command = strtolower($parts[0]);
        $arg = $parts[1] ?? null;

        return match ($command) {
            '/help'    => $this->showHelp(),
            '/login'   => $this->login(),
            '/logout'  => $this->logout(),
            '/new'     => $this->newConversation(),
            '/list'    => $this->listConversations(),
            '/load'    => $this->loadConversation($arg),
            '/delete'  => $this->deleteConversation($arg),
            '/tokens'  => $this->showTokens(),
            '/status'  => $this->showStatus(),
            '/quit'    => true,
            default    => $this->sendMessage($input),
        };
    }

    private function showHelp(): bool
    {
        $this->newLine();
        $this->line('<comment>Commands:</comment>');
        $this->line('  <info>/help</info>       Show this help message');
        $this->line('  <info>/login</info>      Log in with email and password');
        $this->line('  <info>/logout</info>     Log out');
        $this->line('  <info>/new</info>        Start a new conversation');
        $this->line('  <info>/list</info>       List your saved conversations');
        $this->line('  <info>/load {id}</info>  Load a conversation by ID');
        $this->line('  <info>/delete {id}</info> Delete a conversation by ID');
        $this->line('  <info>/tokens</info>     Show your token usage');
        $this->line('  <info>/status</info>     Show API quota status');
        $this->line('  <info>/quit</info>       Exit the chat');
        $this->line('  <info>{message}</info>   Send a message to Gemini AI');
        $this->newLine();

        return false;
    }

    private function login(): bool
    {
        if ($this->user) {
            $this->warn('Already logged in as ' . $this->user->email);
            return false;
        }

        $email = text('Email', required: true);
        $pass = password('Password', required: true);

        if (Auth::attempt(['email' => $email, 'password' => $pass])) {
            Auth::loginUsingId(Auth::user()->id);
            $this->user = Auth::user();
            $this->conversation = null;
            info('Logged in as ' . $this->user->email);
        } else {
            error('Invalid email or password.');
        }

        return false;
    }

    private function logout(): bool
    {
        $this->user = null;
        $this->conversation = null;
        Auth::logout();
        info('Logged out.');
        return false;
    }

    private function newConversation(): bool
    {
        $this->conversation = null;
        info('Started a new conversation.');
        return false;
    }

    private function listConversations(): bool
    {
        if (!$this->user) {
            $this->warn('Log in first to see saved conversations.');
            return false;
        }

        $conversations = Conversation::where('user_id', $this->user->id)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        if ($conversations->isEmpty()) {
            info('No saved conversations.');
            return false;
        }

        $rows = $conversations->map(fn ($c) => [
            $c->id,
            $c->title,
            $c->messages_count ?? $c->messages()->count(),
            $c->updated_at->diffForHumans(),
        ]);

        $this->newLine();
        $this->table(
            ['ID', 'Title', 'Messages', 'Last Active'],
            $rows
        );
        $this->newLine();

        return false;
    }

    private function loadConversation(?string $id): bool
    {
        if (!$this->user) {
            $this->warn('Log in first to load conversations.');
            return false;
        }

        if (!$id || !ctype_digit($id)) {
            $this->warn('Usage: /load {conversation_id}');
            return false;
        }

        $conversation = Conversation::where('id', (int) $id)
            ->where('user_id', $this->user->id)
            ->first();

        if (!$conversation) {
            error('Conversation not found.');
            return false;
        }

        $this->conversation = $conversation;
        $this->line("Loaded conversation #{$conversation->id}: <info>{$conversation->title}</info>");

        $messages = $conversation->messages()->orderBy('created_at')->get();

        foreach ($messages as $msg) {
            if ($msg->role === 'user') {
                $this->newLine();
                $this->line("<info>You:</info> {$msg->content}");
            } else {
                $this->newLine();
                $this->line("<comment>AI:</comment> {$msg->content}");
            }
        }
        $this->newLine();

        return false;
    }

    private function deleteConversation(?string $id): bool
    {
        if (!$this->user) {
            $this->warn('Log in first to delete conversations.');
            return false;
        }

        if (!$id || !ctype_digit($id)) {
            $this->warn('Usage: /delete {conversation_id}');
            return false;
        }

        $conversation = Conversation::where('id', (int) $id)
            ->where('user_id', $this->user->id)
            ->first();

        if (!$conversation) {
            error('Conversation not found.');
            return false;
        }

        $confirmed = confirm("Delete \"{$conversation->title}\"?", default: false);

        if ($confirmed) {
            $conversation->messages()->delete();
            $conversation->delete();

            if ($this->conversation && $this->conversation->id === (int) $id) {
                $this->conversation = null;
            }

            info('Deleted.');
        } else {
            info('Cancelled.');
        }

        return false;
    }

    private function showTokens(): bool
    {
        if (!$this->user) {
            $this->warn('Log in first to see token usage.');
            return false;
        }

        $this->user->refresh();
        $used = number_format($this->user->tokens_used);
        $limit = number_format($this->user->tokens_limit);
        $pct = $this->user->tokens_limit > 0
            ? round(($this->user->tokens_used / $this->user->tokens_limit) * 100, 1)
            : 0;

        $this->newLine();
        $this->line("Tokens: <info>{$used}</info> / <comment>{$limit}</comment> ({$pct}%)");
        $this->newLine();

        return false;
    }

    private function showStatus(): bool
    {
        $usage = SystemUsage::getUsage();

        $pct = $usage->effectivePercentage();
        $level = $usage->statusLevel();
        $used = number_format($usage->requests_today);
        $limit = number_format($usage->requests_day_limit);
        $rpm = $usage::currentRpm();

        $this->newLine();
        $this->line("API Status: <info>{$level}</info> ({$pct}%)");
        $this->line("Requests today: {$used} / {$limit}");
        $this->line("RPM: {$rpm}");

        if ($resetsAt = $usage->resetsAt()) {
            $this->line("Quota resets at: " . $resetsAt->timezone('Asia/Karachi')->format('g:i A'));
        }
        $this->newLine();

        return false;
    }

    private function sendMessage(string $message): bool
    {
        if (strlen($message) > 5000) {
            error('Message too long. Max 5000 characters.');
            return false;
        }

        if ($this->user && !$this->user->hasTokensRemaining()) {
            $used = number_format($this->user->tokens_used);
            $limit = number_format($this->user->tokens_limit);
            error("Token limit reached. Used {$used} of {$limit}.");
            return false;
        }

        $systemUsage = SystemUsage::getUsage();
        if ($systemUsage->isExhausted()) {
            error('System busy. Try later.');
            if ($resetsAt = $systemUsage->resetsAt()) {
                $this->line('Quota resets at: ' . $resetsAt->timezone('Asia/Karachi')->format('g:i A'));
            }
            return false;
        }

        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            error('AI service not configured. Set GEMINI_API_KEY in .env');
            return false;
        }

        try {
            $history = $this->buildHistory($message);

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

            $response = spin(function () use ($url, $history) {
                return Http::withBody(
                    json_encode(['contents' => $history]),
                    'application/json'
                )->timeout(60)->post($url);
            }, 'Thinking...');

            if (!$response->successful()) {
                $status = $response->status();
                $error = match (true) {
                    $status === 429 => 'API quota exceeded.',
                    $status === 403 => 'Invalid API key.',
                    default => 'Failed to get AI response.',
                };
                error($error);

                if ($this->conversation && $this->conversation->messages()->count() === 0) {
                    $this->conversation->delete();
                    $this->conversation = null;
                }

                return false;
            }

            $data = $response->json();

            if (isset($data['promptFeedback']['blockReason'])) {
                error('Message blocked by safety filters.');
                if ($this->conversation && $this->conversation->messages()->count() === 0) {
                    $this->conversation->delete();
                    $this->conversation = null;
                }
                return false;
            }

            $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($reply)) {
                error('Empty response from AI.');
                if ($this->conversation && $this->conversation->messages()->count() === 0) {
                    $this->conversation->delete();
                    $this->conversation = null;
                }
                return false;
            }

            $this->saveMessages($message, $reply);

            $tokensUsed = $data['usageMetadata']['totalTokenCount'] ?? 0;

            SystemUsage::incrementRequests();

            if ($tokensUsed > 0 && $this->user) {
                $this->user->increment('tokens_used', $tokensUsed);
            }

            $this->newLine();
            $this->line("<comment>AI:</comment> {$reply}");
            $this->newLine();

            if ($tokensUsed > 0 && $this->user) {
                $this->line("<info>Tokens used this request: " . number_format($tokensUsed) . "</info>");
            }

            return false;
        } catch (\Throwable $e) {
            error('Something went wrong: ' . $e->getMessage());
            return false;
        }
    }

    private function buildHistory(string $message): array
    {
        if ($this->user) {
            if (!$this->conversation) {
                $this->conversation = Conversation::create([
                    'user_id' => $this->user->id,
                    'title' => Str::limit(strip_tags($message), 50),
                ]);
            }

            $history = $this->conversation->messages()
                ->orderBy('created_at')
                ->get()
                ->map(fn ($m) => [
                    'role' => $m->role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $m->content]],
                ])
                ->values()
                ->all();

            $history[] = ['role' => 'user', 'parts' => [['text' => $message]]];

            return $history;
        }

        return [
            ['role' => 'user', 'parts' => [['text' => $message]]],
        ];
    }

    private function saveMessages(string $userMessage, string $reply): void
    {
        if (!$this->conversation) {
            return;
        }

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $this->conversation->touch();
    }
}

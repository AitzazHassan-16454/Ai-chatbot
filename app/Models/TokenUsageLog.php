<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'endpoint',
        'model',
        'prompt_tokens',
        'candidates_tokens',
        'thoughts_tokens',
        'total_tokens',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens'     => 'integer',
            'candidates_tokens' => 'integer',
            'thoughts_tokens'   => 'integer',
            'total_tokens'      => 'integer',
            'status'            => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public static function record(array $attributes): self
    {
        return static::create($attributes);
    }

    public static function totalTokens(): int
    {
        return (int) static::sum('total_tokens');
    }
}

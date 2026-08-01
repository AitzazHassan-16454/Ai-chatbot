<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'category',
        'message',
        'rating',
        'page_context',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

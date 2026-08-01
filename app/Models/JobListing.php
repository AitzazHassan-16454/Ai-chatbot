<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobListing extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'company',
        'location',
        'url',
        'source',
        'salary_text',
        'posted_at',
        'found_at',
        'emailed',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'found_at' => 'datetime',
        'emailed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'username', 'email', 'password',
    'avatar_path', 'cv_path', 'cv_name', 'job_keywords', 'job_location',
    'job_type', 'job_remote', 'job_experience',
    'job_salary_min', 'job_salary_max',
    'job_alerts_enabled', 'job_alert_frequency',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tokens_used' => 'integer',
            'tokens_limit' => 'integer',
            'job_alerts_enabled' => 'boolean',
            'job_salary_min' => 'integer',
            'job_salary_max' => 'integer',
        ];
    }

    public function tokensRemaining(): int
    {
        return max(0, $this->tokens_limit - $this->tokens_used);
    }

    public function hasTokensRemaining(): bool
    {
        return $this->tokens_used < $this->tokens_limit;
    }

    public function jobListings()
    {
        return $this->hasMany(JobListing::class);
    }
}

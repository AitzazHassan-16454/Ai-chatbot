<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SystemUsage extends Model
{
    protected $table = 'system_usage';

    protected $fillable = [
        'requests_today',
        'requests_day_limit',
        'daily_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'requests_today'    => 'integer',
            'requests_day_limit' => 'integer',
            'daily_reset_at'    => 'datetime',
        ];
    }

    public static function getUsage(): self
    {
        $usage = static::first();

        if (!$usage) {
            $usage = static::create([
                'requests_today'     => 0,
                'requests_day_limit'  => config('services.gemini.requests_day_limit', 250),
                'daily_reset_at'     => Carbon::now('America/Los_Angeles')->startOfDay()->addDay()->setTimezone('UTC'),
            ]);
        }

        // Reset daily counter at midnight Pacific Time
        $nowPT = Carbon::now('America/Los_Angeles');
        $resetPT = $usage->daily_reset_at ? $usage->daily_reset_at->copy()->setTimezone('America/Los_Angeles') : null;
        if ($resetPT && $nowPT->gte($resetPT)) {
            $nextReset = $nowPT->copy()->startOfDay()->addDay()->setTimezone('UTC');
            $usage->update([
                'requests_today' => 0,
                'daily_reset_at' => $nextReset,
            ]);
        }

        return $usage;
    }

    public static function incrementRequests(): void
    {
        $usage = static::getUsage();
        $usage->increment('requests_today');

        // Track RPM using cache (rolling 60s window)
        $rpmKey = 'gemini:rpm:' . Carbon::now('America/Los_Angeles')->format('Y-m-d-H-i');
        Cache::increment($rpmKey);
        Cache::put($rpmKey, Cache::get($rpmKey, 0), now()->addSeconds(65));
    }

    public static function currentRpm(): int
    {
        return Cache::get('gemini:rpm:' . Carbon::now('America/Los_Angeles')->format('Y-m-d-H-i'), 0);
    }

    public function dailyPercentage(): float
    {
        if ($this->requests_day_limit === 0) return 0;
        return round(($this->requests_today / $this->requests_day_limit) * 100, 1);
    }

    public function rpmPercentage(): float
    {
        $rpmLimit = config('services.gemini.rpm_limit', 15);
        if ($rpmLimit === 0) return 0;
        return round((static::currentRpm() / $rpmLimit) * 100, 1);
    }

    public function effectivePercentage(): float
    {
        return max($this->dailyPercentage(), $this->rpmPercentage());
    }

    public function isExhausted(): bool
    {
        $rpmLimit = config('services.gemini.rpm_limit', 15);
        return $this->requests_today >= $this->requests_day_limit
            || static::currentRpm() >= $rpmLimit;
    }

    public function statusLevel(): string
    {
        $pct = $this->effectivePercentage();

        if ($pct >= 100) return 'exhausted';
        if ($pct >= 90)  return 'critical';
        if ($pct >= 75)  return 'warning';
        return 'healthy';
    }

    public function resetsAt(): ?Carbon
    {
        if ($this->requests_today >= $this->requests_day_limit) {
            return $this->daily_reset_at ? $this->daily_reset_at->copy()->setTimezone('America/Los_Angeles') : null;
        }
        return null;
    }

    public function apiStatusArray(): array
    {
        return [
            'status'      => $this->statusLevel(),
            'percentage'  => $this->effectivePercentage(),
            'resets_at'   => $this->resetsAt()?->toIso8601String(),
            'requests_today'    => $this->requests_today,
            'requests_day_limit' => $this->requests_day_limit,
            'rpm'         => static::currentRpm(),
            'rpm_limit'   => config('services.gemini.rpm_limit', 15),
        ];
    }
}

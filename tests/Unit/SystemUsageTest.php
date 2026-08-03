<?php

namespace Tests\Unit;

use App\Models\SystemUsage;
use App\Models\TokenUsageLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_tokens_sums_all_users_and_endpoints(): void
    {
        TokenUsageLog::create([
            'endpoint' => 'generateContent',
            'prompt_tokens' => 10,
            'candidates_tokens' => 20,
            'total_tokens' => 30,
        ]);
        TokenUsageLog::create([
            'endpoint' => 'streamGenerateContent',
            'prompt_tokens' => 5,
            'candidates_tokens' => 15,
            'total_tokens' => 20,
        ]);
        TokenUsageLog::create([
            'endpoint' => 'cv_parse',
            'total_tokens' => 50,
        ]);

        $this->assertSame(100, TokenUsageLog::totalTokens());
    }

    public function test_get_usage_creates_record_if_none_exists(): void
    {
        SystemUsage::truncate();

        $usage = SystemUsage::getUsage();

        $this->assertNotNull($usage);
        $this->assertEquals(0, $usage->requests_today);
        $this->assertGreaterThan(0, $usage->requests_day_limit);
    }

    public function test_get_usage_returns_existing_record(): void
    {
        $existing = SystemUsage::first();

        $usage = SystemUsage::getUsage();

        $this->assertEquals($existing->id, $usage->id);
    }

    public function test_increment_requests_increments_counter(): void
    {
        $usage = SystemUsage::first();
        $initial = $usage->requests_today;

        SystemUsage::incrementRequests();

        $usage->refresh();
        $this->assertEquals($initial + 1, $usage->requests_today);
    }

    public function test_is_exhausted_when_at_limit(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => $usage->requests_day_limit]);

        $this->assertTrue($usage->isExhausted());
    }

    public function test_is_not_exhausted_below_limit(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => 0]);

        $this->assertFalse($usage->isExhausted());
    }

    public function test_status_level_healthy_below_75_percent(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => 0, 'requests_day_limit' => 250]);

        $this->assertEquals('healthy', $usage->statusLevel());
    }

    public function test_status_level_warning_at_75_percent(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => 188, 'requests_day_limit' => 250]);

        $this->assertEquals('warning', $usage->statusLevel());
    }

    public function test_status_level_critical_at_90_percent(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => 225, 'requests_day_limit' => 250]);

        $this->assertEquals('critical', $usage->statusLevel());
    }

    public function test_status_level_exhausted_at_100_percent(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => 250, 'requests_day_limit' => 250]);

        $this->assertEquals('exhausted', $usage->statusLevel());
    }

    public function test_daily_percentage_calculation(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => 50, 'requests_day_limit' => 200]);

        $this->assertEquals(25.0, $usage->dailyPercentage());
    }

    public function test_api_status_array_contains_required_keys(): void
    {
        $usage = SystemUsage::first();

        $status = $usage->apiStatusArray();

        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('percentage', $status);
        $this->assertArrayHasKey('resets_at', $status);
        $this->assertArrayHasKey('requests_today', $status);
        $this->assertArrayHasKey('requests_day_limit', $status);
        $this->assertArrayHasKey('rpm', $status);
        $this->assertArrayHasKey('rpm_limit', $status);
    }

    public function test_resets_at_returns_null_when_not_exhausted(): void
    {
        $usage = SystemUsage::first();
        $usage->update(['requests_today' => 10]);

        $this->assertNull($usage->resetsAt());
    }

    public function test_resets_at_returns_datetime_when_exhausted(): void
    {
        $usage = SystemUsage::first();
        $usage->update([
            'requests_today' => $usage->requests_day_limit,
            'daily_reset_at' => Carbon::tomorrow(),
        ]);

        $this->assertNotNull($usage->resetsAt());
    }
}

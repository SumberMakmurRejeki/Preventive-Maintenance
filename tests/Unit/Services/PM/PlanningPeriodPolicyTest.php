<?php

namespace Tests\Unit\Services\PM;

use App\Services\PM\PlanningPeriodPolicy;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PlanningPeriodPolicyTest extends TestCase
{
    private PlanningPeriodPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PlanningPeriodPolicy;
    }

    public function test_initial_months_defaults_to_twelve(): void
    {
        $this->assertSame(12, $this->policy->initialMonths());
    }

    public function test_planning_window_uses_operational_from_when_ahead_of_business_today(): void
    {
        $businessToday = Carbon::parse('2026-05-21', 'Asia/Jakarta')->startOfDay();
        $operationalFrom = Carbon::parse('2026-06-01', 'Asia/Jakarta')->startOfDay();

        $window = $this->policy->planningWindow($businessToday, $operationalFrom);

        $this->assertSame('2026-06-01', $window['planning_start']->toDateString());
        $this->assertSame('2027-06-01', $window['planning_end']->toDateString());
    }

    public function test_planning_window_clamps_to_business_today_when_operational_from_is_past(): void
    {
        $businessToday = Carbon::parse('2026-05-21', 'Asia/Jakarta')->startOfDay();
        $operationalFrom = Carbon::parse('2026-04-01', 'Asia/Jakarta')->startOfDay();

        $window = $this->policy->planningWindow($businessToday, $operationalFrom);

        $this->assertSame('2026-05-21', $window['planning_start']->toDateString());
        $this->assertSame('2027-05-21', $window['planning_end']->toDateString());
    }

    public function test_planning_window_uses_no_overflow_across_leap_year(): void
    {
        $businessToday = Carbon::parse('2024-02-29', 'Asia/Jakarta')->startOfDay();
        $operationalFrom = Carbon::parse('2024-02-29', 'Asia/Jakarta')->startOfDay();

        $window = $this->policy->planningWindow($businessToday, $operationalFrom);

        // 29 Feb (tahun kabisat) + 12 bulan, no-overflow -> 28 Feb tahun berikutnya.
        // Dengan overflow biasa nilainya akan melompat ke 1 Mar.
        $this->assertSame('2025-02-28', $window['planning_end']->toDateString());
    }

    public function test_materialization_start_clamps_past_operational_from_to_business_today(): void
    {
        $businessToday = Carbon::parse('2026-09-10', 'Asia/Jakarta')->startOfDay();
        $operationalFrom = Carbon::parse('2026-09-01', 'Asia/Jakarta')->startOfDay();

        $this->assertSame('2026-09-10', $this->policy->materializationStart($operationalFrom, $businessToday)?->toDateString());
    }

    public function test_materialization_start_preserves_today_and_future_boundaries(): void
    {
        $businessToday = Carbon::parse('2026-09-10', 'Asia/Jakarta')->startOfDay();

        $this->assertSame('2026-09-10', $this->policy->materializationStart($businessToday, $businessToday)?->toDateString());
        $this->assertSame('2026-09-15', $this->policy->materializationStart(
            Carbon::parse('2026-09-15', 'Asia/Jakarta')->startOfDay(),
            $businessToday,
        )?->toDateString());
    }

    public function test_materialization_start_is_unresolved_for_legacy_null(): void
    {
        $this->assertNull($this->policy->materializationStart(null));
    }
}

<?php

namespace Tests\Unit\Services\PM;

use App\Services\PM\BusinessDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BusinessDateTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_returns_start_of_day_in_asia_jakarta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 23:59:59', 'Asia/Jakarta'));

        $today = BusinessDate::today();

        $this->assertSame('2026-05-21', $today->toDateString());
        $this->assertSame('Asia/Jakarta', $today->getTimezone()->getName());
        $this->assertSame('00:00:00', $today->format('H:i:s'));
    }

    public function test_today_is_deterministic_under_set_test_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00', 'Asia/Jakarta'));

        $this->assertSame('2026-08-20', BusinessDate::today()->toDateString());
        $this->assertSame('2026-08-20', BusinessDate::today()->toDateString());
    }
}

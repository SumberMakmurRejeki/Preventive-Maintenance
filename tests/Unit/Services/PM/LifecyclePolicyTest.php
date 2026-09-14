<?php

namespace Tests\Unit\Services\PM;

use App\Services\PM\LifecyclePolicy;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LifecyclePolicyTest extends TestCase
{
    private LifecyclePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new LifecyclePolicy;
    }

    public function test_legacy_machine_projection_maps_boolean_to_non_terminal_status(): void
    {
        // Proyeksi ini menjaga penulis legacy tetap sinkron tanpa menebak status retired.
        $this->assertSame(['is_active' => true, 'lifecycle_status' => 'active'], $this->policy->machineProjection(true));
        $this->assertSame(['is_active' => false, 'lifecycle_status' => 'inactive'], $this->policy->machineProjection(false));
    }

    public function test_legacy_schedule_projection_maps_boolean_to_non_terminal_status(): void
    {
        // Jadwal nonaktif legacy berarti paused, bukan terminal ended.
        $this->assertSame(['is_active' => true, 'lifecycle_status' => 'active'], $this->policy->scheduleProjection(true));
        $this->assertSame(['is_active' => false, 'lifecycle_status' => 'paused'], $this->policy->scheduleProjection(false));
    }

    public function test_transition_pair_matrix_matches_adr010_exactly(): void
    {
        // Ekspektasi diturunkan dari ADR-010, bukan dari implementasi.
        $expected = [
            'machine' => [
                'active' => ['inactive', 'retired'],
                'inactive' => ['active', 'retired'],
                'retired' => [],
            ],
            'schedule' => [
                'active' => ['paused', 'ended'],
                'paused' => ['active', 'ended'],
                'ended' => [],
            ],
        ];

        // Seluruh pasangan state diuji: self-transition dan terminal tidak boleh legal.
        foreach (['machine', 'schedule'] as $entity) {
            foreach (array_keys($expected[$entity]) as $from) {
                $legalTargets = [];

                foreach (array_keys($expected[$entity]) as $to) {
                    if ($this->policy->canTransition($entity, $from, $to)) {
                        $legalTargets[] = $to;
                    }
                }

                $this->assertSame(
                    $expected[$entity][$from],
                    $legalTargets,
                    'Transisi legal '.$entity.' dari '.$from.' tidak sesuai ADR-010.',
                );
            }
        }

        // Recommission dan resume setelah end adalah workflow eksplisit, bukan transisi ordinary.
        $this->assertFalse($this->policy->canTransition('machine', 'retired', 'active'));
        $this->assertFalse($this->policy->canTransition('machine', 'retired', 'inactive'));
        $this->assertFalse($this->policy->canTransition('schedule', 'ended', 'active'));
        $this->assertFalse($this->policy->canTransition('schedule', 'ended', 'paused'));

        // Entitas atau state yang tidak dikenal harus fail closed.
        $this->assertFalse($this->policy->canTransition('location', 'active', 'inactive'));
        $this->assertFalse($this->policy->canTransition('machine', 'unknown', 'active'));
    }

    public function test_materialization_floor_boundary_permutations(): void
    {
        $businessToday = Carbon::parse('2026-09-07');

        // 1. operational_from NULL -> fail closed, tidak ada materialisasi.
        $this->assertNull($this->policy->materializationFloor(null, null, null, null, $businessToday));

        // 2. start_date lebih baru dari operational_from -> start_date menang.
        $this->assertSame('2026-09-09', $this->policy->materializationFloor(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-09'),
            null,
            null,
            $businessToday,
        )?->toDateString());

        // 3. boundary machine lebih baru dari boundary schedule -> machine menang.
        $this->assertSame('2026-09-12', $this->policy->materializationFloor(
            Carbon::parse('2026-09-01'),
            null,
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-08'),
            $businessToday,
        )?->toDateString());

        // 4. boundary schedule lebih baru dari boundary machine -> schedule menang.
        $this->assertSame('2026-09-12', $this->policy->materializationFloor(
            Carbon::parse('2026-09-01'),
            null,
            Carbon::parse('2026-09-08'),
            Carbon::parse('2026-09-12'),
            $businessToday,
        )?->toDateString());

        // 5. seluruh lifecycle boundary NULL -> fondasi ADR-009 yang berlaku.
        $this->assertSame('2026-09-07', $this->policy->materializationFloor(
            Carbon::parse('2026-09-01'),
            null,
            null,
            null,
            $businessToday,
        )?->toDateString());

        // 6. semua boundary sama -> floor tetap satu nilai.
        $this->assertSame('2026-09-12', $this->policy->materializationFloor(
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-12'),
        )?->toDateString());

        // Boundary di bawah hari bisnis tidak menurunkan floor materialisasi.
        $this->assertSame('2026-09-07', $this->policy->materializationFloor(
            Carbon::parse('2026-09-01'),
            null,
            Carbon::parse('2026-09-02'),
            Carbon::parse('2026-09-03'),
            $businessToday,
        )?->toDateString());
    }

    public function test_persisted_eligibility_floor_boundary_permutations(): void
    {
        // operational_from NULL -> tanpa floor persisted, fail closed.
        $this->assertNull($this->policy->persistedLiveEligibilityFloor(null, null, null, null));

        // start_date lebih baru dari operational_from -> start_date menang.
        $this->assertSame('2026-09-09', $this->policy->persistedLiveEligibilityFloor(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-09'),
            null,
            null,
        )?->toDateString());

        // Boundary machine lebih baru -> machine menang.
        $this->assertSame('2026-09-12', $this->policy->persistedLiveEligibilityFloor(
            Carbon::parse('2026-09-01'),
            null,
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-08'),
        )?->toDateString());

        // Boundary schedule lebih baru -> schedule menang.
        $this->assertSame('2026-09-12', $this->policy->persistedLiveEligibilityFloor(
            Carbon::parse('2026-09-01'),
            null,
            Carbon::parse('2026-09-08'),
            Carbon::parse('2026-09-12'),
        )?->toDateString());

        // Semua boundary NULL -> operational_from adalah lower bound persisted.
        $this->assertSame('2026-09-01', $this->policy->persistedLiveEligibilityFloor(
            Carbon::parse('2026-09-01'),
            null,
            null,
            null,
        )?->toDateString());

        // Semua boundary sama -> floor tunggal.
        $this->assertSame('2026-09-12', $this->policy->persistedLiveEligibilityFloor(
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-12'),
        )?->toDateString());
    }

    public function test_live_occurrence_matches_persisted_eligibility_floor_exactly(): void
    {
        $operationalFrom = Carbon::parse('2026-09-10');

        // Occurrence tepat pada floor -> live bila predikat lain terpenuhi.
        $this->assertTrue($this->policy->isLiveOccurrence(
            'active',
            'active',
            $operationalFrom,
            null,
            null,
            null,
            Carbon::parse('2026-09-10'),
            'scheduled',
            false,
        ));

        // Occurrence sebelum floor -> non-live (suppressed, bukan dihapus).
        $this->assertFalse($this->policy->isLiveOccurrence(
            'active',
            'active',
            $operationalFrom,
            null,
            null,
            null,
            Carbon::parse('2026-09-09'),
            'scheduled',
            false,
        ));

        // Occurrence sesudah floor -> live.
        $this->assertTrue($this->policy->isLiveOccurrence(
            'active',
            'active',
            $operationalFrom,
            null,
            null,
            null,
            Carbon::parse('2026-09-11'),
            'scheduled',
            false,
        ));
    }

    public function test_materialization_floor_adds_lifecycle_boundaries_to_adr009_generation_floor(): void
    {
        // Floor pembuatan occurrence memakai hari bisnis hanya melalui ADR-009.
        $floor = $this->policy->materializationFloor(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-05'),
            Carbon::parse('2026-09-10'),
            Carbon::parse('2026-09-08'),
            Carbon::parse('2026-09-07'),
        );

        $this->assertSame('2026-09-10', $floor?->toDateString());
        $this->assertNull($this->policy->materializationFloor(null, null, null, null, Carbon::parse('2026-09-07')));
    }

    public function test_persisted_eligibility_floor_does_not_move_with_business_today(): void
    {
        // Eligibility occurrence tersimpan tidak boleh bergeser hanya karena tanggal hari ini berubah.
        $floor = $this->policy->persistedLiveEligibilityFloor(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-05'),
            Carbon::parse('2026-09-10'),
            Carbon::parse('2026-09-08'),
        );

        $this->assertSame('2026-09-10', $floor?->toDateString());
    }

    public function test_occurrence_eligibility_floor_is_persisted_not_moving_with_business_today(): void
    {
        // Resume 10 Sep: occurrence mutable 5 Sep berada di bawah floor -> non-live.
        $belowFloor = $this->policy->isLiveOccurrence(
            'active',
            'paused',
            Carbon::parse('2026-09-10'),
            null,
            null,
            null,
            Carbon::parse('2026-09-05'),
            'scheduled',
            false,
        );
        $this->assertFalse($belowFloor);

        // Occurrence 11 Sep berada di atas floor -> live meski schedule paused menunggu resume.
        $aboveFloor = $this->policy->isLiveOccurrence(
            'active',
            'active',
            Carbon::parse('2026-09-10'),
            null,
            null,
            null,
            Carbon::parse('2026-09-11'),
            'scheduled',
            false,
        );
        $this->assertTrue($aboveFloor);

        // Tanggal hari ini bergerak maju tidak boleh menekan occurrence yang sudah di atas floor.
        $this->assertTrue($this->policy->isLiveOccurrence(
            'active',
            'active',
            Carbon::parse('2026-09-10'),
            null,
            null,
            null,
            Carbon::parse('2026-09-11'),
            'scheduled',
            false,
        ));

        // operational_from NULL tetap fail closed.
        $this->assertFalse($this->policy->isLiveOccurrence(
            'active',
            'active',
            null,
            null,
            null,
            null,
            Carbon::parse('2026-09-11'),
            'scheduled',
            false,
        ));

        // Terminal/inactive parent dan occurrence non-mutable tidak pernah live.
        $this->assertFalse($this->policy->isLiveOccurrence(
            'retired',
            'active',
            Carbon::parse('2026-09-10'),
            null,
            null,
            null,
            Carbon::parse('2026-09-11'),
            'scheduled',
            false,
        ));
        $this->assertFalse($this->policy->isLiveOccurrence(
            'active',
            'active',
            Carbon::parse('2026-09-10'),
            null,
            null,
            null,
            Carbon::parse('2026-09-11'),
            'in_progress',
            true,
        ));
    }
}

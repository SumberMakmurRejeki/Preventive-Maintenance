<?php

namespace App\Services\PM;

use Carbon\Carbon;

/**
 * Kebijakan lifecycle murni untuk fondasi Slice A.
 *
 * Kelas ini hanya menghitung proyeksi, transisi, dan batas tanggal; tidak
 * menyimpan model, membuka transaksi, atau menulis database.
 */
class LifecyclePolicy
{
    /**
     * Memetakan boolean Machine legacy ke status non-terminal yang setara.
     *
     * @return array{is_active: bool, lifecycle_status: string}
     */
    public function machineProjection(bool $isActive): array
    {
        return [
            'is_active' => $isActive,
            'lifecycle_status' => $isActive ? 'active' : 'inactive',
        ];
    }

    /**
     * Memetakan boolean Schedule legacy ke status non-terminal yang setara.
     *
     * @return array{is_active: bool, lifecycle_status: string}
     */
    public function scheduleProjection(bool $isActive): array
    {
        return [
            'is_active' => $isActive,
            'lifecycle_status' => $isActive ? 'active' : 'paused',
        ];
    }

    /**
     * Menguji matriks transisi ordinary; terminal state tidak dapat dipulihkan.
     */
    public function canTransition(string $entity, string $from, string $to): bool
    {
        $transitions = [
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

        return in_array($to, $transitions[$entity][$from] ?? [], true);
    }

    /**
     * Menentukan apakah parent berada pada status live (active) untuk Slice A.
     */
    public function isLive(string $entity, string $status): bool
    {
        // Saat ini hanya 'active' yang live; paused/inactive/retired/ended tidak.
        return $status === 'active';
    }

    /**
     * Menghitung floor materialisasi baru: ADR-009 generation floor lalu
     * effective_live_from parent sebagai lower bound tambahan.
     */
    public function materializationFloor(
        ?Carbon $operationalFrom,
        ?Carbon $startDate,
        ?Carbon $machineEffectiveLiveFrom,
        ?Carbon $scheduleEffectiveLiveFrom,
        Carbon $businessToday,
    ): ?Carbon {
        if ($operationalFrom === null) {
            return null;
        }

        $planning = new PlanningPeriodPolicy;
        $materializationStart = $planning->materializationStart($operationalFrom, $businessToday);
        $generationStart = $planning->generationStart($startDate, $materializationStart);

        if ($generationStart === null) {
            return null;
        }

        $floor = $generationStart->copy()->startOfDay();
        foreach ([$machineEffectiveLiveFrom, $scheduleEffectiveLiveFrom] as $boundary) {
            if ($boundary !== null && $boundary->copy()->startOfDay()->greaterThan($floor)) {
                $floor = $boundary->copy()->startOfDay();
            }
        }

        return $floor;
    }

    /**
     * Menghitung floor eligibility occurrence tersimpan tanpa BusinessDate.
     */
    public function persistedLiveEligibilityFloor(
        ?Carbon $operationalFrom,
        ?Carbon $startDate,
        ?Carbon $machineEffectiveLiveFrom,
        ?Carbon $scheduleEffectiveLiveFrom,
    ): ?Carbon {
        if ($operationalFrom === null) {
            return null;
        }

        $floor = $operationalFrom->copy()->startOfDay();
        foreach ([$startDate, $machineEffectiveLiveFrom, $scheduleEffectiveLiveFrom] as $boundary) {
            if ($boundary !== null && $boundary->copy()->startOfDay()->greaterThan($floor)) {
                $floor = $boundary->copy()->startOfDay();
            }
        }

        return $floor;
    }

    /**
     * Menentukan apakah occurrence mutable dan belum memiliki execution canonical.
     */
    public function isMutableOccurrence(string $status, bool $hasCanonicalExecution): bool
    {
        return in_array($status, ['scheduled', 'overdue', 'missed'], true)
            && ! $hasCanonicalExecution;
    }

    /**
     * Menentukan eligibility occurrence baru tanpa mengganggu flow review canonical.
     */
    public function isLiveOccurrence(
        string $machineStatus,
        string $scheduleStatus,
        ?Carbon $operationalFrom,
        ?Carbon $startDate,
        ?Carbon $machineEffectiveLiveFrom,
        ?Carbon $scheduleEffectiveLiveFrom,
        Carbon $scheduledDate,
        string $occurrenceStatus,
        bool $hasCanonicalExecution,
    ): bool {
        if (! $this->isLive('machine', $machineStatus) || ! $this->isLive('schedule', $scheduleStatus)) {
            return false;
        }

        if (! $this->isMutableOccurrence($occurrenceStatus, $hasCanonicalExecution)) {
            return false;
        }

        $floor = $this->persistedLiveEligibilityFloor(
            $operationalFrom,
            $startDate,
            $machineEffectiveLiveFrom,
            $scheduleEffectiveLiveFrom,
        );

        return $floor !== null && $scheduledDate->copy()->startOfDay()->greaterThanOrEqualTo($floor);
    }
}

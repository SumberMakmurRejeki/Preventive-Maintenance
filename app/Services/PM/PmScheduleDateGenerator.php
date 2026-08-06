<?php

namespace App\Services\PM;

use Carbon\Carbon;

class PmScheduleDateGenerator
{
    /**
     * @param  array<string, mixed>  $schedule
     * @return array<int, string>
     */
    public static function generate(array $schedule): array
    {
        $start = Carbon::parse($schedule['start_date'])->startOfDay();
        $end = Carbon::parse($schedule['generate_until'])->startOfDay();

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $shouldInclude = false;

            if ($schedule['frequency_type'] === 'daily') {
                $shouldInclude = true;
            }

            if ($schedule['frequency_type'] === 'weekly') {
                $selectedDays = array_map('intval', $schedule['weekly_days'] ?? []);
                $shouldInclude = in_array((int) $cursor->dayOfWeek, $selectedDays, true);
            }

            if ($schedule['frequency_type'] === 'monthly') {
                $targetDay = max(1, min(31, (int) ($schedule['monthly_day'] ?? 1)));
                $validDay = min($targetDay, $cursor->copy()->endOfMonth()->day);
                $shouldInclude = $cursor->day === $validDay;
            }

            if ($shouldInclude) {
                $dates[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return array_values(array_unique($dates));
    }
}

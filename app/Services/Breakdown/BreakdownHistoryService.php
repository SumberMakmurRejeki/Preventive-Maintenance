<?php

namespace App\Services\Breakdown;

use App\Models\Breakdown;
use App\Models\BreakdownHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class BreakdownHistoryService
{
    public function record(Breakdown $breakdown, ?User $changer, string $fieldName, ?string $oldValue, ?string $newValue, ?string $changeNote = null): void
    {
        BreakdownHistory::query()->create([
            'breakdown_id' => $breakdown->id,
            'changed_by' => $changer?->id,
            'changed_at' => now(),
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'change_note' => $changeNote,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{field_name:string,old_value:?string,new_value:?string,change_note:?string}>  $changes
     */
    public function recordMany(Breakdown $breakdown, ?User $changer, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $now = now();
        $rows = Collection::make($changes)
            ->map(fn (array $change): array => [
                'breakdown_id' => $breakdown->id,
                'changed_by' => $changer?->id,
                'changed_at' => $now,
                'field_name' => $change['field_name'],
                'old_value' => $change['old_value'],
                'new_value' => $change['new_value'],
                'change_note' => $change['change_note'],
                'created_at' => $now,
            ])
            ->all();

        BreakdownHistory::query()->insert($rows);
    }
}


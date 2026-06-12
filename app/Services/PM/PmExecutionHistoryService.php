<?php

namespace App\Services\PM;

use App\Models\PmExecution;
use App\Models\PmExecutionHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class PmExecutionHistoryService
{
    public function record(PmExecution $execution, ?User $admin, string $fieldName, ?string $oldValue, ?string $newValue, string $changeNote): void
    {
        PmExecutionHistory::query()->create([
            'pm_execution_id' => $execution->id,
            'changed_by' => $admin?->id,
            'changed_at' => now(),
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'change_note' => $changeNote,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{field_name:string,old_value:?string,new_value:?string,change_note:string}>  $changes
     */
    public function recordMany(PmExecution $execution, ?User $admin, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $now = now();
        $rows = Collection::make($changes)
            ->map(fn (array $change): array => [
                'pm_execution_id' => $execution->id,
                'changed_by' => $admin?->id,
                'changed_at' => $now,
                'field_name' => $change['field_name'],
                'old_value' => $change['old_value'],
                'new_value' => $change['new_value'],
                'change_note' => $change['change_note'],
                'created_at' => $now,
            ])
            ->all();

        PmExecutionHistory::query()->insert($rows);
    }
}

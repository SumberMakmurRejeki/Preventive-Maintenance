<?php

namespace App\Services\PM;

use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Services\Auth\ActivityLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Membuka era Schedule baru secara eksplisit untuk satu assignment dormant.
 *
 * Recommission tidak pernah menghidupkan kembali era ENDED atau assignment sibling.
 */
class MachineRecommissionService
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected PmScheduleDateReconciler $reconciler,
    ) {}

    /**
     * @param  array<string, mixed>  $scheduleConfiguration
     *
     * @throws InvalidMachineTransitionException
     */
    public function recommission(PmChecksheetMachine $assignment, array $scheduleConfiguration, string $reason): Machine
    {
        // Alasan wajib berasal dari pemanggil sebelum transaksi membuka lock.
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidMachineTransitionException('Alasan recommission wajib diisi.');
        }

        return DB::transaction(function () use ($assignment, $scheduleConfiguration, $reason): Machine {
            // Urutan lock mengikuti writer checksheet: PmChecksheet -> Machine -> Schedule.
            /** @var PmChecksheet $checksheet */
            $checksheet = PmChecksheet::query()->lockForUpdate()->findOrFail($assignment->pm_checksheet_id);
            /** @var PmChecksheetMachine $lockedAssignment */
            $lockedAssignment = PmChecksheetMachine::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $lockedAssignment->pm_checksheet_id !== (int) $checksheet->id) {
                throw new InvalidMachineTransitionException('Provenance assignment recommission tidak valid.');
            }

            /** @var Machine $machine */
            $machine = Machine::query()->lockForUpdate()->findOrFail($lockedAssignment->machine_id);
            if ($machine->lifecycle_status === 'inactive') {
                throw new InvalidMachineTransitionException('Machine inactive tidak dapat direcommission.');
            }
            if (! in_array($machine->lifecycle_status, ['active', 'retired'], true)) {
                throw new InvalidMachineTransitionException('Status lifecycle Machine tidak dikenal.');
            }

            $currentSchedules = PmSchedule::query()
                ->where('pm_checksheet_machine_id', $lockedAssignment->id)
                ->currentEra()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($currentSchedules->count() > 1) {
                throw new InvalidMachineTransitionException('Integrity current era Schedule ganda terdeteksi.');
            }
            if ($currentSchedules->isNotEmpty()) {
                throw new InvalidMachineTransitionException('Assignment sudah memiliki current Schedule era.');
            }

            $attributes = Arr::only($scheduleConfiguration, [
                'frequency_type',
                'weekly_days',
                'monthly_day',
                'operational_from',
                'start_date',
                'generate_until',
            ]);
            foreach (['frequency_type', 'operational_from', 'start_date', 'generate_until'] as $required) {
                if (empty($attributes[$required])) {
                    throw new InvalidMachineTransitionException('Konfigurasi schedule recommission belum lengkap.');
                }
            }

            // Snapshot state authoritative SEBELUM mutasi apa pun. Recommission
            // juga sah untuk Machine yang sudah active (assignment dormant lain
            // pada checksheet yang sama), sehingga audit tidak boleh selalu
            // mengklaim transisi retired -> active.
            $machineStateBefore = $machine->only(['lifecycle_status', 'is_active', 'effective_live_from']);

            if ($machine->lifecycle_status === 'retired') {
                // Recommission Machine retired hanya terjadi pada jalur exceptional ini.
                $machine->forceFill([
                    'lifecycle_status' => 'active',
                    'is_active' => true,
                    'effective_live_from' => BusinessDate::today(),
                ])->save();
            }

            // Era baru aktif memakai boundary hari ini dan tidak menyalin konfigurasi ENDED.
            $schedule = PmSchedule::query()->create([
                ...$attributes,
                'pm_checksheet_machine_id' => $lockedAssignment->id,
                'lifecycle_status' => 'active',
                'is_active' => true,
                'effective_live_from' => BusinessDate::today(),
            ]);

            $this->reconciler->reconcile($schedule);
            $this->activityLog->log(
                request: request(),
                moduleName: 'master_mesin',
                action: 'recommission',
                description: $reason,
                tableName: 'machines',
                recordId: $machine->id,
                oldValues: $machineStateBefore,
                newValues: [
                    ...$machine->only(['lifecycle_status', 'is_active', 'effective_live_from']),
                    'pm_schedule_id' => $schedule->id,
                ],
            );

            return $machine;
        });
    }
}

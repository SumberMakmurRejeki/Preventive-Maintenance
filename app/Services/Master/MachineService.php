<?php

namespace App\Services\Master;

use App\Models\Machine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Services\Auth\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MachineService
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected QrCodeService $qrCodeService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Request $request, array $payload): Machine
    {
        return DB::transaction(function () use ($request, $payload): Machine {
            $machine = Machine::query()->create([
                ...$this->normalizePayload($payload),
                'qr_token' => $this->qrCodeService->createUniqueToken(),
            ]);

            $machine->forceFill([
                'qr_code_path' => $this->qrCodeService->generateForMachine($machine),
            ])->save();

            $this->activityLog->log(
                request: $request,
                moduleName: 'master_mesin',
                action: 'create',
                description: sprintf('Create machine %s', $machine->machine_code),
                tableName: 'machines',
                recordId: $machine->id,
                newValues: $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']),
            );

            return $machine;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Request $request, Machine $machine, array $payload): Machine
    {
        $oldValues = $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']);

        $machine->fill($this->normalizePayload($payload));
        $machine->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_mesin',
            action: 'update',
            description: sprintf('Update machine %s', $machine->machine_code),
            tableName: 'machines',
            recordId: $machine->id,
            oldValues: $oldValues,
            newValues: $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']),
        );

        return $machine;
    }

    public function deactivate(Request $request, Machine $machine): void
    {
        $oldValues = $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']);

        $machine->forceFill([
            'is_active' => false,
        ])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_mesin',
            action: 'deactivate',
            description: sprintf('Deactivate machine %s', $machine->machine_code),
            tableName: 'machines',
            recordId: $machine->id,
            oldValues: $oldValues,
            newValues: $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']),
        );
    }

    public function activate(Request $request, Machine $machine): void
    {
        $oldValues = $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']);

        $machine->forceFill([
            'is_active' => true,
        ])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_mesin',
            action: 'activate',
            description: sprintf('Activate machine %s', $machine->machine_code),
            tableName: 'machines',
            recordId: $machine->id,
            oldValues: $oldValues,
            newValues: $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']),
        );
    }

    public function regenerateQr(Request $request, Machine $machine): Machine
    {
        if (! $machine->qr_token) {
            $machine->forceFill([
                'qr_token' => $this->qrCodeService->createUniqueToken(),
            ])->save();
        }

        $oldValues = $machine->only(['qr_token', 'qr_code_path']);

        $machine->forceFill([
            'qr_code_path' => $this->qrCodeService->generateForMachine($machine),
        ])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_mesin',
            action: 'generate_qr',
            description: sprintf('Generate QR for machine %s', $machine->machine_code),
            tableName: 'machines',
            recordId: $machine->id,
            oldValues: $oldValues,
            newValues: $machine->only(['qr_token', 'qr_code_path']),
        );

        return $machine;
    }

    /**
     * TASK-003 Slice 3: Proteksi Hapus Historis Machine
     *
     * Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.
     *
     * Predikat yang dilindungi:
     * - Breakdown ada (termasuk soft-deleted)
     * - PM Execution ada (termasuk soft-deleted)
     * - Occurrence jadwal yang dilindungi (in_progress, waiting_review, approved)
     * - Occurrence dengan execution/riwayat/descendant yang dilindungi
     *
     * @return bool true jika mesin dilindungi (tidak boleh dihapus)
     */
    public function isProtected(Machine $machine): bool
    {
        // Periksa Breakdown (termasuk soft-deleted)
        if ($machine->breakdowns()->withTrashed()->exists()) {
            return true;
        }

        // Periksa PM Execution (termasuk soft-deleted)
        if ($machine->pmExecutions()->withTrashed()->exists()) {
            return true;
        }

        // Periksa occurrence jadwal yang dilindungi (termasuk soft-deleted)
        $protectedStatuses = ['in_progress', 'waiting_review', 'approved'];
        if ($machine->scheduleDates()
            ->withTrashed()
            ->whereIn('status', $protectedStatuses)
            ->exists()) {
            return true;
        }

        // Periksa occurrence yang memiliki execution/riwayat/descendant yang dilindungi
        // Jika scheduleDate memiliki execution (bahkan soft-deleted), maka dilindungi
        if ($machine->scheduleDates()
            ->withTrashed()
            ->whereHas('executions', function ($query) {
                $query->withTrashed();
            })
            ->exists()) {
            return true;
        }

        return false;
    }

    /**
     * TASK-003 Slice 3: Hapus machine dengan proteksi historis.
     *
     * Urutan lock: Machine → Schedule → ScheduleDate (ID ascending) untuk mencegah deadlock.
     * Pemeriksaan protected dilakukan DI DALAM transaction setelah memperoleh lock.
     * QR cleanup dilakukan SETELAH transaction berhasil commit.
     *
     * @return bool true jika berhasil dihapus, false jika dilindungi
     */
    public function delete(Request $request, Machine $machine): bool
    {
        // Path QR diisi dari row authoritative yang sudah dikunci dalam transaction.
        $qrPath = null;

        // Jalankan transaction: lock → periksa protected → delete → log
        $result = DB::transaction(function () use ($request, $machine, &$qrPath): bool {
            // Kunci baris mesin terlebih dahulu (FOR UPDATE).
            $lockedMachine = Machine::query()
                ->where('id', $machine->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedMachine) {
                return false;
            }
            // Simpan path dari row terkunci untuk cleanup setelah commit.
            $qrPath = $lockedMachine->qr_code_path;

            // Kunci jadwal terkait dalam urutan ID ascending (melalui assignment checksheet)
            $scheduleIds = PmSchedule::query()
                ->whereHas('checksheetMachine', function ($query) use ($lockedMachine) {
                    $query->where('machine_id', $lockedMachine->id);
                })
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->pluck('id')
                ->toArray();

            // Kunci tanggal jadwal terkait dalam urutan ID ascending (termasuk soft-deleted)
            PmScheduleDate::query()
                ->where('machine_id', $lockedMachine->id)
                ->orderBy('id', 'asc')
                ->withTrashed()
                ->lockForUpdate()
                ->get();

            // Periksa protected predicate SETELAH memperoleh lock
            if ($this->isProtected($lockedMachine)) {
                return false;
            }

            // Ambil data untuk activity log sebelum delete
            $oldValues = $lockedMachine->only([
                'location_id',
                'machine_code',
                'machine_name',
                'qr_token',
                'qr_code_path',
                'description',
                'is_active',
            ]);
            $machineId = $lockedMachine->id;
            $machineCode = $lockedMachine->machine_code;

            // Hapus machine di dalam transaction
            $lockedMachine->forceDelete();

            // Catat activity log di dalam transaction
            $this->activityLog->log(
                request: $request,
                moduleName: 'master_mesin',
                action: 'delete',
                description: sprintf('Delete machine %s', $machineCode),
                tableName: 'machines',
                recordId: $machineId,
                oldValues: $oldValues,
            );

            return true;
        });

        // QR cleanup hanya dilakukan setelah database transaction berhasil commit.
        // Jika cleanup gagal, database tetap committed dan failure dicatat.
        if ($result && $qrPath) {
            try {
                $this->qrCodeService->deletePath($qrPath);
            } catch (\Throwable $e) {
                Log::warning('QR cleanup failed after machine deletion', [
                    'machine_id' => $machine->id,
                    'qr_path' => $qrPath,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $payload): array
    {
        return Arr::only($payload, [
            'location_id',
            'machine_code',
            'machine_name',
            'description',
            'is_active',
        ]);
    }
}

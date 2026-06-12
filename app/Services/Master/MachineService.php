<?php

namespace App\Services\Master;

use App\Models\Machine;
use App\Services\Auth\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MachineService
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected QrCodeService $qrCodeService,
    ) {
    }

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

    public function delete(Request $request, Machine $machine): void
    {
        $oldValues = $machine->only(['location_id', 'machine_code', 'machine_name', 'qr_token', 'qr_code_path', 'description', 'is_active']);
        $machineId = $machine->id;
        $machineCode = $machine->machine_code;

        DB::transaction(function () use ($machine): void {
            $this->qrCodeService->deleteForMachine($machine);
            $machine->forceDelete();
        });

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_mesin',
            action: 'delete',
            description: sprintf('Delete machine %s', $machineCode),
            tableName: 'machines',
            recordId: $machineId,
            oldValues: $oldValues,
        );
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

<?php

namespace App\Services\Master;

use App\Models\Location;
use App\Services\Auth\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class LocationService
{
    public function __construct(
        protected ActivityLogService $activityLog,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Request $request, array $payload): Location
    {
        $location = Location::query()->create($this->normalizePayload($payload));

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_lokasi',
            action: 'create',
            description: sprintf('Create location %s', $location->location_code),
            tableName: 'locations',
            recordId: $location->id,
            newValues: $location->only(['location_code', 'location_name', 'description', 'is_active']),
        );

        return $location;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Request $request, Location $location, array $payload): Location
    {
        $oldValues = $location->only(['location_code', 'location_name', 'description', 'is_active']);
        $location->fill($this->normalizePayload($payload));
        $location->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_lokasi',
            action: 'update',
            description: sprintf('Update location %s', $location->location_code),
            tableName: 'locations',
            recordId: $location->id,
            oldValues: $oldValues,
            newValues: $location->only(['location_code', 'location_name', 'description', 'is_active']),
        );

        return $location;
    }

    public function deactivate(Request $request, Location $location): void
    {
        $oldValues = $location->only(['location_code', 'location_name', 'description', 'is_active']);

        $location->forceFill([
            'is_active' => false,
        ])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_lokasi',
            action: 'deactivate',
            description: sprintf('Deactivate location %s', $location->location_code),
            tableName: 'locations',
            recordId: $location->id,
            oldValues: $oldValues,
            newValues: $location->only(['location_code', 'location_name', 'description', 'is_active']),
        );
    }

    public function activate(Request $request, Location $location): void
    {
        $oldValues = $location->only(['location_code', 'location_name', 'description', 'is_active']);

        $location->forceFill([
            'is_active' => true,
        ])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_lokasi',
            action: 'activate',
            description: sprintf('Activate location %s', $location->location_code),
            tableName: 'locations',
            recordId: $location->id,
            oldValues: $oldValues,
            newValues: $location->only(['location_code', 'location_name', 'description', 'is_active']),
        );
    }

    /**
     * @return array{allowed: bool, message: string}
     */
    public function delete(Request $request, Location $location): array
    {
        $activeMachinesCount = $location->machines()->where('is_active', true)->count();
        $machinesCount = $location->machines()->count();

        if ($activeMachinesCount > 0) {
            return [
                'allowed' => false,
                'message' => sprintf(
                    'Lokasi tidak dapat dihapus karena masih digunakan oleh %d mesin aktif. Silakan pindahkan mesin terkait terlebih dahulu.',
                    $activeMachinesCount,
                ),
            ];
        }

        if ($machinesCount > 0) {
            return [
                'allowed' => false,
                'message' => 'Lokasi tidak dapat dihapus karena masih memiliki relasi mesin. Hapus atau pindahkan mesin terkait terlebih dahulu.',
            ];
        }

        $oldValues = $location->only(['location_code', 'location_name', 'description', 'is_active']);
        $locationId = $location->id;
        $locationCode = $location->location_code;
        $location->forceDelete();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_lokasi',
            action: 'delete',
            description: sprintf('Delete location %s', $locationCode),
            tableName: 'locations',
            recordId: $locationId,
            oldValues: $oldValues,
        );

        return [
            'allowed' => true,
            'message' => 'Lokasi berhasil dihapus permanen.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $payload): array
    {
        return Arr::only($payload, [
            'location_code',
            'location_name',
            'description',
            'is_active',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreMachineRequest;
use App\Http\Requests\Master\UpdateMachineRequest;
use App\Models\Location;
use App\Models\Machine;
use App\Services\Auth\PrimeAuthService;
use App\Services\Master\MachineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterMesinController extends Controller
{
    public function __construct(
        protected MachineService $machineService,
        protected PrimeAuthService $primeAuth,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $locationId = $request->integer('location_id');

        $machines = Machine::query()
            ->with('location')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('machine_code', 'like', "%{$search}%")
                        ->orWhere('machine_name', 'like', "%{$search}%");
                });
            })
            ->when($locationId > 0, fn ($query) => $query->where('location_id', $locationId))
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('machine_code')
            ->paginate(10)
            ->withQueryString();

        $editingMachine = null;

        if (old('modal_action') === 'edit' && old('machine_id')) {
            $editingMachine = Machine::query()->with('location')->find(old('machine_id'));
        }

        $filterLocations = Location::query()
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'is_active']);

        $formLocationIds = $editingMachine?->location_id
            ? [$editingMachine->location_id]
            : [];

        $formLocations = Location::query()
            ->where(function ($query) use ($formLocationIds): void {
                $query->where('is_active', true);

                if ($formLocationIds !== []) {
                    $query->orWhereIn('id', $formLocationIds);
                }
            })
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'is_active']);

        return view('pages.master.mesin.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'machines' => $machines,
            'filterLocations' => $filterLocations,
            'formLocations' => $formLocations,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'location_id' => $locationId > 0 ? $locationId : null,
            ],
            'modalState' => [
                'action' => old('modal_action'),
                'machine_id' => old('machine_id'),
            ],
            'editingMachine' => $editingMachine,
        ]);
    }

    public function store(StoreMachineRequest $request): RedirectResponse
    {
        $this->machineService->create($request, $request->validated());

        return redirect()
            ->route('master-mesin.index')
            ->with('flash_success', 'Mesin berhasil ditambahkan.');
    }

    public function update(UpdateMachineRequest $request, int $machineId): RedirectResponse
    {
        $machine = $this->findMachine($machineId);
        $this->machineService->update($request, $machine, $request->validated());

        return redirect()
            ->route('master-mesin.index')
            ->with('flash_success', 'Mesin berhasil diperbarui.');
    }

    public function deactivate(Request $request, int $machineId): RedirectResponse
    {
        $machine = $this->findMachine($machineId);
        $this->machineService->deactivate($request, $machine);

        return redirect()
            ->route('master-mesin.index')
            ->with('flash_success', 'Mesin berhasil dinonaktifkan.');
    }

    public function activate(Request $request, int $machineId): RedirectResponse
    {
        $machine = $this->findMachine($machineId);
        $this->machineService->activate($request, $machine);

        return redirect()
            ->route('master-mesin.index')
            ->with('flash_success', 'Mesin berhasil diaktifkan kembali.');
    }

    public function destroy(Request $request, int $machineId): RedirectResponse
    {
        $machine = $this->findMachine($machineId);

        // TASK-003 Slice 3: Cek proteksi histori sebelum menghapus
        if (!$this->machineService->delete($request, $machine)) {
            return redirect()
                ->route('master-mesin.index')
                ->with('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');
        }

        return redirect()
            ->route('master-mesin.index')
            ->with('flash_success', 'Mesin berhasil dihapus permanen.');
    }

    public function generateQr(Request $request, int $machineId): RedirectResponse
    {
        $machine = $this->findMachine($machineId);
        $this->machineService->regenerateQr($request, $machine);

        return redirect()
            ->back()
            ->with('flash_success', 'QR code mesin berhasil digenerate.');
    }

    protected function findMachine(int $machineId): Machine
    {
        return Machine::query()->findOrFail($machineId);
    }
}

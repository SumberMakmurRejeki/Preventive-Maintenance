<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePmChecksheetRequest;
use App\Http\Requests\Master\UpdatePmChecksheetRequest;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Services\Auth\PrimeAuthService;
use App\Services\Master\PmChecksheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterPmChecksheetController extends Controller
{
    public function __construct(
        protected PmChecksheetService $checksheetService,
        protected PrimeAuthService $primeAuth,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $locationId = $request->integer('location_id');
        $machineId = $request->integer('machine_id');

        $checksheets = PmChecksheet::query()
            ->with([
                'machineAssignments.machine.location',
                'machineAssignments.parts.standards',
                'machineAssignments.schedules',
            ])
            ->withCount('machineAssignments')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('checksheet_code', 'like', "%{$search}%")
                        ->orWhere('checksheet_name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($locationId > 0, function ($query) use ($locationId): void {
                $query->whereHas('machineAssignments.machine', fn ($machineQuery) => $machineQuery->where('location_id', $locationId));
            })
            ->when($machineId > 0, function ($query) use ($machineId): void {
                $query->whereHas('machineAssignments', fn ($assignmentQuery) => $assignmentQuery->where('machine_id', $machineId));
            })
            ->orderBy('checksheet_code')
            ->paginate(10)
            ->withQueryString();

        $locations = Location::query()->active()->orderBy('location_name')->get(['id', 'location_name']);
        $machines = Machine::query()->active()->orderBy('machine_code')->get(['id', 'machine_code', 'machine_name']);

        return view('pages.master.checksheet.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'checksheets' => $checksheets,
            'locations' => $locations,
            'machines' => $machines,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'location_id' => $locationId > 0 ? $locationId : null,
                'machine_id' => $machineId > 0 ? $machineId : null,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('pages.master.checksheet.create', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'machines' => $this->availableMachines(),
        ]);
    }

    public function store(StorePmChecksheetRequest $request): RedirectResponse
    {
        $checksheet = $this->checksheetService->create($request, $request->validated());

        return redirect()
            ->route('master-checksheet.show', $checksheet->id)
            ->with('flash_success', 'Checksheet berhasil ditambahkan.');
    }

    public function show(Request $request, int $checksheetId): View
    {
        $checksheet = $this->findChecksheet($checksheetId);

        return view('pages.master.checksheet.show', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'checksheet' => $checksheet,
        ]);
    }

    public function edit(Request $request, int $checksheetId): View
    {
        $checksheet = $this->findChecksheet($checksheetId);

        return view('pages.master.checksheet.edit', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'checksheet' => $checksheet,
            'machines' => $this->availableMachines($checksheet),
            'initialPayload' => $this->checksheetService->toWizardPayload($checksheet),
        ]);
    }

    public function update(UpdatePmChecksheetRequest $request, int $checksheetId): RedirectResponse
    {
        $checksheet = $this->findChecksheet($checksheetId);
        $this->checksheetService->update($request, $checksheet, $request->validated());

        return redirect()
            ->route('master-checksheet.show', $checksheet->id)
            ->with('flash_success', 'Checksheet berhasil diperbarui.');
    }

    public function deactivate(Request $request, int $checksheetId): RedirectResponse
    {
        $checksheet = $this->findChecksheet($checksheetId);
        $this->checksheetService->deactivate($request, $checksheet);

        return redirect()
            ->route('master-checksheet.index')
            ->with('flash_success', 'Checksheet berhasil dinonaktifkan.');
    }

    public function activate(Request $request, int $checksheetId): RedirectResponse
    {
        $checksheet = $this->findChecksheet($checksheetId);
        $this->checksheetService->activate($request, $checksheet);

        return redirect()
            ->route('master-checksheet.index')
            ->with('flash_success', 'Checksheet berhasil diaktifkan kembali.');
    }

    public function destroy(Request $request, int $checksheetId): RedirectResponse
    {
        $checksheet = $this->findChecksheet($checksheetId);
        $this->checksheetService->delete($request, $checksheet);

        return redirect()
            ->route('master-checksheet.index')
            ->with('flash_success', 'Checksheet berhasil dihapus permanen.');
    }

    protected function findChecksheet(int $checksheetId): PmChecksheet
    {
        return PmChecksheet::query()
            ->with([
                'machineAssignments.machine.location',
                'machineAssignments.parts.standards',
                'machineAssignments.schedules.scheduleDates',
            ])
            ->findOrFail($checksheetId);
    }

    protected function availableMachines(?PmChecksheet $checksheet = null)
    {
        $selectedMachineIds = $checksheet
            ? $checksheet->machineAssignments->pluck('machine_id')->all()
            : [];

        return Machine::query()
            ->with('location')
            ->where(function ($query) use ($selectedMachineIds): void {
                $query->where('is_active', true);

                if ($selectedMachineIds !== []) {
                    $query->orWhereIn('id', $selectedMachineIds);
                }
            })
            ->orderBy('machine_code')
            ->get(['id', 'location_id', 'machine_code', 'machine_name', 'is_active']);
    }
}

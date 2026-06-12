<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreLocationRequest;
use App\Http\Requests\Master\UpdateLocationRequest;
use App\Models\Location;
use App\Services\Auth\PrimeAuthService;
use App\Services\Master\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterLokasiController extends Controller
{
    public function __construct(
        protected LocationService $locationService,
        protected PrimeAuthService $primeAuth,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $locations = Location::query()
            ->withCount('machines')
            ->withCount([
                'machines as active_machines_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('location_code', 'like', "%{$search}%")
                        ->orWhere('location_name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('location_code')
            ->paginate(10)
            ->withQueryString();

        $editingLocation = null;

        if (old('modal_action') === 'edit' && old('location_id')) {
            $editingLocation = Location::query()->find(old('location_id'));
        }

        return view('pages.master.lokasi.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'locations' => $locations,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'modalState' => [
                'action' => old('modal_action'),
                'location_id' => old('location_id'),
            ],
            'editingLocation' => $editingLocation,
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $this->locationService->create($request, $request->validated());

        return redirect()
            ->route('master-lokasi.index')
            ->with('flash_success', 'Lokasi berhasil ditambahkan.');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $this->locationService->update($request, $location, $request->validated());

        return redirect()
            ->route('master-lokasi.index')
            ->with('flash_success', 'Lokasi berhasil diperbarui.');
    }

    public function deactivate(Request $request, Location $location): RedirectResponse
    {
        $this->locationService->deactivate($request, $location);

        return redirect()
            ->route('master-lokasi.index')
            ->with('flash_success', 'Lokasi berhasil dinonaktifkan.');
    }

    public function activate(Request $request, Location $location): RedirectResponse
    {
        $this->locationService->activate($request, $location);

        return redirect()
            ->route('master-lokasi.index')
            ->with('flash_success', 'Lokasi berhasil diaktifkan kembali.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $result = $this->locationService->delete($request, $location);

        return redirect()
            ->route('master-lokasi.index')
            ->with($result['allowed'] ? 'flash_success' : 'flash_error', $result['message']);
    }
}

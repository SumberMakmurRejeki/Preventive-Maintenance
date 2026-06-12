<?php

namespace App\Http\Controllers\Breakdown;

use App\Http\Controllers\Controller;
use App\Http\Requests\Breakdown\StoreBreakdownRequest;
use App\Http\Requests\Breakdown\UploadBreakdownMediaRequest;
use App\Models\Location;
use App\Models\Machine;
use App\Services\Auth\PrimeAuthService;
use App\Services\Breakdown\BreakdownMediaService;
use App\Services\Breakdown\BreakdownService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BreakdownInputController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected BreakdownService $breakdownService,
        protected BreakdownMediaService $breakdownMediaService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'location_id' => (int) $request->query('location_id', 0),
            'status_breakdown' => (string) $request->query('status_breakdown', ''),
        ];

        $hasFilters = $filters['search'] !== ''
            || $filters['location_id'] > 0
            || in_array($filters['status_breakdown'], ['none', 'open'], true);

        $loadError = false;
        $loadErrorMessage = null;
        $machines = collect();
        $locations = collect();
        try {
            $locations = Location::query()
                ->orderBy('location_name')
                ->get(['id', 'location_name']);

            $query = Machine::query()
                ->with('location')
                ->withCount([
                    'breakdowns as breakdown_open_count' => fn (Builder $builder) => $builder->where('status', 'open'),
                ]);

            if ($filters['search'] !== '') {
                $search = $filters['search'];
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('machine_code', 'like', "%{$search}%")
                        ->orWhere('machine_name', 'like', "%{$search}%");
                });
            }

            if ($filters['location_id'] > 0) {
                $query->where('location_id', $filters['location_id']);
            }

            if ($filters['status_breakdown'] === 'none') {
                $query->having('breakdown_open_count', '=', 0);
            }

            if ($filters['status_breakdown'] === 'open') {
                $query->having('breakdown_open_count', '>', 0);
            }

            $machines = $query
                ->orderBy('machine_code')
                ->paginate(10)
                ->withQueryString();
        } catch (\Throwable $throwable) {
            report($throwable);
            $loadError = true;
            $loadErrorMessage = 'Terjadi kendala saat memuat data mesin. Silakan coba kembali.';
        }

        return view('pages.breakdown.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'machines' => $machines,
            'locations' => $locations,
            'filters' => $filters,
            'hasFilters' => $hasFilters,
            'loadError' => $loadError,
            'loadErrorMessage' => $loadErrorMessage,
        ]);
    }

    public function create(Request $request, Machine $machine): View|RedirectResponse
    {
        if (! $machine->is_active) {
            return redirect()
                ->route('machines.show', $machine)
                ->with('flash_error', 'Mesin nonaktif. Input breakdown baru tidak dapat dibuat.');
        }

        $machine->load('location');
        $context = $this->breakdownService->inputContext($machine);

        return view('pages.breakdown.input', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'machine' => $machine,
            'parts' => $context['parts'],
            'tempMedia' => collect($request->session()->get($this->tempSessionKey($machine), [])),
        ]);
    }

    public function store(StoreBreakdownRequest $request, Machine $machine): RedirectResponse
    {
        $actor = $this->primeAuth->authenticatedUser();

        if (! $actor) {
            return redirect()->route('login');
        }

        $sessionKey = $this->tempSessionKey($machine);
        $sessionMedia = (array) $request->session()->get($sessionKey, []);

        $chosenTempIds = collect((array) $request->validated('temp_media_ids', []));
        $selectedSessionMedia = collect($sessionMedia)
            ->filter(fn (array $row): bool => $chosenTempIds->contains((string) ($row['id'] ?? '')))
            ->values()
            ->all();

        try {
            $this->breakdownService->store(
                request: $request,
                machine: $machine,
                actor: $actor,
                payload: $request->validated(),
                temporaryMedia: $selectedSessionMedia,
                uploadedMedia: (array) $request->file('media', []),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $request->session()->forget($sessionKey);

        return redirect()
            ->route('machines.show', $machine)
            ->with('flash_success', 'Breakdown OPEN berhasil dibuat.');
    }

    public function uploadMedia(UploadBreakdownMediaRequest $request): JsonResponse
    {
        $machineCode = trim((string) $request->input('machine_code'));
        $machine = Machine::query()->where('machine_code', $machineCode)->firstOrFail();

        $file = $request->file('media_file');

        if (! $file) {
            throw ValidationException::withMessages([
                'media_file' => 'File media tidak ditemukan.',
            ]);
        }

        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'media_file' => 'Upload file tidak valid. Silakan pilih ulang file lalu coba lagi.',
            ]);
        }

        $stored = $this->breakdownMediaService->storeTemporary($file);
        $sessionKey = $this->tempSessionKey($machine);
        $tempMedia = (array) $request->session()->get($sessionKey, []);
        $tempMedia[] = $stored;
        $request->session()->put($sessionKey, $tempMedia);

        return response()->json([
            'message' => 'Media berhasil diupload.',
            'media' => $stored,
        ]);
    }

    public function deleteMedia(Request $request, string $mediaId): JsonResponse
    {
        $machineCode = trim((string) $request->input('machine_code'));
        $machine = Machine::query()->where('machine_code', $machineCode)->firstOrFail();

        $sessionKey = $this->tempSessionKey($machine);
        $tempMedia = (array) $request->session()->get($sessionKey, []);
        $this->breakdownMediaService->deleteTemporaryById($tempMedia, $mediaId);
        $request->session()->put($sessionKey, $tempMedia);

        return response()->json([
            'message' => 'Media berhasil dihapus.',
        ]);
    }

    protected function tempSessionKey(Machine $machine): string
    {
        return 'breakdown_temp_media_'.$machine->id;
    }
}

<?php

namespace App\Http\Controllers\Breakdown;

use App\Http\Controllers\Controller;
use App\Http\Requests\Breakdown\UpdateBreakdownRequest;
use App\Services\Auth\PrimeAuthService;
use App\Services\Breakdown\BreakdownReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BreakdownReviewController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected BreakdownReviewService $breakdownReviewService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status' => trim($request->string('status')->toString()),
            'location_id' => $request->integer('location_id'),
            'machine_id' => $request->integer('machine_id'),
            'part_name' => trim($request->string('part_name')->toString()),
            'pic_open' => trim($request->string('pic_open')->toString()),
            'pic_close' => trim($request->string('pic_close')->toString()),
            'date_field' => trim($request->string('date_field')->toString()) ?: 'breakdown_at',
            'date' => trim($request->string('date')->toString()),
            'min_downtime_hours' => $request->input('min_downtime_hours'),
        ];

        $data = $this->breakdownReviewService->list($filters);

        return view('pages.breakdown.review.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'breakdowns' => $data['breakdowns'],
            'locations' => $data['locations'],
            'machines' => $data['machines'],
            'parts' => $data['parts'],
            'pics' => $data['pics'],
            'closePics' => $data['closePics'],
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $breakdown = $this->breakdownReviewService->findOrFail($id);

        return view('pages.breakdown.review.show', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'breakdown' => $breakdown,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $breakdown = $this->breakdownReviewService->findOrFail($id);

        return view('pages.breakdown.review.edit', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'breakdown' => $breakdown,
        ]);
    }

    public function update(UpdateBreakdownRequest $request, int $id): RedirectResponse
    {
        $breakdown = $this->breakdownReviewService->findOrFail($id);

        try {
            $this->breakdownReviewService->update(
                request: $request,
                breakdown: $breakdown,
                admin: $this->primeAuth->authenticatedUser(),
                payload: $request->validated(),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('breakdown-review.show', $breakdown->id)
            ->with('flash_success', 'Breakdown berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $breakdown = $this->breakdownReviewService->findOrFail($id);
        $code = $breakdown->breakdown_code;
        $this->breakdownReviewService->delete($request, $breakdown);

        return redirect()
            ->route('breakdown-review.index')
            ->with('flash_success', "Breakdown {$code} berhasil dihapus permanen.");
    }
}


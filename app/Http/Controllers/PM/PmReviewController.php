<?php

namespace App\Http\Controllers\PM;

use App\Http\Controllers\Controller;
use App\Http\Requests\PM\ApprovePmExecutionRequest;
use App\Http\Requests\PM\UpdatePmReviewRequest;
use App\Services\Auth\PrimeAuthService;
use App\Services\PM\PmReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PmReviewController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected PmReviewService $pmReviewService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status' => trim($request->string('status')->toString()),
            'location_id' => $request->integer('location_id'),
            'start_date' => trim($request->string('start_date')->toString()),
            'end_date' => trim($request->string('end_date')->toString()),
        ];

        $data = $this->pmReviewService->list($filters);

        return view('pages.pm.review.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'executions' => $data['executions'],
            'locations' => $data['locations'],
            'machines' => $data['machines'],
            'parts' => $data['parts'],
            'pics' => $data['pics'],
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, int $executionId): View
    {
        $execution = $this->pmReviewService->findOrFail($executionId);

        return view('pages.pm.review.show', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'execution' => $execution,
        ]);
    }

    public function edit(Request $request, int $executionId): View|RedirectResponse
    {
        $execution = $this->pmReviewService->findOrFail($executionId);

        if ($execution->status === 'approved') {
            return redirect()
                ->route('pm-review.show', $execution->id)
                ->with('flash_error', 'Hasil PM yang sudah approved tidak dapat diedit lagi.');
        }

        return view('pages.pm.review.edit', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'execution' => $execution,
        ]);
    }

    public function update(UpdatePmReviewRequest $request, int $executionId): RedirectResponse
    {
        $execution = $this->pmReviewService->findOrFail($executionId);

        try {
            $this->pmReviewService->update(
                request: $request,
                execution: $execution,
                admin: $this->primeAuth->authenticatedUser(),
                payload: $request->validated(),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('pm-review.show', $execution->id)
            ->with('flash_success', 'Hasil PM berhasil diperbarui dan history tersimpan.');
    }

    public function approve(ApprovePmExecutionRequest $request, int $executionId): RedirectResponse
    {
        $execution = $this->pmReviewService->findOrFail($executionId);

        try {
            $this->pmReviewService->approve(
                request: $request,
                execution: $execution,
                admin: $this->primeAuth->authenticatedUser(),
                reviewNote: $request->validated('review_note'),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('pm-review.show', $execution->id)
            ->with('flash_success', 'PM berhasil di-approve.');
    }

    public function destroy(Request $request, int $executionId): RedirectResponse
    {
        $execution = $this->pmReviewService->findOrFail($executionId);
        $result = $this->pmReviewService->delete($request, $execution);

        if (! $result['allowed']) {
            return redirect()
                ->route('pm-review.show', $execution->id)
                ->with('flash_error', $result['message']);
        }

        return redirect()
            ->route('pm-review.index')
            ->with('flash_success', $result['message']);
    }
}

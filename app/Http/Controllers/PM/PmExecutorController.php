<?php

namespace App\Http\Controllers\PM;

use App\Http\Controllers\Controller;
use App\Http\Requests\PM\StorePmExecutionRequest;
use App\Http\Requests\PM\UploadPmMediaRequest;
use App\Models\Machine;
use App\Models\PmChecksheetPart;
use App\Models\PmExecutionMedia;
use App\Services\Auth\PrimeAuthService;
use App\Services\PM\PmExecutionMediaService;
use App\Services\PM\PmExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PmExecutorController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected PmExecutionService $pmExecutionService,
        protected PmExecutionMediaService $mediaService,
    ) {
    }

    public function show(Request $request, Machine $machine): View|RedirectResponse
    {
        if (! $machine->is_active) {
            return redirect()
                ->route('machines.show', $machine)
                ->with('flash_error', 'Mesin nonaktif. PM Executor tidak dapat dibuka.');
        }

        try {
            $context = $this->pmExecutionService->getExecutorContext($machine);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('machines.show', $machine)
                ->with('flash_error', $exception->validator->errors()->first() ?: 'PM Executor belum siap.');
        }

        return view('pages.pm.executor', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'machine' => $machine->load('location'),
            'scheduleDate' => $context['schedule_date'],
            'execution' => $context['execution'],
            'checksheetCode' => $context['checksheet_code'],
            'checksheetName' => $context['checksheet_name'],
            'parts' => $context['parts'],
            'existingItems' => $context['existing_items'],
            'mediaByPart' => $context['media_by_part'],
        ]);
    }

    public function start(StorePmExecutionRequest $request, Machine $machine): RedirectResponse
    {
        $operator = $this->primeAuth->authenticatedUser();

        if (! $operator) {
            return redirect()->route('login');
        }

        $this->pmExecutionService->startOrSaveDraft(
            request: $request,
            machine: $machine,
            operator: $operator,
            actionValues: (array) $request->validated('execution_action', []),
            numberValues: (array) $request->validated('execution_number', []),
            partNotes: (array) $request->validated('part_notes', []),
            mediaFiles: (array) $request->file('media_files', []),
        );

        return redirect()
            ->route('pm-executor.show', $machine)
            ->with('flash_success', 'Draft PM berhasil disimpan.');
    }

    public function submit(StorePmExecutionRequest $request, Machine $machine): RedirectResponse
    {
        $operator = $this->primeAuth->authenticatedUser();

        if (! $operator) {
            return redirect()->route('login');
        }

        $this->pmExecutionService->submit(
            request: $request,
            machine: $machine,
            operator: $operator,
            actionValues: (array) $request->validated('execution_action', []),
            numberValues: (array) $request->validated('execution_number', []),
            partNotes: (array) $request->validated('part_notes', []),
            mediaFiles: (array) $request->file('media_files', []),
        );

        return redirect()
            ->route('machines.show', $machine)
            ->with('flash_success', 'PM berhasil disubmit dan menunggu review admin.');
    }

    public function uploadMedia(UploadPmMediaRequest $request, Machine $machine): RedirectResponse|JsonResponse
    {
        $operator = $this->primeAuth->authenticatedUser();

        if (! $operator) {
            return redirect()->route('login');
        }

        $context = $this->pmExecutionService->getExecutorContext($machine);
        $execution = $this->pmExecutionService->startExecutionOnly(
            request: $request,
            machine: $machine,
            operator: $operator,
            resolvedContext: $context,
        );

        $partId = (int) $request->validated('part_id');
        $part = $partId > 0
            ? $context['parts']->first(fn (PmChecksheetPart $candidate): bool => (int) $candidate->id === $partId)
            : null;
        $file = $request->file('media_file');

        if (! $file) {
            return redirect()->route('pm-executor.show', $machine)->with('flash_error', 'File media tidak ditemukan.');
        }

        $media = $this->mediaService->storeForExecution(
            execution: $execution,
            part: $part,
            file: $file,
            operator: $operator,
            note: $request->validated('part_note'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Media PM berhasil diupload.',
                'media' => [
                    'id' => $media->id,
                    'file_name' => $media->file_name,
                    'file_type' => $media->file_type,
                    'file_size' => (int) $media->file_size,
                ],
            ]);
        }

        return redirect()
            ->route('pm-executor.show', $machine)
            ->with('flash_success', 'Media PM berhasil diupload.');
    }

    public function deleteMedia(Request $request, Machine $machine, PmExecutionMedia $media): RedirectResponse|JsonResponse
    {
        $media->loadMissing('execution');
        if ((int) $media->pm_execution_id <= 0 || (int) $media->execution?->machine_id !== (int) $machine->id) {
            abort(404);
        }

        $this->mediaService->deleteMedia($media);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Media PM berhasil dihapus.',
            ]);
        }

        return redirect()
            ->route('pm-executor.show', $machine)
            ->with('flash_success', 'Media PM berhasil dihapus.');
    }
}

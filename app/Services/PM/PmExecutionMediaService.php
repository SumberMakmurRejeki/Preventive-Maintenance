<?php

namespace App\Services\PM;

use App\Models\PmChecksheetPart;
use App\Models\PmExecution;
use App\Models\PmExecutionMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PmExecutionMediaService
{
    /**
     * @throws ValidationException
     */
    public function storeForExecution(
        PmExecution $execution,
        ?PmChecksheetPart $part,
        UploadedFile $file,
        ?User $operator,
        ?string $note = null,
        ?int $partId = null,
    ): PmExecutionMedia {
        $mimeType = (string) $file->getMimeType();

        if (str_starts_with($mimeType, 'image/')) {
            $fileType = 'photo';
            $maxSize = 5 * 1024 * 1024;
        } elseif (str_starts_with($mimeType, 'video/')) {
            $fileType = 'video';
            $maxSize = 50 * 1024 * 1024;
        } else {
            throw ValidationException::withMessages([
                'media_files' => 'File harus berupa foto atau video.',
            ]);
        }

        $fileSize = (int) $file->getSize();

        if ($fileSize > $maxSize) {
            throw ValidationException::withMessages([
                'media_files' => $fileType === 'photo'
                    ? 'Ukuran foto maksimal 5 MB.'
                    : 'Ukuran video maksimal 50 MB.',
            ]);
        }

        return DB::transaction(function () use ($execution, $part, $partId, $file, $operator, $note, $fileType, $mimeType, $fileSize): PmExecutionMedia {
            // Kunci execution lebih dahulu agar upload stale tidak mendahului submit.
            $lockedExecution = PmExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->id);

            if ($lockedExecution->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'media_files' => 'Media PM hanya dapat diunggah saat PM sedang dikerjakan.',
                ]);
            }

            // Part ID wajib diverifikasi ulang terhadap checksheet occurrence yang terkunci;
            // context controller tidak dipercaya sebagai sumber keputusan.
            $requestedPartId = $partId ?? $part?->id;
            $resolvedPart = null;
            if ($requestedPartId) {
                $lockedExecution->loadMissing('scheduleDate.schedule.checksheetMachine');
                $checksheetMachineId = $lockedExecution->scheduleDate?->schedule?->pm_checksheet_machine_id;
                $resolvedPart = PmChecksheetPart::query()
                    ->whereKey($requestedPartId)
                    ->where('pm_checksheet_machine_id', $checksheetMachineId)
                    ->first();

                if (! $resolvedPart) {
                    throw ValidationException::withMessages([
                        'part_id' => 'Part media tidak sesuai dengan checksheet occurrence PM.',
                    ]);
                }
            }

            $storedPath = $file->store('pm-execution-media', 'public');

            return PmExecutionMedia::query()->create([
                'pm_execution_id' => $lockedExecution->id,
                'pm_execution_item_id' => null,
                'pm_checksheet_part_id' => $resolvedPart?->id,
                'part_name_snapshot' => $resolvedPart?->part_name,
                'file_type' => $fileType,
                'file_path' => $storedPath,
                'original_file_path' => $storedPath,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'original_file_size' => $fileSize,
                'compressed_file_size' => $fileSize,
                'note' => $note,
                'uploaded_by' => $operator?->id,
                'uploaded_by_name_snapshot' => $operator?->name,
            ]);
        });
    }

    /**
     * Hapus media PM dengan proteksi lifecycle.
     * Kunci execution (lockForUpdate) sebelum media agar submit bersamaan
     * tidak dapat meloloskan penghapusan saat status bukan in_progress.
     *
     * @throws ValidationException
     */
    public function deleteMedia(PmExecutionMedia $media): void
    {
        // Mutasi database dilakukan di dalam transaksi; cleanup filesystem hanya setelah commit berhasil.
        $pathsToClean = [];

        DB::transaction(function () use ($media, &$pathsToClean): void {
            // ADR-003: kunci execution lebih dahulu agar media tidak dihapus setelah menjadi bukti transaksi.
            // Urutan kunci: 1) execution 2) media 3) mutasi, sesuai protokol serialisasi submit/delete.
            $execution = PmExecution::query()->lockForUpdate()->findOrFail($media->pm_execution_id);
            $lockedMedia = PmExecutionMedia::query()->lockForUpdate()->findOrFail($media->id);

            // Hanya status in_progress yang diizinkan menghapus media.
            if ($execution->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'media' => 'Media PM hanya dapat dihapus saat PM sedang dikerjakan.',
                ]);
            }

            // Snapshot path sebelum delete dan deduplikasi agar path yang sama tidak dihapus dua kali.
            $paths = array_values(array_unique(array_filter([
                $lockedMedia->file_path !== '' ? $lockedMedia->file_path : null,
                $lockedMedia->original_file_path ?: null,
            ])));

            // Hapus baris media dalam database; filesystem cleanup berada di luar transaksi.
            $lockedMedia->delete();
            $pathsToClean = $paths;
        });

        // Kegagalan cleanup dicatat sebagai warning dan tidak membatalkan lifecycle setelah commit.
        foreach ($pathsToClean as $path) {
            try {
                $deleted = Storage::disk('public')->delete($path);
                if ($deleted === false) {
                    Log::warning('Media PM cleanup failed: Storage::delete() returned false', [
                        'pm_execution_id' => $media->pm_execution_id,
                        'pm_execution_media_id' => $media->id,
                        'path' => $path,
                        'error' => 'Storage::delete() returned false',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Media PM cleanup failed: exception during Storage::delete()', [
                    'pm_execution_id' => $media->pm_execution_id,
                    'pm_execution_media_id' => $media->id,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

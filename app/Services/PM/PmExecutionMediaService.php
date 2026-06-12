<?php

namespace App\Services\PM;

use App\Models\PmChecksheetPart;
use App\Models\PmExecution;
use App\Models\PmExecutionMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

        $storedPath = $file->store('pm-execution-media', 'public');

        return PmExecutionMedia::query()->create([
            'pm_execution_id' => $execution->id,
            'pm_execution_item_id' => null,
            'pm_checksheet_part_id' => $part?->id,
            'part_name_snapshot' => $part?->part_name,
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
    }

    public function deleteMedia(PmExecutionMedia $media): void
    {
        if ($media->file_path !== '') {
            Storage::disk('public')->delete($media->file_path);
        }

        if ($media->original_file_path) {
            Storage::disk('public')->delete($media->original_file_path);
        }

        $media->delete();
    }
}


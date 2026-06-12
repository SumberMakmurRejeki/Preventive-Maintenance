<?php

namespace App\Services\Breakdown;

use App\Models\BreakdownMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BreakdownMediaService
{
    /**
     * @return array{id:string,file_type:string,file_path:string,file_name:string,mime_type:string,file_size:int,original_file_path:string,original_file_size:int,compressed_file_size:int}
     */
    public function storeTemporary(UploadedFile $file): array
    {
        $validated = $this->validateAndStoreFile($file, 'breakdown-media-temp');
        $validated['id'] = (string) str()->uuid();

        return $validated;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $temporaryMedia
     */
    public function persistTemporaryToBreakdown(int $breakdownId, Collection $temporaryMedia, ?User $uploader, ?string $note = null): void
    {
        foreach ($temporaryMedia as $media) {
            $sourcePath = (string) $media['file_path'];
            $targetPath = str_replace('breakdown-media-temp/', 'breakdown-media/', $sourcePath);

            if (Storage::disk('public')->exists($sourcePath)) {
                Storage::disk('public')->move($sourcePath, $targetPath);
            }

            BreakdownMedia::query()->create([
                'breakdown_id' => $breakdownId,
                'file_type' => (string) $media['file_type'],
                'file_path' => $targetPath,
                'original_file_path' => $targetPath,
                'file_name' => (string) $media['file_name'],
                'mime_type' => (string) $media['mime_type'],
                'file_size' => (int) $media['file_size'],
                'original_file_size' => (int) $media['original_file_size'],
                'compressed_file_size' => (int) $media['compressed_file_size'],
                'note' => $note,
                'uploaded_by' => $uploader?->id,
                'uploaded_by_name_snapshot' => $uploader?->name,
            ]);
        }
    }

    public function persistUploadedFileToBreakdown(int $breakdownId, UploadedFile $file, ?User $uploader, ?string $note = null): void
    {
        $stored = $this->validateAndStoreFile($file, 'breakdown-media');

        BreakdownMedia::query()->create([
            'breakdown_id' => $breakdownId,
            'file_type' => $stored['file_type'],
            'file_path' => $stored['file_path'],
            'original_file_path' => $stored['original_file_path'],
            'file_name' => $stored['file_name'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'],
            'original_file_size' => $stored['original_file_size'],
            'compressed_file_size' => $stored['compressed_file_size'],
            'note' => $note,
            'uploaded_by' => $uploader?->id,
            'uploaded_by_name_snapshot' => $uploader?->name,
        ]);
    }

    public function deleteTemporaryById(array &$sessionMedia, string $tempId): void
    {
        foreach ($sessionMedia as $index => $media) {
            if (($media['id'] ?? null) !== $tempId) {
                continue;
            }

            $path = (string) ($media['file_path'] ?? '');
            if ($path !== '') {
                Storage::disk('public')->delete($path);
            }

            unset($sessionMedia[$index]);
        }

        $sessionMedia = array_values($sessionMedia);
    }

    /**
     * @return array{file_type:string,file_path:string,file_name:string,mime_type:string,file_size:int,original_file_path:string,original_file_size:int,compressed_file_size:int}
     */
    protected function validateAndStoreFile(UploadedFile $file, string $directory): array
    {
        $mimeType = (string) $file->getMimeType();
        $fileSize = (int) $file->getSize();

        if (str_starts_with($mimeType, 'image/')) {
            $fileType = 'photo';
            $maxSize = 5 * 1024 * 1024;
        } elseif (str_starts_with($mimeType, 'video/')) {
            $fileType = 'video';
            $maxSize = 50 * 1024 * 1024;
        } else {
            throw ValidationException::withMessages([
                'media_file' => 'File harus berupa foto atau video.',
            ]);
        }

        if ($fileSize > $maxSize) {
            throw ValidationException::withMessages([
                'media_file' => $fileType === 'photo' ? 'Ukuran foto maksimal 5 MB.' : 'Ukuran video maksimal 50 MB.',
            ]);
        }

        $storedPath = $file->store($directory, 'public');

        return [
            'file_type' => $fileType,
            'file_path' => $storedPath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'original_file_path' => $storedPath,
            'original_file_size' => $fileSize,
            'compressed_file_size' => $fileSize,
        ];
    }
}

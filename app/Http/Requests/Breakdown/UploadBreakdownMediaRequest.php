<?php

namespace App\Http\Requests\Breakdown;

use Illuminate\Foundation\Http\FormRequest;

class UploadBreakdownMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'machine_code' => ['required', 'string'],
            'media_file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm',
                'max:51200',
            ],
        ];
    }
}

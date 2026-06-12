<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class StorePushSubscriptionRequest extends FormRequest
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
            'endpoint' => ['required', 'string', 'max:500'],
            'public_key' => ['nullable', 'string'],
            'auth_token' => ['nullable', 'string'],
            'content_encoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keys = $this->input('keys', []);

        $this->merge([
            'endpoint' => trim((string) $this->input('endpoint')),
            'public_key' => isset($keys['p256dh']) ? trim((string) $keys['p256dh']) : null,
            'auth_token' => isset($keys['auth']) ? trim((string) $keys['auth']) : null,
            'content_encoding' => $this->filled('content_encoding')
                ? trim((string) $this->input('content_encoding'))
                : null,
        ]);
    }
}

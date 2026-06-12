<?php

namespace App\Services\Master;

use App\Models\Machine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function createUniqueToken(): string
    {
        do {
            $token = 'qr-'.Str::lower((string) Str::ulid());
        } while (Machine::query()->where('qr_token', $token)->exists());

        return $token;
    }

    public function buildTargetUrl(Machine $machine): string
    {
        return url("/qr/{$machine->qr_token}");
    }

    public function buildStoragePath(Machine $machine): string
    {
        return sprintf('qr-codes/%s.svg', Str::lower($machine->machine_code));
    }

    public function generateForMachine(Machine $machine): string
    {
        $path = $this->buildStoragePath($machine);
        $qrMarkup = QrCode::format('svg')
            ->size(320)
            ->margin(1)
            ->generate($this->buildTargetUrl($machine));

        Storage::disk('public')->put($path, $qrMarkup);

        return $path;
    }

    public function deleteForMachine(Machine $machine): void
    {
        if ($machine->qr_code_path) {
            Storage::disk('public')->delete($machine->qr_code_path);
        }
    }

}

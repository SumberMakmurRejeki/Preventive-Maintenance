<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin.prime'],
            [
                'name' => 'Admin PRIME',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['username' => 'operator.prime'],
            [
                'name' => 'Operator PRIME',
                'password' => 'password',
                'role' => 'operator',
                'is_active' => true,
            ],
        );

        $location = Location::query()->updateOrCreate(
            ['location_code' => 'LOC-PRD-A'],
            [
                'location_name' => 'Produksi A',
                'description' => 'Lokasi seed untuk flow auth dan QR',
                'is_active' => true,
            ],
        );

        Machine::query()->updateOrCreate(
            ['machine_code' => 'MCH-001'],
            [
                'location_id' => $location->id,
                'machine_name' => 'CNC Milling',
                'qr_token' => 'qr-mch-001',
                'description' => 'Mesin aktif untuk validasi login, QR, dan machine access.',
                'is_active' => true,
            ],
        );

        Machine::query()->updateOrCreate(
            ['machine_code' => 'MCH-002'],
            [
                'location_id' => $location->id,
                'machine_name' => 'Sealing Machine',
                'qr_token' => 'qr-mch-002',
                'description' => 'Mesin nonaktif untuk memastikan tombol aksi disabled.',
                'is_active' => false,
            ],
        );
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan fondasi lifecycle (status dan batas live efektif) secara additive.
     *
     * Migrasi ini tidak mengubah operational_from, start_date, generate_until,
     * pm_schedule_dates, pm_executions, maupun snapshot/histori apa pun.
     * Backfill hanya memetakan boolean legacy ke status non-terminal.
     */
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table): void {
            // ENUM menjaga invariant lifecycle langsung di database, termasuk direct SQL writes.
            $table->enum('lifecycle_status', ['active', 'inactive', 'retired'])
                ->default('active')
                ->after('is_active');
            $table->date('effective_live_from')->nullable()->after('lifecycle_status');
        });

        Schema::table('pm_schedules', function (Blueprint $table): void {
            // Schedule memiliki state finite yang berbeda dari Machine dan tetap nullable boundary.
            $table->enum('lifecycle_status', ['active', 'paused', 'ended'])
                ->default('active')
                ->after('is_active');
            $table->date('effective_live_from')->nullable()->after('lifecycle_status');
        });

        // Backfill deterministik: true -> active, false -> inactive.
        // Status terminal (retired/ended) tidak pernah ditebak dari boolean legacy.
        DB::table('machines')
            ->where('is_active', false)
            ->update(['lifecycle_status' => 'inactive']);

        // Backfill jadwal: true -> active, false -> paused (bukan ended).
        DB::table('pm_schedules')
            ->where('is_active', false)
            ->update(['lifecycle_status' => 'paused']);

        // Legacy tidak memiliki nilai live efektif; tetap NULL sampai diputuskan
        // oleh slice lanjutan. Tidak ada nilai otomatis dari tanggal lain.
    }

    /**
     * Menghapus hanya kolom lifecycle yang ditambahkan migrasi ini.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table): void {
            $table->dropColumn(['lifecycle_status', 'effective_live_from']);
        });

        Schema::table('pm_schedules', function (Blueprint $table): void {
            $table->dropColumn(['lifecycle_status', 'effective_live_from']);
        });
    }
};

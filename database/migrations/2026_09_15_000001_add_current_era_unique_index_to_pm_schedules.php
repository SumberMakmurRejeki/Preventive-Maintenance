<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preflight hanya mendeteksi pelanggaran invariant current-era yang nyata;
        // migration tidak boleh memilih pemenang atau mengubah histori secara otomatis.
        $collisions = DB::table('pm_schedules')
            ->whereNull('deleted_at')
            ->whereIn('lifecycle_status', ['active', 'paused'])
            ->select('pm_checksheet_machine_id')
            ->selectRaw('COUNT(*) AS current_era_count')
            ->groupBy('pm_checksheet_machine_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        if ($collisions->isNotEmpty()) {
            throw new RuntimeException('Tidak dapat menambah unique current era karena ditemukan schedule active/paused ganda.');
        }

        Schema::table('pm_schedules', function (Blueprint $table): void {
            // NULL membebaskan era ENDED dan row soft-deleted dari unique invariant.
            $table->unsignedBigInteger('current_era_key')
                ->virtualAs("CASE WHEN lifecycle_status IN ('active', 'paused') AND deleted_at IS NULL THEN pm_checksheet_machine_id ELSE NULL END")
                ->nullable();
            $table->unique('current_era_key', 'pm_schedules_current_era_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table): void {
            // Rollback hanya membuang backstop turunan; source historical row tidak ditulis ulang.
            $table->dropUnique('pm_schedules_current_era_unique');
            $table->dropColumn('current_era_key');
        });
    }
};

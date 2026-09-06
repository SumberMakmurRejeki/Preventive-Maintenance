<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan backstop uniqueness setelah memastikan data legacy aman.
     */
    public function up(): void
    {
        // Preflight membaca seluruh row, termasuk soft-deleted, agar DDL tidak menyamarkan histori duplikat.
        $duplicates = DB::table('pm_executions')
            ->select('pm_schedule_date_id')
            ->selectRaw('COUNT(*) AS row_count')
            ->groupBy('pm_schedule_date_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Migrasi dibatalkan: ditemukan execution PM duplikat untuk pm_schedule_date_id '
                .$duplicates->pluck('pm_schedule_date_id')->implode(', ').'. '
                .'Perlu keputusan manager sebelum data histori diubah.'
            );
        }

        // Unique index menjadi invariant database; index biasa dan foreign key existing tetap dipertahankan.
        Schema::table('pm_executions', function (Blueprint $table): void {
            $table->unique('pm_schedule_date_id', 'pm_executions_pm_schedule_date_id_unique');
        });
    }

    /**
     * Menghapus hanya backstop uniqueness tanpa menyentuh index biasa atau foreign key.
     */
    public function down(): void
    {
        Schema::table('pm_executions', function (Blueprint $table): void {
            $table->dropUnique('pm_executions_pm_schedule_date_id_unique');
        });
    }
};

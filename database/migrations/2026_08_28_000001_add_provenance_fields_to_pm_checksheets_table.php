<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan metadata provenance untuk membedakan checksheet baru dan legacy.
     */
    public function up(): void
    {
        Schema::table('pm_checksheets', function (Blueprint $table): void {
            $table->boolean('assignment_history_known')
                ->default(false)
                ->after('is_active');
            $table->timestamp('first_observed_machine_assignment_at')
                ->nullable()
                ->after('assignment_history_known');
        });
    }

    /**
     * Menghapus metadata provenance saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::table('pm_checksheets', function (Blueprint $table): void {
            $table->dropColumn([
                'assignment_history_known',
                'first_observed_machine_assignment_at',
            ]);
        });
    }
};

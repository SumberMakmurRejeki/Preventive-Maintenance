<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations — additive, nullable, tanpa backfill atau rewrite data.
     */
    public function up(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->date('operational_from')->nullable()->after('monthly_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->dropColumn('operational_from');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pm_executions', function (Blueprint $table) {
            // ADR-007 Slice A: snapshot identitas transaksi (immutable) untuk execution baru.
            // Seluruh kolom nullable — row legacy tetap NULL tanpa backfill dari master.
            $table->string('machine_code_snapshot', 50)->nullable();
            $table->string('machine_name_snapshot', 100)->nullable();
            $table->string('location_code_snapshot', 50)->nullable();
            $table->string('location_name_snapshot', 100)->nullable();
            $table->string('checksheet_code_snapshot', 50)->nullable();
            $table->string('checksheet_name_snapshot', 150)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_executions', function (Blueprint $table) {
            // down(): hapus HANYA enam kolom snapshot yang ditambahkan Slice A.
            $table->dropColumn([
                'machine_code_snapshot',
                'machine_name_snapshot',
                'location_code_snapshot',
                'location_name_snapshot',
                'checksheet_code_snapshot',
                'checksheet_name_snapshot',
            ]);
        });
    }
};

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
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->index();
            $table->string('machine_code', 50)->unique();
            $table->string('machine_name', 100)->index();
            $table->string('qr_token', 100)->unique();
            $table->string('qr_code_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index(['location_id', 'is_active']);
            $table->foreign('location_id', 'machines_location_id_foreign')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};

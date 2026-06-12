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
        Schema::create('pm_checksheets', function (Blueprint $table) {
            $table->id();
            $table->string('checksheet_code', 50)->unique();
            $table->string('checksheet_name', 150)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->foreign('created_by', 'pm_checksheets_created_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('pm_checksheet_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_checksheet_id')->index();
            $table->foreignId('machine_id')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['pm_checksheet_id', 'machine_id'], 'pcm_checksheet_machine_unique');
            $table->foreign('pm_checksheet_id', 'pcm_pm_checksheet_id_foreign')
                ->references('id')
                ->on('pm_checksheets')
                ->cascadeOnDelete();
            $table->foreign('machine_id', 'pcm_machine_id_foreign')
                ->references('id')
                ->on('machines')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'pcm_created_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('pm_checksheet_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_checksheet_machine_id')->index();
            $table->string('part_name', 100)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['pm_checksheet_machine_id', 'part_name'], 'pcp_machine_part_unique');
            $table->foreign('pm_checksheet_machine_id', 'pcp_pm_checksheet_machine_id_foreign')
                ->references('id')
                ->on('pm_checksheet_machines')
                ->cascadeOnDelete();
        });

        Schema::create('pm_checksheet_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_checksheet_part_id')->index();
            $table->string('standard_name', 150)->index();
            $table->enum('input_type', ['action', 'number', 'range'])->index();
            $table->json('action_options')->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->decimal('min_value', 12, 2)->nullable();
            $table->decimal('max_value', 12, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['pm_checksheet_part_id', 'standard_name'], 'pcs_part_standard_unique');
            $table->foreign('pm_checksheet_part_id', 'pcs_pm_checksheet_part_id_foreign')
                ->references('id')
                ->on('pm_checksheet_parts')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_checksheet_standards');
        Schema::dropIfExists('pm_checksheet_parts');
        Schema::dropIfExists('pm_checksheet_machines');
        Schema::dropIfExists('pm_checksheets');
    }
};

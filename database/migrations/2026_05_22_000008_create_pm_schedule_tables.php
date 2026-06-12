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
        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_checksheet_machine_id')->index();
            $table->enum('frequency_type', ['daily', 'weekly', 'monthly'])->index();
            $table->json('weekly_days')->nullable();
            $table->unsignedTinyInteger('monthly_day')->nullable();
            $table->date('start_date')->index();
            $table->date('generate_until')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('pm_checksheet_machine_id', 'pm_schedules_pcm_id_foreign')
                ->references('id')
                ->on('pm_checksheet_machines')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'pm_schedules_created_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('pm_schedule_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->index();
            $table->foreignId('machine_id')->index();
            $table->date('scheduled_date')->index();
            $table->enum('status', ['scheduled', 'in_progress', 'waiting_review', 'approved', 'overdue', 'missed'])->index()->default('scheduled');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('generated_at')->nullable()->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');

            $table->unique(['pm_schedule_id', 'scheduled_date'], 'pm_schedule_dates_unique');
            $table->index(['machine_id', 'scheduled_date'], 'pm_schedule_dates_machine_date_index');
            $table->index(['scheduled_date', 'status'], 'pm_schedule_dates_date_status_index');
            $table->foreign('pm_schedule_id', 'pm_schedule_dates_schedule_id_foreign')
                ->references('id')
                ->on('pm_schedules')
                ->cascadeOnDelete();
            $table->foreign('machine_id', 'pm_schedule_dates_machine_id_foreign')
                ->references('id')
                ->on('machines')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_schedule_dates');
        Schema::dropIfExists('pm_schedules');
    }
};

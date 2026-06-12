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
        Schema::create('pm_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_date_id')->index();
            $table->foreignId('machine_id')->index();
            $table->foreignId('operator_id')->nullable()->index();
            $table->string('operator_name_snapshot', 100)->nullable();
            $table->enum('status', ['in_progress', 'waiting_review', 'approved'])->default('in_progress')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->foreignId('approved_by')->nullable()->index();
            $table->string('approved_by_name_snapshot', 100)->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index(['machine_id', 'status'], 'pm_exec_machine_status_index');

            $table->foreign('pm_schedule_date_id', 'pm_executions_schedule_date_id_foreign')
                ->references('id')
                ->on('pm_schedule_dates')
                ->cascadeOnDelete();
            $table->foreign('machine_id', 'pm_executions_machine_id_foreign')
                ->references('id')
                ->on('machines')
                ->cascadeOnDelete();
            $table->foreign('operator_id', 'pm_executions_operator_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('approved_by', 'pm_executions_approved_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('pm_execution_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_execution_id')->index();
            $table->foreignId('pm_checksheet_part_id')->nullable()->index();
            $table->foreignId('pm_checksheet_standard_id')->nullable()->index();
            $table->string('part_name_snapshot', 100);
            $table->string('standard_name_snapshot', 150);
            $table->enum('input_type_snapshot', ['action', 'number', 'range'])->index();
            $table->json('action_options_snapshot')->nullable();
            $table->decimal('target_value_snapshot', 12, 2)->nullable();
            $table->decimal('min_value_snapshot', 12, 2)->nullable();
            $table->decimal('max_value_snapshot', 12, 2)->nullable();
            $table->string('unit_snapshot', 30)->nullable();
            $table->string('action_value', 50)->nullable();
            $table->decimal('number_value', 12, 2)->nullable();
            $table->boolean('is_warning')->default(false)->index();
            $table->string('warning_message', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('pm_execution_id', 'pm_execution_items_execution_id_foreign')
                ->references('id')
                ->on('pm_executions')
                ->cascadeOnDelete();
            $table->foreign('pm_checksheet_part_id', 'pm_execution_items_part_id_foreign')
                ->references('id')
                ->on('pm_checksheet_parts')
                ->nullOnDelete();
            $table->foreign('pm_checksheet_standard_id', 'pm_execution_items_standard_id_foreign')
                ->references('id')
                ->on('pm_checksheet_standards')
                ->nullOnDelete();
        });

        Schema::create('pm_execution_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_execution_id')->index();
            $table->foreignId('pm_execution_item_id')->nullable()->index();
            $table->foreignId('pm_checksheet_part_id')->nullable()->index();
            $table->string('part_name_snapshot', 100)->nullable();
            $table->enum('file_type', ['photo', 'video'])->index();
            $table->string('file_path');
            $table->string('original_file_path')->nullable();
            $table->string('file_name', 150);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('original_file_size')->nullable();
            $table->unsignedBigInteger('compressed_file_size')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->index();
            $table->string('uploaded_by_name_snapshot', 100)->nullable();
            $table->timestamps();

            $table->foreign('pm_execution_id', 'pm_execution_media_execution_id_foreign')
                ->references('id')
                ->on('pm_executions')
                ->cascadeOnDelete();
            $table->foreign('pm_execution_item_id', 'pm_execution_media_item_id_foreign')
                ->references('id')
                ->on('pm_execution_items')
                ->nullOnDelete();
            $table->foreign('pm_checksheet_part_id', 'pm_execution_media_part_id_foreign')
                ->references('id')
                ->on('pm_checksheet_parts')
                ->nullOnDelete();
            $table->foreign('uploaded_by', 'pm_execution_media_uploaded_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('pm_execution_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_execution_id')->index();
            $table->foreignId('changed_by')->nullable()->index();
            $table->timestamp('changed_at')->useCurrent()->index();
            $table->string('field_name', 100)->index();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('pm_execution_id', 'pm_execution_history_execution_id_foreign')
                ->references('id')
                ->on('pm_executions')
                ->cascadeOnDelete();
            $table->foreign('changed_by', 'pm_execution_history_changed_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_execution_history');
        Schema::dropIfExists('pm_execution_media');
        Schema::dropIfExists('pm_execution_items');
        Schema::dropIfExists('pm_executions');
    }
};

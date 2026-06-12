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
        Schema::create('breakdowns', function (Blueprint $table) {
            $table->id();
            $table->string('breakdown_code', 50)->unique();
            $table->foreignId('machine_id')->index();
            $table->foreignId('pm_checksheet_part_id')->nullable()->index();
            $table->string('machine_name_snapshot', 100);
            $table->string('location_name_snapshot', 100)->nullable();
            $table->string('part_name_snapshot', 100)->nullable();
            $table->string('custom_part_name', 100)->nullable();
            $table->text('problem');
            $table->text('open_note')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamp('breakdown_at')->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->string('created_by_name_snapshot', 100)->nullable();
            $table->text('root_cause')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('countermeasure')->nullable();
            $table->timestamp('closed_at')->nullable()->index();
            $table->foreignId('closed_by')->nullable()->index();
            $table->string('closed_by_name_snapshot', 100)->nullable();
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index(['machine_id', 'status'], 'breakdowns_machine_status_index');
            $table->index(['breakdown_at', 'status'], 'breakdowns_date_status_index');

            $table->foreign('machine_id', 'breakdowns_machine_id_foreign')
                ->references('id')
                ->on('machines')
                ->cascadeOnDelete();
            $table->foreign('pm_checksheet_part_id', 'breakdowns_part_id_foreign')
                ->references('id')
                ->on('pm_checksheet_parts')
                ->nullOnDelete();
            $table->foreign('created_by', 'breakdowns_created_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('closed_by', 'breakdowns_closed_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('breakdown_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('breakdown_id')->index();
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

            $table->foreign('breakdown_id', 'breakdown_media_breakdown_id_foreign')
                ->references('id')
                ->on('breakdowns')
                ->cascadeOnDelete();
            $table->foreign('uploaded_by', 'breakdown_media_uploaded_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('breakdown_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('breakdown_id')->index();
            $table->foreignId('changed_by')->nullable()->index();
            $table->timestamp('changed_at')->useCurrent()->index();
            $table->string('field_name', 100)->index();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('breakdown_id', 'breakdown_history_breakdown_id_foreign')
                ->references('id')
                ->on('breakdowns')
                ->cascadeOnDelete();
            $table->foreign('changed_by', 'breakdown_history_changed_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('notification_type', [
                'pm_waiting_review',
                'pm_overdue',
                'pm_missed',
                'breakdown_open',
                'breakdown_closed',
                'system',
            ])->index();
            $table->string('title', 150);
            $table->text('message');
            $table->enum('target_role', ['admin'])->default('admin')->index();
            $table->foreignId('target_user_id')->nullable()->index();
            $table->string('related_table', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('target_url')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['related_table', 'related_id'], 'notifications_related_index');

            $table->foreign('target_user_id', 'notifications_target_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->timestamp('read_at')->useCurrent()->index();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id'], 'notification_reads_unique');
            $table->foreign('notification_id', 'notification_reads_notification_id_foreign')
                ->references('id')
                ->on('notifications')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'notification_reads_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->enum('report_type', ['pm', 'breakdown'])->index();
            $table->enum('file_type', ['pdf', 'excel'])->index();
            $table->string('file_name', 150)->nullable();
            $table->string('file_path')->nullable();
            $table->json('filter_data')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing')->index();
            $table->foreignId('requested_by')->nullable()->index();
            $table->string('requested_by_name_snapshot', 100)->nullable();
            $table->timestamp('requested_at')->useCurrent()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('failed_message')->nullable();
            $table->timestamps();

            $table->foreign('requested_by', 'report_exports_requested_by_foreign')
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
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('breakdown_history');
        Schema::dropIfExists('breakdown_media');
        Schema::dropIfExists('breakdowns');
    }
};

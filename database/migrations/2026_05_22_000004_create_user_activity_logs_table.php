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
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('guest_session_id')->nullable()->index();
            $table->enum('actor_type', ['user', 'guest', 'system'])->index();
            $table->string('actor_name_snapshot', 100)->nullable();
            $table->string('module_name', 100)->index();
            $table->string('action', 100)->index();
            $table->string('table_name', 100)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['table_name', 'record_id']);
            $table->foreign('user_id', 'user_activity_logs_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('guest_session_id', 'user_activity_logs_guest_session_id_foreign')
                ->references('id')
                ->on('guest_sessions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};

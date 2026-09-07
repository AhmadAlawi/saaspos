<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('frequency', 16);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('time_of_day');
            $table->string('timezone', 64);
            $table->string('destination', 32);
            $table->json('destination_config')->nullable();
            $table->boolean('encryption_enabled')->default(false);
            $table->string('encryption_passphrase_hash')->nullable();
            $table->text('encryption_passphrase_encrypted')->nullable();
            $table->unsignedInteger('retention_count')->nullable();
            $table->unsignedInteger('retention_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('destination', 32);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 16)->default('running');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->boolean('encryption_enabled')->default(false);
            $table->foreignId('schedule_id')->nullable()->constrained('backup_schedules')->nullOnDelete();
            $table->json('manifest')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('restore_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_log_id')->nullable()->constrained('backup_logs')->nullOnDelete();
            $table->string('source', 32);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 16)->default('running');
            $table->text('error_message')->nullable();
            $table->boolean('was_pre_restore_backup_created')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('backup_destination_configs', function (Blueprint $table) {
            $table->id();
            $table->string('destination_key', 32)->unique();
            $table->json('config_json');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_destination_configs');
        Schema::dropIfExists('restore_logs');
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('backup_schedules');
    }
};

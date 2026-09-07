<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_logs', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 32);
            $table->string('to_version', 32);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('pre_update_backup_id')->nullable()->constrained('backup_logs')->nullOnDelete();
            $table->string('channel', 16)->nullable();
            $table->json('steps_log')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_logs');
    }
};

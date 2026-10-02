<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per create/update/delete on an admin-managed model — the
 * "who changed the price on this product" trail. Written by
 * {@see \App\Models\Concerns\Auditable}, only while an authenticated
 * user is present (console/import/seeder writes are not logged, since
 * they aren't "who did this" events). Super-admin-only viewer:
 * {@see \App\Http\Controllers\Admin\AuditLogController}.
 *
 * Named `admin_audit_logs`, NOT `audit_logs` — this install already ships
 * an unrelated `audit_logs` table from the base product (columns:
 * user_id, store_id, action, auditable_type, auditable_id, changes,
 * ip_address, user_agent, created_at; referenced only by
 * ClearSampleData's truncate list, no model/writer anywhere in this
 * codebase). A same-named migration created here would either collide
 * or silently no-op against that table's different schema — which is
 * exactly what happened before this file replaced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 16); // created|updated|deleted
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};

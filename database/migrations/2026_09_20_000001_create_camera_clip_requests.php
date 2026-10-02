<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A queued "go fetch this bit of footage" request — the async
 * replacement for the old synchronous camera-search-and-download flow
 * (that tied up the browser tab for however long the NVR felt like
 * taking). One row per (subject, window): revisiting the same invoice
 * or activity-log entry finds the SAME row instead of re-queuing, and
 * the file is kept for 24h so re-opening the page later still finds it
 * without redoing the work — see
 * {@see \App\Console\Commands\CleanupCameraClipRequests}, which deletes
 * both the row and the file once `expires_at` passes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_clip_requests', function (Blueprint $table) {
            $table->id();
            // 'sale' | 'activity' — which kind of thing this clip is
            // for; $subjectId is the Sale id or CashierActivityLog id.
            // Not a real polymorphic relation (no need for eager
            // loading etc.) — just enough to let a page ask "is there
            // already a request for THIS thing" on load.
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('channel');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->string('label')->nullable();
            $table->string('status', 16)->default('pending'); // pending|processing|ready|failed
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_clip_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces "one OPEN shift per terminal" at the database level.
 *
 * A plain unique index on `terminal_id` can't work — many CLOSED shifts share a
 * terminal. Instead `active_terminal_id` mirrors `terminal_id` only WHILE the
 * shift is open and is NULLed on close, with a UNIQUE index on it. NULLs are
 * exempt from unique indexes (MySQL + SQLite), so a terminal-less shift is still
 * allowed, but any terminal can back at most one open shift — race-proof, unlike
 * the app-level `lockForUpdate` check (which can't lock a not-yet-existing row).
 *
 * Backfill sets it on currently-open shifts, keeping only the earliest open
 * shift per terminal so pre-existing duplicate open shifts (the bug this fixes)
 * don't block the unique index. The stray duplicates stay open but unguarded —
 * close them and reopen to bind them.
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shifts', 'active_terminal_id')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->unsignedBigInteger('active_terminal_id')->nullable()->after('terminal_id');
            });
        }

        // Backfill open shifts, de-duplicated so the unique index can be added.
        $seen = [];
        DB::table('shifts')
            ->where('status', 'open')
            ->whereNotNull('terminal_id')
            ->orderBy('id')
            ->select('id', 'terminal_id')
            ->get()
            ->each(function ($row) use (&$seen) {
                if (isset($seen[$row->terminal_id])) {
                    return; // duplicate open shift on this terminal — leave unguarded
                }
                $seen[$row->terminal_id] = true;
                DB::table('shifts')->where('id', $row->id)->update(['active_terminal_id' => $row->terminal_id]);
            });

        // Add the unique index only if it isn't already there.
        $hasIndex = collect(Schema::getIndexes('shifts'))
            ->contains(fn ($i) => in_array('active_terminal_id', $i['columns'] ?? [], true) && ($i['unique'] ?? false));
        if (! $hasIndex) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->unique('active_terminal_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shifts', 'active_terminal_id')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropUnique(['active_terminal_id']);
                $table->dropColumn('active_terminal_id');
            });
        }
    }
};

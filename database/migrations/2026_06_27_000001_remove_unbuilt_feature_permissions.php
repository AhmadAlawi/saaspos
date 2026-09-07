<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes permissions for feature modules that are not implemented in v1.0
 * (Accounting, Expenses, AI, Plugins, Audit log, WhatsApp). They were seeded
 * by the original catalog but have no routes/controllers/UI, so they appeared
 * as dead rows on the role-builder page. PermissionsSeeder no longer seeds
 * them; this prunes them (and their role pivots) from existing installs.
 *
 * Re-add the relevant block to PermissionsSeeder when the feature ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keys = [
            // Accounting
            'accounting.view',
            'accounting.manual_entry',
            'accounting.lock_period',
            'accounting.unlock_period',
            // Expenses
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',
            // AI
            'ai.use',
            'ai.configure',
            // Plugins
            'plugins.view',
            'plugins.install',
            'plugins.toggle',
            'plugins.delete',
            // Audit log
            'audit.view',
            'audit.export',
            // WhatsApp
            'whatsapp.send_receipt',
        ];

        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // role_permission cascades on permission delete, but prune explicitly
        // so the cleanup is driver-independent (e.g. SQLite without FK pragma).
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // Irreversible data cleanup. Re-running PermissionsSeeder after
        // re-adding the catalog entries restores any permissions that ship.
    }
};

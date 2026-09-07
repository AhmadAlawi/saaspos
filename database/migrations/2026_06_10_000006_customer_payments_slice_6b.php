<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 6b — Customer payments allocation.
 *
 * Re-shapes `sale_payments` so the same table holds both the tender
 * rows captured at sale time AND post-sale settlement rows entered
 * when a customer walks in to clear their tab. Single-table approach
 * mirrors the supplier side (`purchase_payments` doing double duty
 * for receive-time and post-receive payments).
 *
 *   - `sale_id`     → nullable (was NOT NULL). Null = unallocated
 *                     customer credit (over-payment that didn't match
 *                     any open sale; lands on customer.outstanding < 0).
 *   - `customer_id` → new nullable FK. Required on settlement rows so
 *                     unallocated credit knows which customer owns it.
 *                     Sale-time rows leave it null (derived from sale).
 *   - `client_uuid` → groups every row written by a single submission
 *                     so the show page + void flow can find siblings.
 *   - `notes`       → free-text per submission (kept on every row of
 *                     the group; not strictly needed but mirrors the
 *                     supplier side and keeps reads simple).
 *
 * Also seeds the `customers.payments_record` permission. Cashier roles
 * that already carry `cash_drawer.pay_in` (the closest peer permission)
 * get it auto-granted so existing installs aren't broken.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── sale_id → nullable
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->change();
        });

        // ── customer_id + client_uuid + notes
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('sale_id')
                ->constrained('customers')->nullOnDelete();
            $table->char('client_uuid', 36)->nullable()->after('reference');
            $table->text('notes')->nullable()->after('client_uuid');

            $table->index(['customer_id', 'paid_at']);
            $table->index('client_uuid');
        });

        // ── Permission
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'        => 'customers.payments_record',
            'group'      => 'Customers',
            'label'      => 'Record customer payments (settle outstanding sales)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'customers.payments_record')->value('id');
        $peerPermId = (int) DB::table('permissions')->where('key', 'cash_drawer.pay_in')->value('id');
        if ($newPermId && $peerPermId) {
            $roleIds = DB::table('role_permission')->where('permission_id', $peerPermId)->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id'       => $roleId,
                    'permission_id' => $newPermId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'paid_at']);
            $table->dropIndex(['client_uuid']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['client_uuid', 'notes']);
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            // NOTE: this fails if any null sale_id rows exist. The down
            // path is a developer-only escape hatch; production never
            // runs it on a populated table.
            $table->unsignedBigInteger('sale_id')->nullable(false)->change();
        });

        DB::table('permissions')->where('key', 'customers.payments_record')->delete();
    }
};

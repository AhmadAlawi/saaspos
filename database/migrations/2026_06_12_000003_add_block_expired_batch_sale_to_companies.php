<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `companies.block_expired_batch_sale` — admin toggle that, when on,
 * blocks the cashier from ringing up a sale where any line refers to
 * an expired batch (`product_batches.expiry_date < today`). Users with
 * the `inventory.sell_expired` permission bypass the block — pharmacy
 * shops legitimately need a "ring it up and send to return-to-vendor"
 * path that requires a manager override, not a hard prohibition.
 *
 * Default OFF — preserves pre-Slice-3b behaviour where every batch
 * was sellable regardless of expiry. Existing pharmacy installs flip
 * the setting in Inventory settings once they're ready.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            if (! Schema::hasColumn('company', 'block_expired_batch_sale')) {
                $table->boolean('block_expired_batch_sale')->default(false)->after('auto_apply_markup_on_receive');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            if (Schema::hasColumn('company', 'block_expired_batch_sale')) {
                $table->dropColumn('block_expired_batch_sale');
            }
        });
    }
};

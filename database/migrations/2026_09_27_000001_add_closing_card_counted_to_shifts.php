<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cash shortage sometimes isn't really a shortage — a cash sale rung
 * up on the card terminal (or vice versa) makes the drawer look light
 * while the card batch settles heavy by the same amount. There was no
 * way to catch that at close: only cash ever got counted against an
 * expected figure. `closing_card_counted` mirrors `closing_cash_counted`
 * for the card terminal's own batch total (optional — a store that
 * doesn't want to bother with this can leave it blank), with
 * `card_variance` computed the same way `cash_variance` is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->decimal('closing_card_counted', 15, 4)->nullable()->after('closing_cash_counted');
            $table->decimal('card_variance', 15, 4)->nullable()->after('cash_variance');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['closing_card_counted', 'card_variance']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opaque, unguessable token for the no-login "download my Apple Wallet
 * pass" link — same shape as ReceiptPublicLink's token, generated
 * lazily (see WalletPassController). Simpler than reusing
 * ReceiptPublicLink's polymorphic/expiring design: a wallet pass is
 * permanently 1:1 with one customer, no multi-link or expiry semantics
 * needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('wallet_pass_token', 64)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('wallet_pass_token');
        });
    }
};

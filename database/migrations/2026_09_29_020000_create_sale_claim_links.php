<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opaque public link for the CFD "claim this purchase" flow — a walk-in
 * customer (no customer attached at checkout) scans a QR that resolves
 * here, fills in their info, and gets attached to the sale + retroactive
 * points. Separate from ReceiptPublicLink (different lifecycle: a
 * single-use claim vs. an always-live receipt view).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_claim_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_claim_links');
    }
};

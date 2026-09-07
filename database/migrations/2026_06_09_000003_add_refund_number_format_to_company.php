<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `refund_number_format` template alongside the existing
 * sale + hold formats. Read via Company::numberFormat('refund')
 * (defaults to `REFUND-{store}-{Ym}-{seq:04}`).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('refund_number_format', 191)->nullable()->after('hold_number_format');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('refund_number_format');
        });
    }
};

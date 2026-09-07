<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy policy + terms of service content. Stored as markdown on the
 * company row and surfaced at `/privacy-policy` and `/terms` so payment
 * gateways (Razorpay/Paystack/Flutterwave, etc.) can link to them during
 * onboarding. Editable from Settings → Legal pages.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->longText('privacy_policy')->nullable()->after('footer_text');
            $table->longText('terms_of_service')->nullable()->after('privacy_policy');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['privacy_policy', 'terms_of_service']);
        });
    }
};

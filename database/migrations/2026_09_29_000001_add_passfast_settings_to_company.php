<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PassFast (passfa.st) — an alternative to holding your own Apple Pass
 * Type ID certificate. PassFast holds its own Apple Developer signing
 * credentials and signs passes on your behalf via API, in exchange for
 * their own subscription — see PassFastClient's doc-comment for the
 * full trade-off. Preferred over the raw-certificate path when both
 * are configured, since it also supports live balance push-updates
 * (which the raw-certificate path deliberately doesn't attempt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->text('passfast_api_key')->nullable()->after('apple_cert_password');
            // A pass TEMPLATE must be designed once in PassFast's own
            // dashboard (storeCard layout, points/member fields, barcode
            // field) — there's no documented API to create one, so this
            // is filled in by hand after that one-time setup.
            $table->string('passfast_template_id')->nullable()->after('passfast_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['passfast_api_key', 'passfast_template_id']);
        });
    }
};

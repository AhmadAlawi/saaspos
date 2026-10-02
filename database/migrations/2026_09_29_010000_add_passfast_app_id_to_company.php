<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PassFast scopes templates per "app" within an account — a real,
 * confirmed "Template not found" happened with a correct, published
 * template_id and a valid secret key, resolved by sending this
 * alongside the request (see PassFastClient's `X-App-Id` header).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('passfast_app_id')->nullable()->after('passfast_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('passfast_app_id');
        });
    }
};

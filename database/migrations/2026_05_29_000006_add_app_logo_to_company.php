<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App logo (Settings → Branding): the logo shown in the admin shell —
 * the sidebar brand row — in place of the letter mark. Distinct from the
 * company-profile logo, which is the business's logo for receipts and
 * printed documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('app_logo_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('app_logo_path');
        });
    }
};

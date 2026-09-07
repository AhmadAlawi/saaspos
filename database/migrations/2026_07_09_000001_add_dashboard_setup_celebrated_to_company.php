<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers that the one-time "You're all set!" celebration has been shown
 * for the dashboard setup checklist. Once every essential step is complete we
 * show the congratulations state exactly once; on the next dashboard visit the
 * card is gone for good. Company-wide, same as `dashboard_setup_dismissed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('dashboard_setup_celebrated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('dashboard_setup_celebrated');
        });
    }
};

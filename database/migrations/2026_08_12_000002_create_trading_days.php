<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "trading day" wraps every {@see \App\Models\Shift} opened on one
 * terminal on one calendar date. Per-employee cash-drawer accountability
 * stays exactly as it was — each of the store's 3 daily staff shifts
 * still opens/closes its OWN `Shift` with its own float/count — this is
 * purely a day-level rollup so the business gets ONE combined report at
 * close of business instead of one fragment per staff handover.
 *
 * @see \App\Actions\Shifts\OpenShift (finds-or-creates the day on shift open)
 * @see \App\Actions\Shifts\CloseTradingDay (the explicit "Close Day" action)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            // Nullable — stores with no terminal binding configured still
            // get a day wrapper, just not scoped to a specific till.
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->date('business_date');
            $table->string('status', 16)->default('open'); // open | closed
            $table->dateTime('opened_at')->useCurrent();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_path')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'terminal_id', 'business_date']);
            $table->index(['store_id', 'status']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('trading_day_id')->nullable()->after('terminal_id')
                ->constrained('trading_days')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trading_day_id');
        });
        Schema::dropIfExists('trading_days');
    }
};

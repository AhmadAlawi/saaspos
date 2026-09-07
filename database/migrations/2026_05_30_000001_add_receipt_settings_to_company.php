<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt template settings (Settings → Receipt): the look and content
 * of printed sale receipts. Stored on the single company row. Consumed
 * by the receipt printer (not yet built) via `app_receipt()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            // Layout
            $table->string('receipt_paper_size', 8)->default('80mm')->after('time_format');   // 58mm | 80mm | a4
            $table->boolean('receipt_show_logo')->default(true)->after('receipt_paper_size');
            $table->boolean('receipt_show_customer')->default(true)->after('receipt_show_logo');
            $table->boolean('receipt_show_cashier')->default(true)->after('receipt_show_customer');
            $table->boolean('receipt_show_tax_breakdown')->default(true)->after('receipt_show_cashier');
            $table->boolean('receipt_show_barcode')->default(true)->after('receipt_show_tax_breakdown');
            $table->boolean('receipt_show_qr')->default(false)->after('receipt_show_barcode');
            // Content
            $table->text('receipt_header')->nullable()->after('receipt_show_qr');
            $table->text('receipt_footer')->nullable()->after('receipt_header');
            $table->text('receipt_return_policy')->nullable()->after('receipt_footer');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_paper_size', 'receipt_show_logo', 'receipt_show_customer',
                'receipt_show_cashier', 'receipt_show_tax_breakdown', 'receipt_show_barcode',
                'receipt_show_qr', 'receipt_header', 'receipt_footer', 'receipt_return_policy',
            ]);
        });
    }
};

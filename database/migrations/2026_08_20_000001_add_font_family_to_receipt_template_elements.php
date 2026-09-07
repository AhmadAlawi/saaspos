<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-element font choice for the Canvas designer. Arabic text always
 * renders in DejaVu Sans regardless of this value — see
 * {@see \App\Services\Hardware\CanvasReceiptRasterizer::drawText()} — it's
 * the only bundled font proven to render Arabic (FreeSans came out as
 * tofu boxes when tried for the header/footer fix earlier this session).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_template_elements', function (Blueprint $table) {
            $table->string('font_family', 32)->default('dejavu_sans')->after('font_size');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_template_elements', function (Blueprint $table) {
            $table->dropColumn('font_family');
        });
    }
};

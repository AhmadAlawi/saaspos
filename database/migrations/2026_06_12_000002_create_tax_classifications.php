<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now()->toDateTimeString();
        DB::table('tax_classifications')->insert([
            ['name' => 'Taxable',        'slug' => 'taxable',        'description' => 'Standard taxable supply.',                    'sort_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nil Rated',      'slug' => 'nil_rated',      'description' => 'Supply taxable at 0% rate.',                  'sort_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Zero Rated',     'slug' => 'zero_rated',     'description' => 'Zero-rated supply (exports etc.).',           'sort_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Exempt',         'slug' => 'exempt',         'description' => 'Supply exempt from tax.',                     'sort_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Composition',    'slug' => 'composition',    'description' => 'Composite scheme — fixed rate on turnover.', 'sort_order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Reverse Charge', 'slug' => 'reverse_charge', 'description' => 'Tax liability shifted to buyer.',             'sort_order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_classifications');
    }
};

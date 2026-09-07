<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('version', 32);
            $table->string('author');
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamp('last_activated_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('manifest');
            $table->timestamps();
        });

        Schema::create('plugin_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_id')->constrained('plugins')->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value');

            $table->unique(['plugin_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_options');
        Schema::dropIfExists('plugins');
    }
};

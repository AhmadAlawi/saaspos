<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens for the Expo mobile app (price checker / stock-take /
 * label printing). Additive-only companion to the main app — lives
 * entirely outside the session/web-guard auth the rest of the app uses.
 *
 * A token is pinned to one store_id at issue time, because the app's
 * multi-store scoping (current_store_id(), the StoreScoped trait) is
 * session-based and has nothing to resolve for a stateless API request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('token', 100)->unique();
            $table->string('name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_api_tokens');
    }
};

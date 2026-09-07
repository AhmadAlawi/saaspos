<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail / SMTP settings (Settings → Email & SMTP): the outbound mail
 * transport the installation uses for password resets, receipts-by-email,
 * and notifications. Applied to `config('mail.*')` at runtime by the
 * `ApplyCompanySettings` middleware so every send picks them up.
 *
 * `mail_password` is stored encrypted via the Eloquent `encrypted` cast
 * (Laravel uses APP_KEY) — it is never written to the DB in plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('mail_driver', 16)->nullable()->after('receipt_return_policy'); // smtp | log | null
            $table->string('mail_host', 128)->nullable()->after('mail_driver');
            $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username', 191)->nullable()->after('mail_port');
            $table->text('mail_password')->nullable()->after('mail_username');            // encrypted
            $table->string('mail_encryption', 8)->nullable()->after('mail_password');     // tls | ssl | null
            $table->string('mail_from_address', 191)->nullable()->after('mail_encryption');
            $table->string('mail_from_name', 191)->nullable()->after('mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn([
                'mail_driver', 'mail_host', 'mail_port', 'mail_username',
                'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name',
            ]);
        });
    }
};

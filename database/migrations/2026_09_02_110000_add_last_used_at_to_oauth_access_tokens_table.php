<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passport 12 tracks when each access token was last used
 * (updated automatically on every authenticated request).
 * This project's original oauth_access_tokens migration predates
 * the column, so we add it for the /v1/auth/devices endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->timestamp('last_used_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add local_transaction_id for idempotent offline POS sync.
     *
     * The Flutter POS sends a locally-generated UUID for every offline sale
     * (`local_transaction_id`). This column lets the server safely re-serve
     * the already-stored transaction on retry instead of creating a duplicate,
     * which is essential after a network drop mid-commit.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'local_transaction_id')) {
                $table->string('local_transaction_id', 64)->nullable()->after('id');
                $table->index(['business_id', 'local_transaction_id'], 'idx_business_local_tx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'local_transaction_id')) {
                $table->dropIndex('idx_business_local_tx');
                $table->dropColumn('local_transaction_id');
            }
        });
    }
};
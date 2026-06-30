<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallet_transactions', function (Blueprint $table) {
            $table->string('source_module', 32)->nullable()->after('description');
            $table->string('source_id', 64)->nullable()->after('source_module');

            $table->index(['source_module', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['source_module', 'source_id']);
            $table->dropColumn(['source_module', 'source_id']);
        });
    }
};

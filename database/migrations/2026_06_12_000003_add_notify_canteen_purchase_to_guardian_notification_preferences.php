<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_notification_preferences', function (Blueprint $table) {
            $table->boolean('notify_canteen_purchase')->default(true)->after('notify_in_app');
        });
    }

    public function down(): void
    {
        Schema::table('guardian_notification_preferences', function (Blueprint $table) {
            $table->dropColumn('notify_canteen_purchase');
        });
    }
};

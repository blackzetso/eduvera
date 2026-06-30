<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canteen_parent_visibility_queue', function (Blueprint $table) {
            $table->string('notification_status', 16)->default('none')->after('visibility_status');
            $table->unsignedSmallInteger('notification_attempts')->default(0)->after('notification_status');
            $table->text('last_notification_error')->nullable()->after('notification_attempts');
            $table->timestamp('notified_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('canteen_parent_visibility_queue', function (Blueprint $table) {
            $table->dropColumn([
                'notification_status',
                'notification_attempts',
                'last_notification_error',
                'notified_at',
            ]);
        });
    }
};

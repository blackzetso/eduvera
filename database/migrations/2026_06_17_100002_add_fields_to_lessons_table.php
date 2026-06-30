<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            if (!Schema::hasColumn('lessons', 'strategies')) {
                $table->text('strategies')->nullable()->after('description');
            }
            if (!Schema::hasColumn('lessons', 'publish_date')) {
                $table->dateTime('publish_date')->nullable()->after('expire_date');
            }
            if (!Schema::hasColumn('lessons', 'lesson_message_template_id')) {
                $table->unsignedBigInteger('lesson_message_template_id')->nullable()->after('teacher_id');
                $table->foreign('lesson_message_template_id')
                      ->references('id')->on('lesson_message_templates')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            if (Schema::hasColumn('lessons', 'lesson_message_template_id')) {
                $table->dropForeign(['lesson_message_template_id']);
                $table->dropColumn('lesson_message_template_id');
            }
            if (Schema::hasColumn('lessons', 'publish_date')) {
                $table->dropColumn('publish_date');
            }
            if (Schema::hasColumn('lessons', 'strategies')) {
                $table->dropColumn('strategies');
            }
        });
    }
};

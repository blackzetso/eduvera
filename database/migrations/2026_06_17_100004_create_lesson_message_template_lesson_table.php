<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lesson_message_template_lesson')) {
            Schema::drop('lesson_message_template_lesson');
        }

        Schema::create('lesson_strategy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->unsignedBigInteger('lesson_message_template_id');
            $table->foreign('lesson_message_template_id', 'lesson_strategy_template_fk')
                ->references('id')->on('lesson_message_templates')->cascadeOnDelete();
            $table->unique(['lesson_id', 'lesson_message_template_id'], 'lesson_strategy_unique');
            $table->timestamps();
        });

        if (Schema::hasColumn('lessons', 'lesson_message_template_id')) {
            DB::table('lessons')
                ->whereNotNull('lesson_message_template_id')
                ->orderBy('id')
                ->each(function ($lesson) {
                    DB::table('lesson_strategy')->insertOrIgnore([
                        'lesson_id'                  => $lesson->id,
                        'lesson_message_template_id' => $lesson->lesson_message_template_id,
                        'created_at'                 => now(),
                        'updated_at'                 => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_strategy');
    }
};

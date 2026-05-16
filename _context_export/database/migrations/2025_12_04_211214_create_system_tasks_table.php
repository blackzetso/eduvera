<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_name')->unique(); // اسم المهمة (صفين فقط!)
            $table->timestamp('last_run_at')->nullable(); // آخر تنفيذ
            $table->timestamp('next_run_at')->nullable(); // التنفيذ القادم
            $table->integer('run_interval')->default(86400); // كل كام ساعة
            $table->boolean('is_enabled')->default(true); // مفعلة ولا لأ
            $table->text('last_result')->nullable(); // نتيجة آخر تنفيذ (JSON)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_tasks');
    }
};

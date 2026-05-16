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
        Schema::create('timetable_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained('timetables')->onDelete('cascade');
            $table->string('day_name'); // اسم اليوم (مثل: الأحد، الإثنين، أو أي اسم مخصص)
            $table->integer('day_order')->default(0); // ترتيب اليوم
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['timetable_id', 'day_name'], 'unique_day_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_days');
    }
};

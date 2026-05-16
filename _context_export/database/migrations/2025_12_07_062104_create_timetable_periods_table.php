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
        Schema::create('timetable_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained('timetables')->onDelete('cascade');
            $table->enum('day', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
            $table->integer('period_number');
            $table->time('time_from');
            $table->time('time_to');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['timetable_id', 'day', 'period_number', 'category_id'], 'unique_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_periods');
    }
};

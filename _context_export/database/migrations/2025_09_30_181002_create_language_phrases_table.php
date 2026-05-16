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
        Schema::create('language_phrases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained('languages')->onDelete('cascade');
            $table->string('group')->default('general');
            $table->string('key');
            $table->string('word')->nullable();
            $table->timestamps();
            $table->unique(['language_id', 'group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('language_phrases');
    }
};

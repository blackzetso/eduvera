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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->json('semesters')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status',['enable','disable'])->default('enable');
            $table->enum('expiry_period', ['lifetime', 'limited'])->default('lifetime');
            $table->date('expire_date')->nullable();
            $table->boolean('is_free')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('image')->nullable();
            $table->string('video_url')->nullable();
            $table->json('faqs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};

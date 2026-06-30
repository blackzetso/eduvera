<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dova_ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model', 64);
            $table->string('request_type', 32);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('portal', 32)->default('public');
            $table->string('role', 32)->default('guest');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question')->nullable();
            $table->boolean('success')->default(false);
            $table->boolean('used_fallback')->default(false);
            $table->timestamps();

            $table->index(['created_at', 'request_type']);
            $table->index(['portal', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dova_ai_usage_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')
                ->nullable()
                ->constrained('admission_applications')
                ->nullOnDelete();
            $table->string('type', 40);
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['admission_application_id', 'type']);
            $table->index(['admission_application_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_engagements');
    }
};

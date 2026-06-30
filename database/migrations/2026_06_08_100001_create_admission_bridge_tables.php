<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_bridge_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('form_submissions')->restrictOnDelete();
            $table->uuid('correlation_id');
            $table->foreignId('form_id')->constrained('forms')->restrictOnDelete();
            $table->string('binding_key', 80);
            $table->unsignedSmallInteger('mapped_form_version');
            $table->string('mapping_profile', 80);
            $table->string('status', 20);
            $table->string('outcome', 30)->nullable();
            $table->foreignId('admission_case_id')->nullable()->constrained('admission_applications')->nullOnDelete();
            $table->string('error_code', 80)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('submission_id');
            $table->index(['form_id', 'status']);
            $table->index('binding_key');
        });

        Schema::create('admission_case_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->restrictOnDelete();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->restrictOnDelete();
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->unique('form_submission_id');
            $table->index('admission_application_id');
        });

        Schema::create('admission_bridge_dead_letters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->uuid('correlation_id');
            $table->unsignedBigInteger('form_id');
            $table->string('binding_key', 80);
            $table->string('error_code', 80);
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->json('event_payload');
            $table->timestamp('failed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('submission_id');
            $table->index('correlation_id');
            $table->index('error_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_bridge_dead_letters');
        Schema::dropIfExists('admission_case_submissions');
        Schema::dropIfExists('admission_bridge_runs');
    }
};

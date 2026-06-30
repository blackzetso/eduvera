<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->uuid('application_group_id')->nullable();
            $table->string('pipeline_stage', 40);
            $table->string('status', 20)->default('open');
            $table->string('academic_year', 20);
            $table->foreignId('target_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('source_channel', 40);
            $table->string('source_reference')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal');
            $table->text('notes')->nullable();
            $table->foreignId('converted_student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['pipeline_stage', 'status']);
            $table->index('academic_year');
            $table->index('assigned_to_user_id');
        });

        Schema::create('admission_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('father_name')->nullable();
            $table->string('grandfather_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('current_grade_label')->nullable();
            $table->foreignId('existing_student_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('admission_application_id');
        });

        Schema::create('admission_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->foreignId('matched_guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('relationship_type', 30)->default('guardian');
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('is_pickup_authorized')->default(false);
            $table->boolean('is_financial_responsible')->default(false);
            $table->timestamps();

            $table->index('admission_application_id');
            $table->index('email');
            $table->index('phone');
            $table->index('national_id');
        });

        Schema::create('admission_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->string('scheduled_time', 20)->nullable();
            $table->string('status', 20)->default('requested');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('admission_application_id');
            $table->index('scheduled_date');
        });

        Schema::create('admission_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->string('from_stage', 40)->nullable();
            $table->string('to_stage', 40);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index('admission_application_id');
            $table->index('effective_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_stage_histories');
        Schema::dropIfExists('admission_visits');
        Schema::dropIfExists('admission_contacts');
        Schema::dropIfExists('admission_applicants');
        Schema::dropIfExists('admission_applications');
    }
};

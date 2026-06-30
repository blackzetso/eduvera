<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            $table->string('target_stage_label')->nullable()->after('current_grade_label');
            $table->foreignId('target_category_id')->nullable()->after('target_stage_label')->constrained('categories')->nullOnDelete();
            $table->text('notes')->nullable()->after('target_category_id');
        });

        Schema::table('admission_contacts', function (Blueprint $table) {
            $table->string('address')->nullable()->after('national_id');
            $table->json('communication_preferences')->nullable()->after('address');
        });

        Schema::table('admission_visits', function (Blueprint $table) {
            $table->string('outcome', 40)->nullable()->after('status');
            $table->string('attendance_status', 40)->nullable()->after('outcome');
            $table->text('follow_up_notes')->nullable()->after('notes');
        });

        Schema::create('admission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->string('document_key', 80);
            $table->string('label');
            $table->boolean('required')->default(true);
            $table->string('status', 20)->default('pending');
            $table->string('file_path')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['admission_application_id', 'document_key']);
            $table->index(['admission_application_id', 'status']);
        });

        Schema::create('admission_document_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_document_id')->constrained('admission_documents')->cascadeOnDelete();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('notes')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index('admission_application_id');
        });

        Schema::create('admission_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('visibility', 20)->default('internal');
            $table->text('content');
            $table->timestamps();

            $table->index('admission_application_id');
        });

        Schema::create('admission_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index('admission_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_assignment_histories');
        Schema::dropIfExists('admission_notes');
        Schema::dropIfExists('admission_document_histories');
        Schema::dropIfExists('admission_documents');

        Schema::table('admission_visits', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'attendance_status', 'follow_up_notes']);
        });

        Schema::table('admission_contacts', function (Blueprint $table) {
            $table->dropColumn(['address', 'communication_preferences']);
        });

        Schema::table('admission_applicants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_category_id');
            $table->dropColumn(['target_stage_label', 'notes']);
        });
    }
};

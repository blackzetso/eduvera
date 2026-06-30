<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait AdmissionBridgeTestSchema
{
    protected function ensureBridgeTestTables(): void
    {
        if (! Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->string('status')->default('enable');
                $table->string('publication_status')->default('draft');
                $table->unsignedSmallInteger('version')->default(2);
                $table->string('template_key')->nullable();
                $table->json('visibility_settings')->nullable();
                $table->json('submission_settings')->nullable();
                $table->json('workflow_definition')->nullable();
                $table->json('logic_rules')->nullable();
                $table->json('builder_settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status', 30)->default('submitted');
                $table->string('workflow_stage')->nullable();
                $table->json('data');
                $table->json('timeline')->nullable();
                $table->string('locale', 5)->default('ar');
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admission_applications')) {
            Schema::create('admission_applications', function (Blueprint $table) {
                $table->id();
                $table->string('reference_code')->unique();
                $table->uuid('application_group_id')->nullable();
                $table->string('pipeline_stage', 40);
                $table->string('status', 20)->default('open');
                $table->string('academic_year', 20);
                $table->unsignedBigInteger('target_category_id')->nullable();
                $table->string('source_channel', 40);
                $table->string('source_reference')->nullable();
                $table->unsignedBigInteger('assigned_to_user_id')->nullable();
                $table->string('priority', 20)->default('normal');
                $table->text('notes')->nullable();
                $table->string('decision', 20)->nullable();
                $table->timestamp('decision_at')->nullable();
                $table->unsignedBigInteger('decision_by_user_id')->nullable();
                $table->unsignedBigInteger('converted_student_id')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('converted_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admission_applicants')) {
            Schema::create('admission_applicants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admission_application_id');
                $table->string('first_name');
                $table->string('father_name')->nullable();
                $table->string('grandfather_name')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('gender', 20)->nullable();
                $table->string('national_id', 50)->nullable();
                $table->string('current_grade_label')->nullable();
                $table->unsignedBigInteger('existing_student_user_id')->nullable();
                $table->unsignedBigInteger('converted_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admission_contacts')) {
            Schema::create('admission_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admission_application_id');
                $table->unsignedBigInteger('matched_guardian_id')->nullable();
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
            });
        }

        if (! Schema::hasTable('admission_visits')) {
            Schema::create('admission_visits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admission_application_id');
                $table->date('scheduled_date')->nullable();
                $table->string('scheduled_time', 20)->nullable();
                $table->string('status', 20)->default('requested');
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admission_stage_histories')) {
            Schema::create('admission_stage_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admission_application_id');
                $table->string('from_stage', 40)->nullable();
                $table->string('to_stage', 40);
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('performed_by_user_id')->nullable();
                $table->timestamp('effective_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admission_bridge_runs')) {
            Schema::create('admission_bridge_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('submission_id');
                $table->uuid('correlation_id');
                $table->unsignedBigInteger('form_id');
                $table->string('binding_key', 80);
                $table->unsignedSmallInteger('mapped_form_version');
                $table->string('mapping_profile', 80);
                $table->string('status', 20);
                $table->string('outcome', 30)->nullable();
                $table->unsignedBigInteger('admission_case_id')->nullable();
                $table->string('error_code', 80)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique('submission_id');
            });
        }

        if (! Schema::hasTable('admission_case_submissions')) {
            Schema::create('admission_case_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admission_application_id');
                $table->unsignedBigInteger('form_submission_id');
                $table->uuid('correlation_id');
                $table->timestamps();

                $table->unique('form_submission_id');
            });
        }

        if (! Schema::hasTable('admission_bridge_dead_letters')) {
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
    }

    protected function truncateBridgeTestTables(): void
    {
        foreach ([
            'admission_bridge_dead_letters',
            'admission_bridge_runs',
            'admission_case_submissions',
            'admission_stage_histories',
            'admission_visits',
            'admission_contacts',
            'admission_applicants',
            'form_submissions',
            'admission_applications',
            'forms',
        ] as $table) {
            if (Schema::hasTable($table)) {
                \DB::table($table)->delete();
            }
        }
    }
}

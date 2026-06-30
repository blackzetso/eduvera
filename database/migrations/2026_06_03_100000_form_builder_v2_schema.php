<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            if (! Schema::hasColumn('forms', 'name_en')) {
                $table->string('name_en')->nullable()->after('name');
            }
            if (! Schema::hasColumn('forms', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('name_en');
            }
            if (! Schema::hasColumn('forms', 'description_en')) {
                $table->text('description_en')->nullable()->after('description_ar');
            }
            if (! Schema::hasColumn('forms', 'publication_status')) {
                $table->string('publication_status', 20)->default('draft')->after('status');
            }
            if (! Schema::hasColumn('forms', 'version')) {
                $table->unsignedSmallInteger('version')->default(2)->after('publication_status');
            }
            if (! Schema::hasColumn('forms', 'template_key')) {
                $table->string('template_key')->nullable()->after('version');
            }
            if (! Schema::hasColumn('forms', 'visibility_settings')) {
                $table->json('visibility_settings')->nullable()->after('template_key');
            }
            if (! Schema::hasColumn('forms', 'submission_settings')) {
                $table->json('submission_settings')->nullable()->after('visibility_settings');
            }
            if (! Schema::hasColumn('forms', 'workflow_definition')) {
                $table->json('workflow_definition')->nullable()->after('submission_settings');
            }
            if (! Schema::hasColumn('forms', 'logic_rules')) {
                $table->json('logic_rules')->nullable()->after('workflow_definition');
            }
            if (! Schema::hasColumn('forms', 'builder_settings')) {
                $table->json('builder_settings')->nullable()->after('logic_rules');
            }
        });

        if (! Schema::hasTable('form_sections')) {
            Schema::create('form_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained()->cascadeOnDelete();
                $table->string('title_ar');
                $table->string('title_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_collapsed')->default(false);
                $table->timestamps();
            });
        }

        Schema::table('form_inputs', function (Blueprint $table) {
            if (! Schema::hasColumn('form_inputs', 'section_id')) {
                $table->foreignId('section_id')->nullable()->after('form_id')->constrained('form_sections')->nullOnDelete();
            }
            if (! Schema::hasColumn('form_inputs', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('section_id');
            }
            if (! Schema::hasColumn('form_inputs', 'schema')) {
                $table->json('schema')->nullable()->after('options');
            }
        });

        if (! Schema::hasTable('form_templates')) {
            Schema::create('form_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name_ar');
                $table->string('name_en');
                $table->string('category', 50)->default('system');
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->json('definition');
                $table->boolean('is_system')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status', 30)->default('submitted');
                $table->string('workflow_stage')->nullable();
                $table->json('data');
                $table->json('timeline')->nullable();
                $table->string('locale', 5)->default('ar');
                $table->ipAddress('ip_address')->nullable();
                $table->timestamps();

                $table->index(['form_id', 'status']);
                $table->index(['form_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');

        Schema::table('form_inputs', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn(['section_id', 'sort_order', 'schema']);
        });

        Schema::dropIfExists('form_sections');
        Schema::dropIfExists('form_templates');

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn([
                'name_en',
                'description_ar',
                'description_en',
                'publication_status',
                'version',
                'template_key',
                'visibility_settings',
                'submission_settings',
                'workflow_definition',
                'logic_rules',
                'builder_settings',
            ]);
        });
    }
};

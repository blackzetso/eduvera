<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('website_landing_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('website_landing_pages')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('block_type', 64);
            $table->string('admin_name');
            $table->string('anchor_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->json('settings')->nullable();
            $table->json('content')->nullable();
            $table->boolean('show_desktop')->default(true);
            $table->boolean('show_tablet')->default(true);
            $table->boolean('show_mobile')->default(true);
            $table->timestamp('scheduled_starts_at')->nullable();
            $table->timestamp('scheduled_ends_at')->nullable();
            $table->foreignId('duplicated_from_id')->nullable()->constrained('website_landing_sections')->nullOnDelete();
            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
            $table->index(['page_id', 'block_type']);
        });

        Schema::create('website_landing_section_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('website_landing_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 32);
            $table->json('snapshot');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_landing_section_revisions');
        Schema::dropIfExists('website_landing_sections');
        Schema::dropIfExists('website_landing_pages');
    }
};

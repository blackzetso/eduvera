<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('alt')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('website_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('text');
            $table->string('href')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_nav_links', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('href');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_stages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('age_range')->nullable();
            $table->string('tagline')->nullable();
            $table->string('tone')->nullable();
            $table->unsignedInteger('student_count')->nullable();
            $table->unsignedSmallInteger('class_size')->nullable();
            $table->json('key_skills')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->string('image_src')->nullable();
            $table->string('image_alt')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('icon')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('benefit')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->string('image_src')->nullable();
            $table->string('image_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('slug')->nullable();
            $table->string('title');
            $table->string('type')->nullable();
            $table->string('date')->nullable();
            $table->string('date_short')->nullable();
            $table->string('audience')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('cta')->nullable();
            $table->string('href')->nullable();
            $table->boolean('is_open_day')->default(false);
            $table->string('limited_seats_label')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->string('image_src')->nullable();
            $table->string('image_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('news')->index();
            $table->string('external_id')->nullable()->index();
            $table->string('slug')->nullable();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('published_at')->nullable();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->foreignId('image_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->string('image_src')->nullable();
            $table->string('image_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('name');
            $table->string('role');
            $table->string('role_type')->nullable();
            $table->text('quote');
            $table->foreignId('photo_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->string('photo_src')->nullable();
            $table->string('photo_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_success_stories', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('student_name');
            $table->string('achievement')->nullable();
            $table->string('category')->nullable();
            $table->text('story')->nullable();
            $table->string('stat_value')->nullable();
            $table->string('stat_label')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->string('image_src')->nullable();
            $table->string('image_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_careers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('department')->nullable();
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->string('apply_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('category');
            $table->foreignId('image_media_id')->nullable()->constrained('website_media')->nullOnDelete();
            $table->string('src');
            $table->string('alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_gallery_items');
        Schema::dropIfExists('website_careers');
        Schema::dropIfExists('website_success_stories');
        Schema::dropIfExists('website_testimonials');
        Schema::dropIfExists('website_posts');
        Schema::dropIfExists('website_events');
        Schema::dropIfExists('website_facilities');
        Schema::dropIfExists('website_stages');
        Schema::dropIfExists('website_nav_links');
        Schema::dropIfExists('website_announcements');
        Schema::dropIfExists('website_settings');
        Schema::dropIfExists('website_media');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_posts', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('summary_ar')->nullable()->after('summary');
            $table->longText('content_ar')->nullable()->after('content');
        });

        Schema::table('website_nav_links', function (Blueprint $table) {
            $table->string('label_ar')->nullable()->after('label');
        });

        Schema::table('website_announcements', function (Blueprint $table) {
            $table->string('text_ar')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('website_posts', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'summary_ar', 'content_ar']);
        });

        Schema::table('website_nav_links', function (Blueprint $table) {
            $table->dropColumn('label_ar');
        });

        Schema::table('website_announcements', function (Blueprint $table) {
            $table->dropColumn('text_ar');
        });
    }
};

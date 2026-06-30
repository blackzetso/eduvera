<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_documents', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('file_path');
            $table->string('mime_type', 120)->nullable()->after('original_filename');
            $table->unsignedInteger('file_size')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('admission_documents', function (Blueprint $table) {
            $table->dropColumn(['original_filename', 'mime_type', 'file_size']);
        });
    }
};

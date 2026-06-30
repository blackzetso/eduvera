<?php

// Excel/CSV bulk attendance import batches (validate then confirm).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_path', 500);
            $table->string('original_file_name')->nullable();
            $table->enum('scope_type', ['school', 'category', 'timetable_period', 'custom']);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('attendance_date')->nullable();
            $table->string('session_type', 50)->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->enum('status', [
                'pending_validation',
                'validated',
                'importing',
                'imported',
                'failed',
                'rolled_back',
            ])->default('pending_validation');
            $table->json('validation_errors_json')->nullable();
            $table->json('parsed_data_json')->nullable();
            $table->text('error_summary')->nullable();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_batches');
    }
};

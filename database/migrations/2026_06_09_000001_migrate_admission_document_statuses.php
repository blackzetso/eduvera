<?php

use App\Support\Admission\AdmissionDocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admission_documents')
            ->where('status', 'submitted')
            ->update(['status' => AdmissionDocumentStatus::REVIEW_PENDING]);

        DB::table('admission_documents')
            ->where('status', 'missing')
            ->update(['status' => AdmissionDocumentStatus::NEEDS_UPLOAD]);

        DB::table('admission_documents')
            ->where('status', 'pending')
            ->whereNull('file_path')
            ->update(['status' => AdmissionDocumentStatus::NEEDS_UPLOAD]);

        DB::table('admission_documents')
            ->where('status', 'pending')
            ->whereNotNull('file_path')
            ->update(['status' => AdmissionDocumentStatus::REVIEW_PENDING]);
    }

    public function down(): void
    {
        DB::table('admission_documents')
            ->where('status', AdmissionDocumentStatus::NEEDS_UPLOAD)
            ->update(['status' => 'pending']);

        DB::table('admission_documents')
            ->where('status', AdmissionDocumentStatus::REVIEW_PENDING)
            ->update(['status' => 'submitted']);

        DB::table('admission_documents')
            ->where('status', AdmissionDocumentStatus::REUPLOAD_REQUIRED)
            ->update(['status' => 'submitted']);
    }
};

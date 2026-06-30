<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('form_inputs', 'label_en')) {
            Schema::table('form_inputs', function (Blueprint $table) {
                $table->string('label_en')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('form_inputs', function (Blueprint $table) {
            $table->dropColumn('label_en');
        });
    }
};

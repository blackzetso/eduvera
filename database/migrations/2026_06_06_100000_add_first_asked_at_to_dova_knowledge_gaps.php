<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dova_knowledge_gaps', function (Blueprint $table) {
            $table->timestamp('first_asked_at')->nullable()->after('last_asked_at');
        });

        DB::table('dova_knowledge_gaps')
            ->whereNull('first_asked_at')
            ->update(['first_asked_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('dova_knowledge_gaps', function (Blueprint $table) {
            $table->dropColumn('first_asked_at');
        });
    }
};

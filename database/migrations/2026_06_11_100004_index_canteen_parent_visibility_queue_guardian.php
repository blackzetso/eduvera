<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canteen_parent_visibility_queue', function (Blueprint $table) {
            $table->index(['guardian_id_ref', 'visibility_status'], 'canteen_parent_vis_guardian_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('canteen_parent_visibility_queue', function (Blueprint $table) {
            $table->dropIndex('canteen_parent_vis_guardian_status_idx');
        });
    }
};

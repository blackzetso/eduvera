<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('admin','student','teacher','guardian','control_staff','social_worker','nurse','department_head') NOT NULL DEFAULT 'student'");
    }

    public function down(): void
    {
        $count = DB::table('users')->where('user_type', 'department_head')->count();
        if ($count > 0) {
            throw new \RuntimeException("Cannot rollback: {$count} department_head user(s) exist.");
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('admin','student','teacher','guardian','control_staff','social_worker','nurse') NOT NULL DEFAULT 'student'");
    }
};

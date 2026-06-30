<?php

// Extend users.user_type enum with control_staff, social_worker, and nurse.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('admin','student','teacher','guardian','control_staff','social_worker','nurse') NOT NULL DEFAULT 'student'");
    }

    public function down(): void
    {
        $count = DB::table('users')
            ->whereIn('user_type', ['control_staff', 'social_worker', 'nurse'])
            ->count();

        if ($count > 0) {
            throw new \RuntimeException(
                "Cannot rollback user_type enum: {$count} user(s) have control_staff, social_worker, or nurse. / "
                ."لا يمكن التراجع: يوجد مستخدمون بأدوار control_staff أو social_worker أو nurse."
            );
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('admin','student','teacher','guardian') NOT NULL DEFAULT 'student'");
    }
};

<?php

namespace Database\Seeders;

use App\Services\TeacherAbsenceDemoService;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية لتفعيل «مركز تغطية الغياب اليومية» على /admin/timetable
 *
 * تشغيل:
 *   php artisan db:seed --class=TeacherAbsenceDemoSeeder
 */
class TeacherAbsenceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(TeacherAbsenceDemoService::class);

        if ($service->hasDemoDataForToday()) {
            $this->command?->warn('بيانات تجريبية موجودة مسبقاً لهذا اليوم.');

            return;
        }

        $result = $service->seedForToday();

        if (! ($result['success'] ?? false)) {
            $this->command?->warn($result['message'] ?? 'تعذر إنشاء البيانات التجريبية.');

            return;
        }

        $preview = $result['preview'] ?? [];
        $this->command?->info($result['message']);
        $this->command?->table(
            ['المعلم', 'الحالة', 'سبب', 'حصص متأثرة اليوم'],
            collect($preview['absent_teachers'] ?? [])->map(fn ($t) => [
                $t['name'],
                $t['status_label'] ?? $t['status'],
                $t['reason'] ?? '—',
                $t['affected_count'] ?? 0,
            ])->all()
        );
        $this->command?->line('افتح: /admin/timetable → تغطية الغياب اليومية');
    }
}

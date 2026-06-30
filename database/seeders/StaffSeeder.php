<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Database\Seeders\Concerns\SeedsEgyptianIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
  use SeedsEgyptianIdentity;

  public function run(): void
  {
    $subjects = Subject::all();
    $teacherNames = [
      'أ. محمد عبدالله السيد',
      'أ. أحمد محمود حسن',
      'أ. محمود علي إبراهيم',
      'أ. خالد يوسف أحمد',
      'أ. سامي طارق محمود',
      'أ. هشام رامي علي',
      'أ. عمرو باسم حسين',
      'أ. وليد كريم فتحي',
      'أ. نادية سمير عبدالرحمن',
      'أ. هبة إبراهيم خليل',
    ];

    $teachers = [];

    foreach ($teacherNames as $i => $teacherName) {
      $teachers[] = User::create([
        'name' => $teacherName,
        'email' => 'teacher' . ($i + 1) . '@eduvera.test',
        'password' => Hash::make(self::DEMO_PASSWORD),
        'user_type' => 'teacher',
        'phone' => $this->egyptianPhone(100 + $i),
        'national_id' => $this->uniqueNationalId(100 + $i, 1980 + ($i % 15)),
        'email_verified_at' => now(),
      ]);
    }

    $demoTeacher = User::create([
      'name' => 'أ. كريم مصطفى نبيل',
      'email' => 'teacher.demo@eduvera.test',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'teacher',
      'phone' => '01098765432',
      'national_id' => $this->uniqueNationalId(200, 1985),
      'email_verified_at' => now(),
    ]);
    $teachers[] = $demoTeacher;

    foreach ($teachers as $index => $teacher) {
      $subjectIds = $subjects->random(min(3, $subjects->count()))->pluck('id');
      $teacher->subjects()->syncWithoutDetaching($subjectIds->all());
    }

    User::create([
      'name' => 'موظف الكنترول',
      'email' => 'control@eduvera.test',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'control_staff',
      'phone' => $this->egyptianPhone(300),
      'department' => 'الشؤون التعليمية',
      'job_title' => 'موظف كنترول',
      'email_verified_at' => now(),
    ]);

    User::create([
      'name' => 'الأخصائية الاجتماعية',
      'email' => 'social@eduvera.test',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'social_worker',
      'phone' => $this->egyptianPhone(301),
      'department' => 'الإرشاد الطلابي',
      'job_title' => 'أخصائية اجتماعية',
      'email_verified_at' => now(),
    ]);

    User::create([
      'name' => 'الممرضة المدرسية',
      'email' => 'nurse@eduvera.test',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'nurse',
      'phone' => $this->egyptianPhone(302),
      'department' => 'العيادة المدرسية',
      'job_title' => 'ممرضة',
      'email_verified_at' => now(),
    ]);
  }
}

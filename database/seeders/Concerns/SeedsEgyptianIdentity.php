<?php

namespace Database\Seeders\Concerns;

trait SeedsEgyptianIdentity
{
  protected array $maleFirstNames = [
    'محمد', 'أحمد', 'محمود', 'علي', 'حسن', 'حسين', 'يوسف', 'إبراهيم', 'عمر', 'خالد',
    'عبدالله', 'مصطفى', 'كريم', 'طارق', 'سامي', 'رامي', 'وليد', 'هشام', 'عمرو', 'باسم',
  ];

  protected array $femaleFirstNames = [
    'فاطمة', 'مريم', 'نور', 'سارة', 'آية', 'هدى', 'ياسمين', 'منى', 'دينا', 'رانيا',
    'سلمى', 'لمياء', 'هبة', 'إيمان', 'شيماء', 'نادية', 'أمل', 'سمية', 'ريم', 'مها',
  ];

  protected array $fatherNames = [
    'أحمد', 'محمد', 'علي', 'حسن', 'محمود', 'سيد', 'عبدالرحمن', 'خالد', 'إبراهيم', 'عمر',
    'يوسف', 'طارق', 'سامي', 'كريم', 'مصطفى', 'فتحي', 'رمضان', 'جمال', 'نبيل', 'عادل',
  ];

  protected array $grandfatherNames = [
    'حسن', 'علي', 'محمد', 'أحمد', 'إبراهيم', 'عبدالله', 'سيد', 'محمود', 'خليل', 'سعد',
    'فوزي', 'عبدالعزيز', 'رضا', 'صالح', 'منصور', 'حسين', 'عثمان', 'زكي', 'فاروق', 'ناصر',
  ];

  protected array $usedNationalIds = [];

  public const DEMO_GUARDIAN_NATIONAL_ID = '29309208800736';

  public const DEMO_PASSWORD = '12345678';

  protected function egyptianNationalId(int $birthYear, int $birthMonth, int $birthDay, int $sequence): string
  {
    $century = $birthYear >= 2000 ? 3 : 2;
    $yy = substr((string) $birthYear, -2);
    $mm = str_pad((string) $birthMonth, 2, '0', STR_PAD_LEFT);
    $dd = str_pad((string) $birthDay, 2, '0', STR_PAD_LEFT);
    $gov = '09';
    $seq = str_pad((string) ($sequence % 10000), 4, '0', STR_PAD_LEFT);
    $base = "{$century}{$yy}{$mm}{$dd}{$gov}{$seq}";
    $check = (int) substr($base, -1) ^ ($sequence % 10);

    return $base . $check;
  }

  protected function uniqueNationalId(int $index, ?int $birthYear = null): string
  {
    $year = $birthYear ?? (1975 + ($index % 25));
    $month = ($index % 12) + 1;
    $day = ($index % 28) + 1;
    $attempt = 0;

    do {
      $id = $this->egyptianNationalId($year, $month, $day, $index * 100 + $attempt);
      $attempt++;
    } while (in_array($id, $this->usedNationalIds, true));

    $this->usedNationalIds[] = $id;

    return $id;
  }

  protected function egyptianPhone(int $index): string
  {
    $prefixes = ['010', '011', '012', '015'];
    $prefix = $prefixes[$index % count($prefixes)];

    return $prefix . str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT);
  }

  protected function studentCode(int $number): string
  {
    return 'STU-2025-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
  }

  protected function randomEgyptianName(string $gender, int $index): array
  {
    $first = $gender === 'female'
      ? $this->femaleFirstNames[$index % count($this->femaleFirstNames)]
      : $this->maleFirstNames[$index % count($this->maleFirstNames)];
    $father = $this->fatherNames[($index + 3) % count($this->fatherNames)];
    $grandfather = $this->grandfatherNames[($index + 7) % count($this->grandfatherNames)];

    return [
      'first_name' => $first,
      'father_name' => $father,
      'grandfather_name' => $grandfather,
      'name' => "{$first} {$father} {$grandfather}",
    ];
  }

  protected function studentBirthDate(int $index, string $gradeHint): string
  {
    $offsets = [
      'أولى إعدادي' => 13,
      'تانية إعدادي' => 14,
      'تالتة إعدادي' => 15,
      'أولى ثانوي' => 16,
      'تانية ثانوي' => 17,
      'تالتة ثانوي' => 18,
    ];

    $age = 14;
    foreach ($offsets as $key => $years) {
      if (str_contains($gradeHint, $key)) {
        $age = $years;
        break;
      }
    }

    $year = (int) date('Y') - $age;
    $month = ($index % 12) + 1;
    $day = ($index % 28) + 1;

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }
}

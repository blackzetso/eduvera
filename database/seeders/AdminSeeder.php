<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\SeedsEgyptianIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
  use SeedsEgyptianIdentity;

  public function run(): void
  {
    User::create([
      'name' => 'super admin',
      'email' => 'admin@example.com',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'admin',
      'role' => 'admin',
      'email_verified_at' => '2023-12-30 10:04:21',
    ]);

    User::create([
      'name' => 'Student Account',
      'email' => 'student@example.com',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'student',
      'email_verified_at' => '2023-12-30 10:04:21',
    ]);

    User::create([
      'name' => 'Guardian Account',
      'email' => 'guardian@example.com',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'guardian',
      'phone' => '01012345679',
      'national_id' => self::DEMO_GUARDIAN_NATIONAL_ID,
      'email_verified_at' => '2023-12-30 10:04:21',
    ]);

    User::create([
      'name' => 'Teacher Account',
      'email' => 'teacher@example.com',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'teacher',
      'email_verified_at' => '2023-12-30 10:04:21',
    ]);
  }
}

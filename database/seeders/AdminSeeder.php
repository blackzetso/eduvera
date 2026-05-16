<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name'      => 'super admin',
            'email'     => 'admin@example.com',
            'password'  =>  Hash::make('12345678'),
            'user_type' => 'admin',
            'role'      => 'admin',
            'email_verified_at' => '2023-12-30 10:04:21'
        ]);

        User::create([
            'name'      => 'Student Account',
            'email'     => 'student@example.com',
            'password'  =>  Hash::make('12345678'),
            'user_type' => 'student',
            'email_verified_at' => '2023-12-30 10:04:21'
        ]);

        User::create([
            'name'      => 'Guardian Account',
            'email'     => 'guardian@example.com',
            'password'  =>  Hash::make('12345678'),
            'user_type' => 'guardian',
            'email_verified_at' => '2023-12-30 10:04:21'
        ]);

        User::create([
            'name'      => 'Teacher Account',
            'email'     => 'teacher@example.com',
            'password'  =>  Hash::make('12345678'),
            'user_type' => 'teacher',
            'email_verified_at' => '2023-12-30 10:04:21'
        ]);

    }
}

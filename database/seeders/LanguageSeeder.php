<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\LanguagePhrase;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Seed languages and basic phrases for tenant database.
     */
    public function run(): void
    {
        // 1. إنشاء اللغة العربية (افتراضية)
        $arabic = Language::create([
            'code' => 'ar',
            'name' => 'العربية',
            'status' => 'enabled',
            'is_default' => true,
        ]);

        // 2. إنشاء اللغة الإنجليزية
        $english = Language::create([
            'code' => 'en',
            'name' => 'English',
            'status' => 'enabled',
            'is_default' => false,
        ]);

        // 3. إنشاء بعض الـ phrases الأساسية للعربية
        $arabicPhrases = [
            ['key' => 'welcome', 'word' => 'مرحباً', 'group' => 'general'],
            ['key' => 'dashboard', 'word' => 'لوحة التحكم', 'group' => 'general'],
            ['key' => 'lessons', 'word' => 'الدروس', 'group' => 'general'],
            ['key' => 'all_lessons', 'word' => 'جميع الدروس', 'group' => 'general'],
            ['key' => 'lectures', 'word' => 'المحاضرات', 'group' => 'general'],
            ['key' => 'files', 'word' => 'الملفات', 'group' => 'general'],
            ['key' => 'students', 'word' => 'الطلاب', 'group' => 'general'],
            ['key' => 'teachers', 'word' => 'المعلمين', 'group' => 'general'],
            ['key' => 'categories', 'word' => 'الفئات', 'group' => 'general'],
            ['key' => 'subjects', 'word' => 'المواد الدراسية', 'group' => 'general'],
            ['key' => 'timetable', 'word' => 'الجدول الدراسي', 'group' => 'general'],
            ['key' => 'design_timetable', 'word' => 'تصميم الجدول', 'group' => 'general'],
            ['key' => 'view_timetable', 'word' => 'عرض الجدول وتعيين المدرسين', 'group' => 'general'],
            ['key' => 'settings', 'word' => 'الإعدادات', 'group' => 'general'],
            ['key' => 'wallet', 'word' => 'محفظة التخزين', 'group' => 'general'],
            ['key' => 'form_builder', 'word' => 'منشئ النماذج', 'group' => 'general'],
            ['key' => 'save', 'word' => 'حفظ', 'group' => 'general'],
            ['key' => 'cancel', 'word' => 'إلغاء', 'group' => 'general'],
            ['key' => 'delete', 'word' => 'حذف', 'group' => 'general'],
            ['key' => 'edit', 'word' => 'تعديل', 'group' => 'general'],
            ['key' => 'add', 'word' => 'إضافة', 'group' => 'general'],
            ['key' => 'search', 'word' => 'بحث', 'group' => 'general'],
            ['key' => 'name', 'word' => 'الاسم', 'group' => 'general'],
            ['key' => 'email', 'word' => 'البريد الإلكتروني', 'group' => 'general'],
            ['key' => 'password', 'word' => 'كلمة المرور', 'group' => 'general'],
            ['key' => 'login', 'word' => 'تسجيل الدخول', 'group' => 'general'],
            ['key' => 'logout', 'word' => 'تسجيل الخروج', 'group' => 'general'],
            ['key' => 'register', 'word' => 'التسجيل', 'group' => 'general'],
            ['key' => 'profile', 'word' => 'الملف الشخصي', 'group' => 'general'],
        ];

        foreach ($arabicPhrases as $phrase) {
            LanguagePhrase::create([
                'language_id' => $arabic->id,
                'key' => $phrase['key'],
                'word' => $phrase['word'],
                'group' => $phrase['group'],
            ]);
        }

        // 4. إنشاء نفس الـ phrases للإنجليزية
        $englishPhrases = [
            ['key' => 'welcome', 'word' => 'Welcome', 'group' => 'general'],
            ['key' => 'dashboard', 'word' => 'Dashboard', 'group' => 'general'],
            ['key' => 'lessons', 'word' => 'Lessons', 'group' => 'general'],
            ['key' => 'all_lessons', 'word' => 'All Lessons', 'group' => 'general'],
            ['key' => 'lectures', 'word' => 'Lectures', 'group' => 'general'],
            ['key' => 'files', 'word' => 'Files', 'group' => 'general'],
            ['key' => 'students', 'word' => 'Students', 'group' => 'general'],
            ['key' => 'teachers', 'word' => 'Teachers', 'group' => 'general'],
            ['key' => 'categories', 'word' => 'Categories', 'group' => 'general'],
            ['key' => 'subjects', 'word' => 'Subjects', 'group' => 'general'],
            ['key' => 'timetable', 'word' => 'Timetable', 'group' => 'general'],
            ['key' => 'design_timetable', 'word' => 'Design Timetable', 'group' => 'general'],
            ['key' => 'view_timetable', 'word' => 'View Timetable & Assign Teachers', 'group' => 'general'],
            ['key' => 'settings', 'word' => 'Settings', 'group' => 'general'],
            ['key' => 'wallet', 'word' => 'Storage Wallet', 'group' => 'general'],
            ['key' => 'form_builder', 'word' => 'Form Builder', 'group' => 'general'],
            ['key' => 'save', 'word' => 'Save', 'group' => 'general'],
            ['key' => 'cancel', 'word' => 'Cancel', 'group' => 'general'],
            ['key' => 'delete', 'word' => 'Delete', 'group' => 'general'],
            ['key' => 'edit', 'word' => 'Edit', 'group' => 'general'],
            ['key' => 'add', 'word' => 'Add', 'group' => 'general'],
            ['key' => 'search', 'word' => 'Search', 'group' => 'general'],
            ['key' => 'name', 'word' => 'Name', 'group' => 'general'],
            ['key' => 'email', 'word' => 'Email', 'group' => 'general'],
            ['key' => 'password', 'word' => 'Password', 'group' => 'general'],
            ['key' => 'login', 'word' => 'Login', 'group' => 'general'],
            ['key' => 'logout', 'word' => 'Logout', 'group' => 'general'],
            ['key' => 'register', 'word' => 'Register', 'group' => 'general'],
            ['key' => 'profile', 'word' => 'Profile', 'group' => 'general'],
        ];

        foreach ($englishPhrases as $phrase) {
            LanguagePhrase::create([
                'language_id' => $english->id,
                'key' => $phrase['key'],
                'word' => $phrase['word'],
                'group' => $phrase['group'],
            ]);
        }
    }
}


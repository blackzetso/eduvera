<?php

namespace Database\Seeders;

use App\Models\FormTemplate;
use Illuminate\Database\Seeder;

class FormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            $this->template('student_admission', 'تسجيل طالب', 'Student Admission', $this->studentAdmission()),
            $this->template('employee_admission', 'تسجيل موظف', 'Employee Admission', $this->employeeAdmission()),
            $this->template('parent_registration', 'تسجيل ولي أمر', 'Parent Registration', $this->parentRegistration()),
            $this->template('teacher_application', 'طلب تعيين معلم', 'Teacher Application', $this->teacherApplication()),
            $this->template('leave_request', 'طلب إجازة', 'Leave Request', $this->leaveRequest()),
            $this->template('purchase_request', 'طلب شراء', 'Purchase Request', $this->purchaseRequest()),
            $this->template('maintenance_request', 'طلب صيانة', 'Maintenance Request', $this->maintenanceRequest()),
            $this->template('visitor_registration', 'تسجيل زائر', 'Visitor Registration', $this->visitorRegistration()),
            $this->template('complaint_form', 'نموذج شكوى', 'Complaint Form', $this->complaintForm()),
        ];

        foreach ($templates as $template) {
            FormTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }

    protected function template(string $key, string $nameAr, string $nameEn, array $definition): array
    {
        return [
            'key' => $key,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'category' => 'system',
            'description_ar' => $definition['description_ar'] ?? null,
            'description_en' => $definition['description_en'] ?? null,
            'definition' => $definition,
            'is_system' => true,
        ];
    }

    protected function section(string $titleAr, string $titleEn, array $fields, ?string $descAr = null, ?string $descEn = null): array
    {
        return [
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'description_ar' => $descAr,
            'description_en' => $descEn,
            'fields' => $fields,
        ];
    }

    protected function field(string $ar, string $en, string $type, bool $required = false, array $options = []): array
    {
        return [
            'name_ar' => $ar,
            'name_en' => $en,
            'type' => $type,
            'required' => $required,
            'options' => $options,
            'schema' => [
                'label_ar' => $ar,
                'label_en' => $en,
                'validation' => ['required' => $required],
                'visibility' => ['mode' => 'visible'],
            ],
        ];
    }

    protected function studentAdmission(): array
    {
        return [
            'name_ar' => 'نموذج تسجيل طالب',
            'name_en' => 'Student Admission Form',
            'sections' => [
                $this->section('البيانات الشخصية', 'Personal Information', [
                    $this->field('الاسم الرباعي', 'Full Name', 'text', true),
                    $this->field('الجنس', 'Gender', 'select', true, [
                        ['value' => 'male', 'label_ar' => 'ذكر', 'label_en' => 'Male'],
                        ['value' => 'female', 'label_ar' => 'أنثى', 'label_en' => 'Female'],
                    ]),
                    $this->field('تاريخ الميلاد', 'Birth Date', 'date', true),
                ]),
                $this->section('بيانات التواصل', 'Contact Information', [
                    $this->field('رقم الجوال', 'Mobile', 'phone', true),
                    $this->field('البريد الإلكتروني', 'Email', 'email'),
                ]),
                $this->section('البيانات الأكاديمية', 'Academic Information', [
                    $this->field('الصف الدراسي', 'Grade', 'grade', true),
                    $this->field('الفصل', 'Class', 'class'),
                ]),
            ],
        ];
    }

    protected function employeeAdmission(): array
    {
        return [
            'name_ar' => 'نموذج تسجيل موظف',
            'name_en' => 'Employee Admission Form',
            'sections' => [
                $this->section('البيانات الشخصية', 'Personal Information', [
                    $this->field('الاسم الكامل', 'Full Name', 'text', true),
                    $this->field('نوع الموظف', 'Employee Type', 'select', true, [
                        ['value' => 'teacher', 'label_ar' => 'معلم', 'label_en' => 'Teacher'],
                        ['value' => 'admin', 'label_ar' => 'إداري', 'label_en' => 'Administrative'],
                    ]),
                ]),
                $this->section('المؤهلات', 'Qualifications', [
                    $this->field('المؤهل العلمي', 'Degree', 'text'),
                    $this->field('سنوات الخبرة', 'Experience Years', 'number'),
                ]),
            ],
        ];
    }

    protected function parentRegistration(): array
    {
        return [
            'name_ar' => 'تسجيل ولي أمر',
            'name_en' => 'Parent Registration',
            'sections' => [
                $this->section('بيانات ولي الأمر', 'Guardian Information', [
                    $this->field('اسم ولي الأمر', 'Guardian Name', 'text', true),
                    $this->field('رقم الجوال', 'Mobile', 'phone', true),
                    $this->field('البريد الإلكتروني', 'Email', 'email'),
                ]),
            ],
        ];
    }

    protected function teacherApplication(): array
    {
        return [
            'name_ar' => 'طلب تعيين معلم',
            'name_en' => 'Teacher Application',
            'sections' => [
                $this->section('البيانات الأساسية', 'Basic Information', [
                    $this->field('الاسم', 'Name', 'text', true),
                    $this->field('التخصص', 'Subject', 'subject', true),
                ]),
            ],
        ];
    }

    protected function leaveRequest(): array
    {
        return [
            'name_ar' => 'طلب إجازة',
            'name_en' => 'Leave Request',
            'sections' => [
                $this->section('تفاصيل الإجازة', 'Leave Details', [
                    $this->field('نوع الإجازة', 'Leave Type', 'select', true),
                    $this->field('من تاريخ', 'From Date', 'date', true),
                    $this->field('إلى تاريخ', 'To Date', 'date', true),
                ]),
            ],
        ];
    }

    protected function purchaseRequest(): array
    {
        return [
            'name_ar' => 'طلب شراء',
            'name_en' => 'Purchase Request',
            'sections' => [
                $this->section('تفاصيل الطلب', 'Request Details', [
                    $this->field('وصف الصنف', 'Item Description', 'textarea', true),
                    $this->field('التكلفة التقديرية', 'Estimated Cost', 'number'),
                ]),
            ],
        ];
    }

    protected function maintenanceRequest(): array
    {
        return [
            'name_ar' => 'طلب صيانة',
            'name_en' => 'Maintenance Request',
            'sections' => [
                $this->section('تفاصيل الصيانة', 'Maintenance Details', [
                    $this->field('الموقع', 'Location', 'text', true),
                    $this->field('وصف المشكلة', 'Issue Description', 'textarea', true),
                ]),
            ],
        ];
    }

    protected function visitorRegistration(): array
    {
        return [
            'name_ar' => 'تسجيل زائر',
            'name_en' => 'Visitor Registration',
            'sections' => [
                $this->section('بيانات الزائر', 'Visitor Information', [
                    $this->field('اسم الزائر', 'Visitor Name', 'text', true),
                    $this->field('وقت الزيارة', 'Visit Time', 'time'),
                ]),
            ],
        ];
    }

    protected function complaintForm(): array
    {
        return [
            'name_ar' => 'نموذج شكوى',
            'name_en' => 'Complaint Form',
            'sections' => [
                $this->section('تفاصيل الشكوى', 'Complaint Details', [
                    $this->field('موضوع الشكوى', 'Subject', 'text', true),
                    $this->field('التفاصيل', 'Details', 'textarea', true),
                    $this->field('التقييم', 'Rating', 'rating'),
                ]),
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\admin;

use Inertia\Inertia;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\StudentEnrollment;
use App\Services\StudentCodeService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentGuardianService;
use App\Services\StudentProfileService;
use App\Services\StudentStatusService;
use App\Support\Student\GuardianRelationship;
use App\Support\Student\StudentStatus;

class StudentController extends Controller
{
    public function __construct(
        protected StudentProfileService $studentProfile,
        protected StudentCodeService $studentCodes,
        protected StudentEnrollmentService $studentEnrollments,
        protected StudentStatusService $studentStatus,
        protected StudentGuardianService $studentGuardians,
    ) {}

    /**
     * Display a listing of students.
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->where('user_type', 'student')
            ->with(['category', 'currentStudentEnrollment']);

        if ($status = $request->input('status')) {
            $query->where('student_status', $status);
        }

        if ($academicYear = $request->input('academic_year')) {
            $query->whereHas('currentStudentEnrollment', fn ($q) => $q->where('academic_year', $academicYear));
        }

        if ($stageId = $request->input('stage_id')) {
            $query->where(function ($q) use ($stageId) {
                $q->whereHas('currentStudentEnrollment', fn ($en) => $en->where('stage_category_id', (int) $stageId))
                    ->orWhereHas('category', function ($cat) use ($stageId) {
                        $cat->where('id', (int) $stageId)
                            ->orWhere('parent_id', (int) $stageId);
                    });
            });
        }

        $students = $query->orderByDesc('created_at')->get();

        $categories = Category::whereNull('parent_id')->with('children')->get();

        return Inertia::render('Admin/theme1/Students/Index', [
            'students' => $students,
            'categories' => $categories,
            'filters' => [
                'status' => $request->input('status', ''),
                'academic_year' => $request->input('academic_year', ''),
                'stage_id' => $request->input('stage_id', ''),
            ],
            'filterOptions' => [
                'statuses' => StudentStatus::options(),
                'academic_years' => StudentEnrollment::query()
                    ->where('is_current', true)
                    ->distinct()
                    ->orderByDesc('academic_year')
                    ->pluck('academic_year')
                    ->values(),
                'stages' => Category::query()
                    ->whereNull('parent_id')
                    ->where('status', 'enable')
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        $categories = $this->categoryTreeForForms();
        $parents = User::where('user_type', 'guardian')
            ->orderBy('name')
            ->get(['id', 'name', 'national_id', 'email']);

        return Inertia::render('Admin/theme1/Students/Create', [
            'categories' => $categories,
            'parents'    => $parents,
            'statusOptions' => StudentStatus::options(),
            'relationshipTypeOptions' => GuardianRelationship::typeOptions(),
        ]);
    }

    /**
     * Show bulk data page for students.
     */
    public function bulkData()
    {
        $allCategories = Category::where('status', 'enable')->get(['id', 'name', 'parent_id']);
        $byParent = $allCategories->groupBy('parent_id');

        $flattened = [];

        $walk = function ($parentId, $prefix = '') use (&$walk, &$flattened, $byParent) {
            foreach ($byParent->get($parentId, collect()) as $cat) {
                $path = $prefix ? ($prefix . ' / ' . $cat->name) : $cat->name;
                $flattened[] = [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'path' => $path,
                ];
                $walk($cat->id, $path);
            }
        };

        $walk(null);

        return Inertia::render('Admin/theme1/Students/BulkData', [
            'categories' => $flattened,
        ]);
    }

    /**
     * Download CSV template for students bulk import.
     */
    public function bulkDataTemplate()
    {
        $headers = [
            'name (الاسم)',
            'email (البريد الإلكتروني)',
            'password (كلمة المرور)',
            'phone (رقم الهاتف)',
            'category_id (الفصل ID)',
            'guardian_national_id (الرقم القومي لولي الأمر - اختياري)',
        ];
        $example = ['محمد أحمد', 'mohamed.student@example.com', 'Password123', '01000000000', '12', '29901011234567'];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'students_bulk_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import students from CSV file.
     */
    public function bulkDataImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (!$handle) {
            return back()->with('error', 'تعذر قراءة الملف.');
        }

        $headerRow = fgetcsv($handle);
        if (!$headerRow) {
            fclose($handle);
            return back()->with('error', 'الملف فارغ أو غير صالح.');
        }

        $delimiter = ',';
        if (count($headerRow) === 1 && str_contains((string) $headerRow[0], ';')) {
            $delimiter = ';';
            $headerRow = str_getcsv((string) $headerRow[0], ';');
        }

        $normalizeHeader = function ($header) {
            $value = mb_strtolower(trim((string) $header));
            $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value);
            $value = preg_replace('/^\x{feff}/u', '', $value);
            $value = preg_replace('/^[^a-z\x{0600}-\x{06FF}_]+/u', '', $value);

            if (str_contains($value, 'name') || str_contains($value, 'الاسم')) {
                return 'name';
            }
            if (str_contains($value, 'email') || str_contains($value, 'البريد')) {
                return 'email';
            }
            if (str_contains($value, 'password') || str_contains($value, 'كلمة المرور')) {
                return 'password';
            }
            if (str_contains($value, 'phone') || str_contains($value, 'الهاتف')) {
                return 'phone';
            }
            if (str_contains($value, 'category_id') || str_contains($value, 'category id') || str_contains($value, 'الفصل')) {
                return 'category_id';
            }
            if (str_contains($value, 'guardian_national_id') || str_contains($value, 'guardian national id') || str_contains($value, 'الرقم القومي لولي') || str_contains($value, 'رقم ولي')) {
                return 'guardian_national_id';
            }

            if (preg_match('/[a-z_]+/', $value, $m)) {
                return $m[0];
            }
            return $value;
        };

        $normalizedHeaders = array_map($normalizeHeader, $headerRow);
        $requiredHeaders = ['name', 'email', 'password', 'phone', 'category_id'];
        $optionalHeaders = ['guardian_national_id'];
        $missingHeaders = array_values(array_diff($requiredHeaders, $normalizedHeaders));
        if (!empty($missingHeaders)) {
            fclose($handle);
            return back()->with('error', 'أعمدة ناقصة في الملف: ' . implode(', ', $missingHeaders));
        }

        $headerIndex = array_flip($normalizedHeaders);
        $validCategoryIds = Category::pluck('id')->map(fn($id) => (int) $id)->all();
        $validCategoryLookup = array_fill_keys($validCategoryIds, true);

        $rowsToInsert = [];
        $errors = [];
        $emailsInFile = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            $values = [];
            foreach ($requiredHeaders as $col) {
                $idx = $headerIndex[$col] ?? null;
                $values[$col] = $idx !== null ? trim((string) ($row[$idx] ?? '')) : '';
            }
            foreach ($optionalHeaders as $col) {
                $idx = $headerIndex[$col] ?? null;
                $values[$col] = $idx !== null ? trim((string) ($row[$idx] ?? '')) : '';
            }

            $isEmptyRow = collect($values)->every(fn($v) => $v === '');
            if ($isEmptyRow) {
                continue;
            }

            $payload = [
                'name' => $values['name'],
                'email' => $values['email'],
                'password' => $values['password'],
                'phone' => $values['phone'],
                'category_id' => (int) $values['category_id'],
            ];

            $validator = Validator::make($payload, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'phone' => 'nullable|string|max:20',
                'category_id' => 'required|integer',
            ]);

            if (in_array(strtolower($payload['email']), $emailsInFile, true)) {
                $validator->errors()->add('email', 'البريد الإلكتروني مكرر داخل الملف.');
            }

            if (!isset($validCategoryLookup[(int) $payload['category_id']])) {
                $validator->errors()->add('category_id', 'قيمة category_id غير موجودة في التصنيفات.');
            }

            if ($validator->fails()) {
                $errors[] = 'السطر ' . $lineNumber . ': ' . implode(' | ', $validator->errors()->all());
                continue;
            }

            $emailsInFile[] = strtolower($payload['email']);

            $rowsToInsert[] = [
                'name'                 => $payload['name'],
                'email'                => $payload['email'],
                'password'             => Hash::make($payload['password']),
                'phone'                => $payload['phone'] !== '' ? $payload['phone'] : null,
                'category_id'          => (int) $payload['category_id'],
                'guardian_national_id' => $values['guardian_national_id'] !== '' ? $values['guardian_national_id'] : null,
            ];
        }

        fclose($handle);

        if (empty($rowsToInsert)) {
            $errorMsg = !empty($errors)
                ? 'لم يتم استيراد أي طالب. أول الأخطاء: ' . $errors[0]
                : 'لا توجد بيانات صالحة للاستيراد.';

            return back()->with('error', $errorMsg);
        }

        DB::transaction(function () use ($rowsToInsert) {
            $nationalIds = collect($rowsToInsert)
                ->pluck('guardian_national_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $guardianLookup = !empty($nationalIds)
                ? User::where('user_type', 'guardian')
                    ->whereIn('national_id', $nationalIds)
                    ->pluck('id', 'national_id')
                    ->toArray()
                : [];

            foreach ($rowsToInsert as $row) {
                $student = User::create([
                    'name'             => $row['name'],
                    'email'            => $row['email'],
                    'password'         => $row['password'],
                    'phone'            => $row['phone'],
                    'category_id'      => $row['category_id'],
                    'user_type'        => 'student',
                    'role'             => 'student',
                    'student_code'     => $this->studentCodes->generate(),
                    'enrollment_date'  => now()->toDateString(),
                    'student_status'   => StudentStatus::ACTIVE,
                ]);

                $this->studentStatus->recordInitial($student);
                $this->studentEnrollments->recordInitialEnrollment(
                    $student,
                    $student->category_id,
                    $student->enrollment_date?->toDateString(),
                    'initial',
                    'import',
                );

                if (!empty($row['guardian_national_id']) && isset($guardianLookup[$row['guardian_national_id']])) {
                    $student->guardians()->attach($guardianLookup[$row['guardian_national_id']], [
                        'relationship_type' => GuardianRelationship::GUARDIAN,
                        'is_primary' => true,
                        'is_pickup_authorized' => true,
                    ]);
                }
            }
        });

        $successMsg = 'تم استيراد ' . count($rowsToInsert) . ' طالب بنجاح.';
        if (!empty($errors)) {
            $successMsg .= ' مع وجود ' . count($errors) . ' صف لم يتم استيراده.';
            return back()->with('success', $successMsg)->with('error', 'أول خطأ: ' . $errors[0]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Store a newly created student in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateStudentPayload($request, creating: true);
        $data['user_type'] = 'student';
        $data['role'] = 'student';
        $data['password'] = bcrypt($data['password']);
        $data['student_code'] = $this->studentCodes->generate();
        $data['student_status'] = $data['student_status'] ?? StudentStatus::ACTIVE;

        if (empty($data['enrollment_date'])) {
            $data['enrollment_date'] = now()->toDateString();
        }

        $data['name'] = $this->resolveDisplayName($data);

        $student = User::create($data);

        $this->studentStatus->recordInitial($student);

        $this->studentEnrollments->recordInitialEnrollment(
            $student,
            $student->category_id,
            $student->enrollment_date?->toDateString(),
        );

        $this->studentGuardians->sync($student, $this->normalizeGuardianLinks($request));

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'تم إنشاء الطالب بنجاح');
    }

    /**
     * Display the student profile hub.
     */
    public function show(User $student)
    {
        $this->studentCodes->assignIfMissing($student);

        return Inertia::render('Admin/theme1/Students/Show', array_merge(
            $this->studentProfile->forAdmin($student),
            [
                'categories' => $this->categoryTreeForForms(),
                'relationshipTypeOptions' => GuardianRelationship::typeOptions(),
            ],
        ));
    }

    /**
     * Show the form for editing the student.
     */
    public function edit(User $student)
    {
        abort_unless($student->user_type === 'student', 404);

        $student->load('guardians:id,name,national_id,email');

        $parents = User::where('user_type', 'guardian')
            ->orderBy('name')
            ->get(['id', 'name', 'national_id', 'email']);

        return Inertia::render('Admin/theme1/Students/Edit', [
            'student'    => $student,
            'categories' => $this->categoryTreeForForms(),
            'parents'    => $parents,
            'relationshipTypeOptions' => GuardianRelationship::typeOptions(),
        ]);
    }

    /**
     * Update the student in storage.
     */
    public function update(Request $request, User $student)
    {
        abort_unless($student->user_type === 'student', 404);

        $data = $this->validateStudentPayload($request, creating: false, studentId: $student->id);
        $data['name'] = $this->resolveDisplayName($data);

        $oldCategoryId = $student->category_id;

        $student->update($data);

        if ((int) ($data['category_id'] ?? 0) !== (int) $oldCategoryId) {
            $this->studentEnrollments->handleCategoryChange(
                $student->fresh(),
                $oldCategoryId,
                $data['category_id'] ?? null,
            );
        }

        $this->studentGuardians->sync($student, $this->normalizeGuardianLinks($request));

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'تم تحديث بيانات الطالب بنجاح');
    }

    /**
     * Remove the student from storage.
     */
    public function destroy(User $student)
    {
        abort_unless($student->user_type === 'student', 404);
        $student->delete();

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'تم حذف الطالب بنجاح');
    }

    protected function validateStudentPayload(Request $request, bool $creating, ?int $studentId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email' . ($studentId ? ',' . $studentId : ''),
            'phone' => 'nullable|string|max:20',
            'category_id' => ($creating ? 'required' : 'nullable') . '|exists:categories,id',
            'first_name' => 'nullable|string|max:100',
            'father_name' => 'nullable|string|max:100',
            'grandfather_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'national_id' => 'nullable|string|max:50',
            'enrollment_date' => 'nullable|date',
            'student_status' => $creating ? 'nullable|in:' . implode(',', StudentStatus::all()) : 'prohibited',
            'guardian_ids' => 'nullable|array',
            'guardian_ids.*' => 'integer|exists:users,id',
            'guardian_links' => 'nullable|array',
            'guardian_links.*.guardian_id' => 'required|integer|exists:users,id',
            'guardian_links.*.relationship_type' => 'nullable|in:' . implode(',', GuardianRelationship::types()),
            'guardian_links.*.is_primary' => 'nullable|boolean',
            'guardian_links.*.is_emergency_contact' => 'nullable|boolean',
            'guardian_links.*.is_pickup_authorized' => 'nullable|boolean',
            'guardian_links.*.is_financial_responsible' => 'nullable|boolean',
        ];

        if ($creating) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        return $request->validate($rules);
    }

    protected function resolveDisplayName(array $data): string
    {
        $parts = array_filter([
            $data['first_name'] ?? null,
            $data['father_name'] ?? null,
            $data['grandfather_name'] ?? null,
        ]);

        return $parts ? implode(' ', $parts) : $data['name'];
    }

    protected function normalizeGuardianLinks(Request $request): array
    {
        $links = $request->input('guardian_links', []);

        if (! empty($links)) {
            return collect($links)
                ->filter(fn ($link) => ! empty($link['guardian_id']))
                ->values()
                ->all();
        }

        return collect($request->input('guardian_ids', []))
            ->filter()
            ->values()
            ->map(fn ($id, $index) => [
                'guardian_id' => (int) $id,
                'relationship_type' => GuardianRelationship::GUARDIAN,
                'is_primary' => $index === 0,
                'is_emergency_contact' => false,
                'is_pickup_authorized' => true,
                'is_financial_responsible' => false,
            ])
            ->all();
    }

    protected function categoryTreeForForms(): array
    {
        return Category::whereNull('parent_id')
            ->where('status', 'enable')
            ->with(['children' => fn($q) => $q->where('status', 'enable')->orderBy('name')
                ->with(['children' => fn($q2) => $q2->where('status', 'enable')->orderBy('name')
                    ->with(['children' => fn($q3) => $q3->where('status', 'enable')->orderBy('name')])
                ])
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'children' => $c->children->map(fn($ch) => [
                    'id'       => $ch->id,
                    'name'     => $ch->name,
                    'children' => $ch->children->map(fn($gch) => [
                        'id'       => $gch->id,
                        'name'     => $gch->name,
                        'children' => $gch->children->map(fn($sgch) => ['id' => $sgch->id, 'name' => $sgch->name])->values(),
                    ])->values(),
                ])->values(),
            ])
            ->all();
    }
}

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

class StudentController extends Controller
{
    /**
     * Display a listing of students and guardians.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'students');

        // Get all students
        $students = User::where('user_type', 'student')
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all guardians/parents
        $guardians = User::where('user_type', 'guardian')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all categories for filtering
        $categories = Category::whereNull('parent_id')->with('children')->get();

        return Inertia::render('Admin/theme1/Students/Index', [
            'students' => $students,
            'guardians' => $guardians,
            'categories' => $categories,
            'tab' => $tab,
        ]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        $categories = Category::whereNull('parent_id')
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
            ]);

        $parents = User::where('user_type', 'guardian')
            ->orderBy('name')
            ->get(['id', 'name', 'national_id', 'email']);

        return Inertia::render('Admin/theme1/Students/Create', [
            'categories' => $categories,
            'parents'    => $parents,
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
            // Read optional columns
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
            // Build a lookup: national_id => guardian user_id
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
                    'name'        => $row['name'],
                    'email'       => $row['email'],
                    'password'    => $row['password'],
                    'phone'       => $row['phone'],
                    'category_id' => $row['category_id'],
                    'user_type'   => 'student',
                    'role'        => 'student',
                ]);

                if (!empty($row['guardian_national_id']) && isset($guardianLookup[$row['guardian_national_id']])) {
                    $student->guardians()->attach($guardianLookup[$row['guardian_national_id']]);
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
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'category_id' => 'required|exists:categories,id',
        ]);

        $data['user_type'] = 'student';
        $data['password'] = bcrypt($data['password']);

        $student = User::create($data);

        if (!empty($request->guardian_ids)) {
            $validGuardians = User::where('user_type', 'guardian')
                ->whereIn('id', $request->guardian_ids)
                ->pluck('id');
            $student->guardians()->sync($validGuardians);
        }

        return redirect()->route('admin.students.index')->with('success', 'تم إنشاء السجل بنجاح');
    }

    /**
     * Display the specified student.
     */
    public function show($id)
    {
        $student = User::findOrFail($id);
        $categories = Category::whereNull('parent_id')->with('children')->get();

        return Inertia::render('Admin/theme1/Students/Show', [
            'student' => $student,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for editing the student.
     */
    public function edit($id)
    {
        $student = User::findOrFail($id);
        $student->load('guardians:id,name,national_id,email');

        $categories = Category::whereNull('parent_id')
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
            ]);

        $parents = User::where('user_type', 'guardian')
            ->orderBy('name')
            ->get(['id', 'name', 'national_id', 'email']);

        return Inertia::render('Admin/theme1/Students/Edit', [
            'student'    => $student,
            'categories' => $categories,
            'parents'    => $parents,
        ]);
    }

    /**
     * Update the student in storage.
     */
    public function update(Request $request, $id)
    {
        $student = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $student->update($data);

        $validGuardians = [];
        if (!empty($request->guardian_ids)) {
            $validGuardians = User::where('user_type', 'guardian')
                ->whereIn('id', $request->guardian_ids)
                ->pluck('id')
                ->toArray();
        }
        $student->guardians()->sync($validGuardians);

        return redirect()->route('admin.students.index')->with('success', 'تم تحديث بيانات الطالب بنجاح');
    }

    /**
     * Remove the student from storage.
     */
    public function destroy($id)
    {
        $student = User::findOrFail($id);
        $student->delete();

        return back()->with('success', 'تم حذف الطالب بنجاح');
    }
}

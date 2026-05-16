<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $teachers = User::where('user_type', 'teacher')
            ->with('subjects')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/theme1/Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/theme1/Teachers/Create', [
            'subjects' => Subject::all(),
        ]);
    }

    /**
     * Show bulk data page for teachers.
     */
    public function bulkData()
    {
        return Inertia::render('Admin/theme1/Teachers/BulkData', [
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Download CSV template for teachers bulk import.
     */
    public function bulkDataTemplate()
    {
        $headers = [
            'name (الاسم)',
            'email (البريد الإلكتروني)',
            'password (كلمة المرور)',
            'phone (رقم الهاتف)',
            'department (القسم)',
            'job_title (الوظيفة)',
            'subjects (المواد)',
        ];
        $example = ['أحمد محمد', 'ahmed.teacher@example.com', 'Password123', '01000000000', 'العلوم', 'مدرس رياضيات', '1,2'];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'teachers_bulk_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import teachers from CSV file.
     */
    public function bulkDataImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $filePath = $request->file('file')->getRealPath();
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            return back()->with('error', 'تعذر قراءة الملف.');
        }

        $headerRow = fgetcsv($handle);
        if (!$headerRow) {
            fclose($handle);
            return back()->with('error', 'الملف فارغ أو غير صالح.');
        }

        // Excel on some locales exports CSV with ';' delimiter.
        if (count($headerRow) === 1 && str_contains((string) $headerRow[0], ';')) {
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
            if (str_contains($value, 'department') || str_contains($value, 'القسم')) {
                return 'department';
            }
            if (str_contains($value, 'job_title') || str_contains($value, 'job title') || str_contains($value, 'الوظيفة')) {
                return 'job_title';
            }
            if (str_contains($value, 'subjects') || str_contains($value, 'المواد')) {
                return 'subjects';
            }

            if (preg_match('/[a-z_]+/', $value, $m)) {
                return $m[0];
            }

            return trim($value);
        };

        $normalizedHeaders = array_map($normalizeHeader, $headerRow);
        $requiredHeaders = ['name', 'email', 'password', 'phone', 'department', 'job_title', 'subjects'];
        $missingHeaders = array_values(array_diff($requiredHeaders, $normalizedHeaders));

        if (!empty($missingHeaders)) {
            fclose($handle);
            return back()->with('error', 'أعمدة ناقصة في الملف: ' . implode(', ', $missingHeaders));
        }

        $headerIndex = array_flip($normalizedHeaders);
        $validSubjectIds = Subject::pluck('id')->map(fn($id) => (int) $id)->all();
        $validSubjectLookup = array_fill_keys($validSubjectIds, true);

        $rowsToInsert = [];
        $errors = [];
        $emailsInFile = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            $values = [];
            foreach ($requiredHeaders as $col) {
                $idx = $headerIndex[$col] ?? null;
                $values[$col] = $idx !== null ? trim((string) ($row[$idx] ?? '')) : '';
            }

            $isEmptyRow = collect($values)->every(fn($v) => $v === '');
            if ($isEmptyRow) {
                continue;
            }

            $subjectIds = collect(preg_split('/[,|]/', $values['subjects']))
                ->map(fn($v) => trim((string) $v))
                ->filter()
                ->map(fn($v) => (int) $v)
                ->values()
                ->all();

            $validator = Validator::make([
                'name' => $values['name'],
                'email' => $values['email'],
                'password' => $values['password'],
                'phone' => $values['phone'],
                'department' => $values['department'],
                'job_title' => $values['job_title'],
                'subjects' => $subjectIds,
            ], [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'phone' => 'nullable|string|max:20',
                'department' => 'nullable|string|max:255',
                'job_title' => 'nullable|string|max:255',
                'subjects' => 'required|array|min:1',
            ]);

            if (in_array(strtolower($values['email']), $emailsInFile, true)) {
                $validator->errors()->add('email', 'البريد الإلكتروني مكرر داخل الملف.');
            }

            $invalidSubjects = collect($subjectIds)
                ->filter(fn($id) => !isset($validSubjectLookup[(int) $id]))
                ->values()
                ->all();

            if (!empty($invalidSubjects)) {
                $validator->errors()->add('subjects', 'قيم مواد غير موجودة: ' . implode(', ', $invalidSubjects));
            }

            if ($validator->fails()) {
                $errors[] = 'السطر ' . $lineNumber . ': ' . implode(' | ', $validator->errors()->all());
                continue;
            }

            $emailsInFile[] = strtolower($values['email']);

            $rowsToInsert[] = [
                'name' => $values['name'],
                'email' => $values['email'],
                'password' => Hash::make($values['password']),
                'phone' => $values['phone'] !== '' ? $values['phone'] : null,
                'department' => $values['department'] !== '' ? $values['department'] : null,
                'job_title' => $values['job_title'] !== '' ? $values['job_title'] : null,
                'subjects' => $subjectIds,
            ];
        }

        fclose($handle);

        if (empty($rowsToInsert)) {
            $errorMsg = !empty($errors)
                ? 'لم يتم استيراد أي مدرس. أول الأخطاء: ' . $errors[0]
                : 'لا توجد بيانات صالحة للاستيراد.';

            return back()->with('error', $errorMsg);
        }

        DB::transaction(function () use ($rowsToInsert) {
            foreach ($rowsToInsert as $row) {
                $teacher = User::create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $row['password'],
                    'phone' => $row['phone'],
                    'department' => $row['department'],
                    'job_title' => $row['job_title'],
                    'user_type' => 'teacher',
                    'role' => 'teacher',
                ]);

                $teacher->subjects()->attach($row['subjects']);
            }
        });

        $successMsg = 'تم استيراد ' . count($rowsToInsert) . ' مدرس بنجاح.';
        if (!empty($errors)) {
            $successMsg .= ' مع وجود ' . count($errors) . ' صف لم يتم استيراده.';
            return back()->with('success', $successMsg)->with('error', 'أول خطأ: ' . $errors[0]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'exists:subjects,id',
        ]);

        $teacher = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'department' => $validated['department'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'user_type' => 'teacher',
            'role' => 'teacher', // Helper for other logic if needed
        ]);

        $teacher->subjects()->attach($validated['subjects']);

        return redirect()->route('admin.teachers.index')
            ->with('success', 'تم إضافة المدرس بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $teacher = User::where('user_type', 'teacher')->with('subjects')->findOrFail($id);
        
        return Inertia::render('Admin/theme1/Teachers/Edit', [
            'teacher' => $teacher,
            'subjects' => Subject::all(),
            'teacherSubjects' => $teacher->subjects->pluck('id'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $teacher = User::where('user_type', 'teacher')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($teacher->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'exists:subjects,id',
        ]);

        $teacher->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'department' => $validated['department'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
        ]);

        if (!empty($validated['password'])) {
            $teacher->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        $teacher->subjects()->sync($validated['subjects']);

        return redirect()->route('admin.teachers.index')
            ->with('success', 'تم تحديث بيانات المدرس بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $teacher = User::where('user_type', 'teacher')->findOrFail($id);
        $teacher->delete();

        return redirect()->route('admin.teachers.index')
            ->with('success', 'تم حذف المدرس بنجاح');
    }
}

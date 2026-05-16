<?php

namespace App\Http\Controllers\admin;

use Inertia\Inertia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class ParentController extends Controller
{
    /**
     * Display a listing of parents/guardians.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');

        $parents = User::where('user_type', 'guardian')
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%")
                   ->orWhere('phone', 'like', "%{$search}%")
                   ->orWhere('national_id', 'like', "%{$search}%");
            }))
            ->with(['students:id,name,national_id'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/theme1/Parents/Index', [
            'parents' => $parents,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new parent.
     */
    public function create()
    {
        $students = User::where('user_type', 'student')
            ->orderBy('name')
            ->get(['id', 'name', 'national_id', 'email']);

        return Inertia::render('Admin/theme1/Parents/Create', [
            'students' => $students,
        ]);
    }

    /**
     * Store a newly created parent in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'phone'       => 'nullable|string|max:20',
            'job_title'   => 'nullable|string|max:255',
            'national_id' => 'required|string|max:50',
            'password'    => 'required|string|min:8|confirmed',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        $parent = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? null,
            'job_title'   => $data['job_title'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'password'    => Hash::make($data['password']),
            'user_type'   => 'guardian',
        ]);

        if (!empty($data['student_ids'])) {
            // Attach only students with user_type = student
            $validStudents = User::where('user_type', 'student')
                ->whereIn('id', $data['student_ids'])
                ->pluck('id');
            $parent->students()->sync($validStudents);
        }

        return redirect()->route('admin.parents.index')->with('success', 'تم إضافة ولي الأمر بنجاح');
    }

    /**
     * Show the form for editing the specified parent.
     */
    public function edit($id)
    {
        $parent = User::where('user_type', 'guardian')->findOrFail($id);
        $parent->load('students:id,name,national_id,email');

        $students = User::where('user_type', 'student')
            ->orderBy('name')
            ->get(['id', 'name', 'national_id', 'email']);

        return Inertia::render('Admin/theme1/Parents/Edit', [
            'parent'   => $parent,
            'students' => $students,
        ]);
    }

    /**
     * Update the specified parent in storage.
     */
    public function update(Request $request, $id)
    {
        $parent = User::where('user_type', 'guardian')->findOrFail($id);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $id,
            'phone'         => 'nullable|string|max:20',
            'job_title'     => 'nullable|string|max:255',
            'national_id'   => 'required|string|max:50',
            'student_ids'   => 'nullable|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        $parent->update([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? null,
            'job_title'   => $data['job_title'] ?? null,
            'national_id' => $data['national_id'] ?? null,
        ]);

        $validStudents = [];
        if (!empty($data['student_ids'])) {
            $validStudents = User::where('user_type', 'student')
                ->whereIn('id', $data['student_ids'])
                ->pluck('id')
                ->toArray();
        }
        $parent->students()->sync($validStudents);

        return redirect()->route('admin.parents.index')->with('success', 'تم تحديث بيانات ولي الأمر بنجاح');
    }

    /**
     * Remove the specified parent from storage.
     */
    public function destroy($id)
    {
        $parent = User::where('user_type', 'guardian')->findOrFail($id);
        $parent->students()->detach();
        $parent->delete();

        return back()->with('success', 'تم حذف ولي الأمر بنجاح');
    }

    /**
     * Show bulk data import page for parents.
     */
    public function bulkData()
    {
        return Inertia::render('Admin/theme1/Parents/BulkData');
    }

    /**
     * Download CSV template for parents bulk import.
     */
    public function bulkDataTemplate()
    {
        $headers = [
            'name (الاسم)',
            'email (البريد الإلكتروني)',
            'password (كلمة المرور)',
            'phone (رقم الهاتف)',
            'national_id (الرقم القومي)',
            'job_title (المسمى الوظيفي - اختياري)',
        ];
        $example = ['أحمد محمود', 'ahmed.parent@example.com', 'Password123', '01000000000', '29901011234567', 'محاسب'];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'parents_bulk_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import parents from uploaded CSV file.
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
            if (str_contains($value, 'national_id') || str_contains($value, 'national id') || str_contains($value, 'الرقم القومي')) {
                return 'national_id';
            }
            if (str_contains($value, 'job_title') || str_contains($value, 'job title') || str_contains($value, 'المسمى الوظيفي') || str_contains($value, 'الوظيفة')) {
                return 'job_title';
            }

            if (preg_match('/[a-z_]+/', $value, $m)) {
                return $m[0];
            }
            return $value;
        };

        $normalizedHeaders = array_map($normalizeHeader, $headerRow);
        $headerIndex = array_flip($normalizedHeaders);

        $requiredHeaders = ['name', 'email', 'password', 'phone', 'national_id'];
        $optionalHeaders = ['job_title'];

        $missingHeaders = array_values(array_diff($requiredHeaders, $normalizedHeaders));
        if (!empty($missingHeaders)) {
            fclose($handle);
            return back()->with('error', 'أعمدة ناقصة في الملف: ' . implode(', ', $missingHeaders));
        }

        $rowsToInsert = [];
        $errors = [];
        $emailsInFile = [];
        $nationalIdsInFile = [];
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
                'name'        => $values['name'],
                'email'       => $values['email'],
                'password'    => $values['password'],
                'phone'       => $values['phone'],
                'national_id' => $values['national_id'],
            ];

            $validator = Validator::make($payload, [
                'name'        => 'required|string|max:255',
                'email'       => 'required|email|max:255|unique:users,email',
                'password'    => 'required|string|min:8',
                'phone'       => 'nullable|string|max:20',
                'national_id' => 'required|string|max:50',
            ]);

            if (in_array(strtolower($payload['email']), $emailsInFile, true)) {
                $validator->errors()->add('email', 'البريد الإلكتروني مكرر داخل الملف.');
            }
            if ($payload['national_id'] !== '' && in_array($payload['national_id'], $nationalIdsInFile, true)) {
                $validator->errors()->add('national_id', 'الرقم القومي مكرر داخل الملف.');
            }
            if ($payload['national_id'] !== '' && User::where('national_id', $payload['national_id'])->exists()) {
                $validator->errors()->add('national_id', 'الرقم القومي موجود مسبقاً في قاعدة البيانات.');
            }

            if ($validator->fails()) {
                $errors[] = 'السطر ' . $lineNumber . ': ' . implode(' | ', $validator->errors()->all());
                continue;
            }

            $emailsInFile[] = strtolower($payload['email']);
            $nationalIdsInFile[] = $payload['national_id'];

            $rowsToInsert[] = [
                'name'                => $payload['name'],
                'email'               => $payload['email'],
                'password'            => Hash::make($payload['password']),
                'phone'               => $payload['phone'] !== '' ? $payload['phone'] : null,
                'national_id'         => $payload['national_id'],
                'job_title'           => $values['job_title'] !== '' ? $values['job_title'] : null,
            ];
        }

        fclose($handle);

        if (empty($rowsToInsert)) {
            $errorMsg = !empty($errors)
                ? 'لم يتم استيراد أي ولي أمر. أول الأخطاء: ' . $errors[0]
                : 'لا توجد بيانات صالحة للاستيراد.';

            return back()->with('error', $errorMsg);
        }

        DB::transaction(function () use ($rowsToInsert) {
            foreach ($rowsToInsert as $row) {
                $parent = User::create([
                    'name'        => $row['name'],
                    'email'       => $row['email'],
                    'password'    => $row['password'],
                    'phone'       => $row['phone'],
                    'national_id' => $row['national_id'],
                    'job_title'   => $row['job_title'],
                    'user_type'   => 'guardian',
                ]);

            }
        });

        $successMsg = 'تم استيراد ' . count($rowsToInsert) . ' ولي أمر بنجاح.';
        if (!empty($errors)) {
            $successMsg .= ' مع وجود ' . count($errors) . ' صف لم يتم استيراده.';
            return back()->with('success', $successMsg)->with('error', 'أول خطأ: ' . $errors[0]);
        }

        return back()->with('success', $successMsg);
    }
}

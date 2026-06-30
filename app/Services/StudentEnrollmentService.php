<?php

namespace App\Services;

use App\Models\Category;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Student\AcademicYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentService
{
    public function recordInitialEnrollment(
        User $student,
        ?int $categoryId,
        ?string $enrollmentDate = null,
        string $actionType = 'initial',
        ?string $source = 'manual',
    ): ?StudentEnrollment {
        if (! $categoryId) {
            return null;
        }

        $date = $enrollmentDate
            ? Carbon::parse($enrollmentDate)->toDateString()
            : ($student->enrollment_date?->toDateString() ?? now()->toDateString());

        return $this->openEnrollment($student, $categoryId, $date, $actionType, $source);
    }

    public function recordAdmissionEnrollment(
        User $student,
        int $categoryId,
        int $admissionApplicationId,
        ?string $enrollmentDate = null,
        ?string $notes = null,
    ): StudentEnrollment {
        $existing = StudentEnrollment::query()
            ->where('admission_reference_id', $admissionApplicationId)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'enrollment' => 'يوجد قيد مسجل مسبقاً لهذا الطلب.',
            ]);
        }

        $date = $enrollmentDate
            ? Carbon::parse($enrollmentDate)->toDateString()
            : ($student->enrollment_date?->toDateString() ?? now()->toDateString());

        return $this->openEnrollment(
            $student,
            $categoryId,
            $date,
            'admission',
            'admission',
            null,
            $notes,
            null,
            $admissionApplicationId,
        );
    }

    public function promote(
        User $student,
        int $categoryId,
        string $academicYear,
        ?string $enrollmentDate = null,
        ?string $notes = null,
    ): StudentEnrollment {
        $this->assertHasCurrentEnrollment($student);
        $this->assertNoDuplicateActive($student);

        $date = $enrollmentDate
            ? Carbon::parse($enrollmentDate)->toDateString()
            : now()->toDateString();

        $this->closeCurrentEnrollment($student, 'completed', now()->toDateString());

        $enrollment = $this->openEnrollment(
            $student,
            $categoryId,
            $date,
            'promotion',
            'manual',
            null,
            $notes,
            $academicYear,
        );

        $student->update(['category_id' => $categoryId]);

        return $enrollment;
    }

    public function transfer(
        User $student,
        int $categoryId,
        string $transferDate,
        ?string $reason = null,
        ?string $notes = null,
    ): StudentEnrollment {
        $this->assertHasCurrentEnrollment($student);

        $date = Carbon::parse($transferDate)->toDateString();

        $this->closeCurrentEnrollment($student, 'transferred', $date, $reason);

        $enrollment = $this->openEnrollment(
            $student,
            $categoryId,
            $date,
            'transfer',
            'manual',
            $reason,
            $notes,
        );

        $student->update(['category_id' => $categoryId]);

        return $enrollment;
    }

    public function withdraw(
        User $student,
        string $withdrawalDate,
        ?string $reason = null,
        ?string $notes = null,
    ): ?StudentEnrollment {
        $current = $this->currentEnrollment($student);

        if (! $current) {
            throw ValidationException::withMessages([
                'enrollment' => 'لا يوجد قيد نشط لإغلاقه.',
            ]);
        }

        $date = Carbon::parse($withdrawalDate)->toDateString();

        $current->update([
            'is_current' => false,
            'status' => 'withdrawn',
            'action_type' => 'withdrawal',
            'withdrawal_date' => $date,
            'reason' => $reason,
            'notes' => $notes,
            'performed_by_user_id' => Auth::id(),
        ]);

        return $current->fresh();
    }

    public function reEnroll(
        User $student,
        int $categoryId,
        ?string $enrollmentDate = null,
        ?string $academicYear = null,
        ?string $notes = null,
    ): StudentEnrollment {
        if ($this->currentEnrollment($student)) {
            throw ValidationException::withMessages([
                'enrollment' => 'الطالب لديه قيد نشط بالفعل.',
            ]);
        }

        $date = $enrollmentDate
            ? Carbon::parse($enrollmentDate)->toDateString()
            : now()->toDateString();

        $enrollment = $this->openEnrollment(
            $student,
            $categoryId,
            $date,
            're_enrollment',
            'manual',
            null,
            $notes,
            $academicYear,
        );

        $student->update([
            'category_id' => $categoryId,
            'enrollment_date' => $date,
        ]);

        return $enrollment;
    }

    public function graduate(
        User $student,
        ?string $graduationDate = null,
        ?string $notes = null,
    ): ?StudentEnrollment {
        $this->assertHasCurrentEnrollment($student);

        $current = $this->currentEnrollment($student);
        $date = $graduationDate
            ? Carbon::parse($graduationDate)->toDateString()
            : now()->toDateString();

        $current->update([
            'is_current' => false,
            'status' => 'completed',
            'action_type' => 'graduation',
            'promotion_date' => $date,
            'notes' => $notes,
            'performed_by_user_id' => Auth::id(),
        ]);

        return $current->fresh();
    }

    public function handleCategoryChange(
        User $student,
        ?int $oldCategoryId,
        ?int $newCategoryId,
        ?string $reason = null,
        string $actionType = 'promotion',
    ): void {
        if ($oldCategoryId === $newCategoryId) {
            return;
        }

        $current = $this->currentEnrollment($student);

        if ($current && (int) $current->category_id === (int) $newCategoryId) {
            return;
        }

        if ($current) {
            $current->update([
                'is_current' => false,
                'status' => $actionType === 'transfer' ? 'transferred' : 'completed',
                'promotion_date' => now()->toDateString(),
                'reason' => $reason ?? $current->reason,
                'performed_by_user_id' => Auth::id(),
            ]);
        }

        if ($newCategoryId) {
            $this->openEnrollment(
                $student,
                $newCategoryId,
                now()->toDateString(),
                $actionType,
                'manual',
                $reason,
            );
        }
    }

    public function backfillForStudent(User $student): void
    {
        if ($student->user_type !== 'student') {
            return;
        }

        $hasEnrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->exists();

        if ($hasEnrollment) {
            return;
        }

        if (! $student->category_id) {
            return;
        }

        $this->recordInitialEnrollment(
            $student,
            $student->category_id,
            $student->enrollment_date?->toDateString(),
            'initial',
            'backfill',
        );
    }

    public function currentEnrollment(User $student): ?StudentEnrollment
    {
        return StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_current', true)
            ->latest('enrollment_date')
            ->first();
    }

    public function assertHasCurrentEnrollment(User $student): void
    {
        if (! $this->currentEnrollment($student)) {
            throw ValidationException::withMessages([
                'enrollment' => 'لا يوجد قيد نشط لهذا الطالب.',
            ]);
        }
    }

    public function assertNoDuplicateActive(User $student): void
    {
        $count = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_current', true)
            ->count();

        if ($count > 1) {
            throw ValidationException::withMessages([
                'enrollment' => 'يوجد أكثر من قيد نشط. يرجى مراجعة سجل القيد.',
            ]);
        }
    }

    public function resolvePlacementFromCategory(?int $categoryId): array
    {
        if (! $categoryId) {
            return [
                'category_id' => null,
                'stage_category_id' => null,
                'stage_name' => null,
                'grade_name' => null,
                'class_name' => null,
                'path_label' => null,
            ];
        }

        $path = $this->categoryPath($categoryId);
        $count = count($path);

        return [
            'category_id' => $categoryId,
            'stage_category_id' => $path[0]['id'] ?? null,
            'stage_name' => $path[0]['name'] ?? null,
            'grade_name' => $count > 1 ? ($path[1]['name'] ?? null) : null,
            'class_name' => $count > 0 ? ($path[$count - 1]['name'] ?? null) : null,
            'path_label' => collect($path)->pluck('name')->implode(' / '),
        ];
    }

    protected function closeCurrentEnrollment(
        User $student,
        string $status,
        string $closeDate,
        ?string $reason = null,
    ): void {
        $current = $this->currentEnrollment($student);

        if (! $current) {
            return;
        }

        $current->update([
            'is_current' => false,
            'status' => $status,
            'promotion_date' => $closeDate,
            'reason' => $reason ?? $current->reason,
            'performed_by_user_id' => Auth::id(),
        ]);
    }

    protected function openEnrollment(
        User $student,
        int $categoryId,
        string $enrollmentDate,
        string $actionType,
        ?string $source = 'manual',
        ?string $reason = null,
        ?string $notes = null,
        ?string $academicYear = null,
        ?int $admissionReferenceId = null,
    ): StudentEnrollment {
        StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'performed_by_user_id' => Auth::id(),
            ]);

        $placement = $this->resolvePlacementFromCategory($categoryId);

        return StudentEnrollment::create([
            'student_id' => $student->id,
            'academic_year' => $academicYear ?? AcademicYear::forDate(Carbon::parse($enrollmentDate)),
            'category_id' => $placement['category_id'],
            'stage_category_id' => $placement['stage_category_id'],
            'stage_name' => $placement['stage_name'],
            'grade_name' => $placement['grade_name'],
            'class_name' => $placement['class_name'],
            'enrollment_date' => $enrollmentDate,
            'status' => 'active',
            'action_type' => $actionType,
            'reason' => $reason,
            'notes' => $notes,
            'is_current' => true,
            'source' => $source,
            'admission_reference_id' => $admissionReferenceId,
            'performed_by_user_id' => Auth::id(),
        ]);
    }

    protected function categoryPath(int $categoryId): array
    {
        $path = [];
        $currentId = $categoryId;
        $guard = 0;

        while ($currentId && $guard < 12) {
            $node = Category::query()->find($currentId);
            if (! $node) {
                break;
            }

            array_unshift($path, [
                'id' => $node->id,
                'name' => $node->name,
            ]);

            $currentId = $node->parent_id;
            $guard++;
        }

        return $path;
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The subjects that belong to the user (teacher).
     */
    public function subjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_user');
    }

    /**
     * Subject IDs the teacher may use: direct assignment + subjects from timetable.
     */
    public function teachingSubjectIds(): \Illuminate\Support\Collection
    {
        $fromPivot = $this->subjects()->pluck('subjects.id');
        $fromTimetable = TimetableAssignment::query()
            ->where('teacher_id', $this->id)
            ->whereNotNull('subject_id')
            ->distinct()
            ->pluck('subject_id');

        return $fromPivot->merge($fromTimetable)->unique()->values();
    }

    /**
     * Subjects the teacher may teach (pivot + timetable assignments).
     */
    public function teachingSubjects(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $this->teachingSubjectIds();

        if ($ids->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return Subject::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function teachesSubject(int $subjectId): bool
    {
        return $this->teachingSubjectIds()->contains($subjectId);
    }

    /**
     * The category that belongs to the user (student).
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The guardians (parents) linked to this student.
     */
    public function guardians(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'student_id', 'guardian_id')
            ->withPivot([
                'relationship_type',
                'is_primary',
                'is_emergency_contact',
                'is_pickup_authorized',
                'is_financial_responsible',
            ])
            ->withTimestamps();
    }

    /**
     * The students linked to this guardian (parent).
     */
    public function students(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'guardian_id', 'student_id')
            ->withPivot([
                'relationship_type',
                'is_primary',
                'is_emergency_contact',
                'is_pickup_authorized',
                'is_financial_responsible',
            ])
            ->withTimestamps();
    }

    public function studentEnrollments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }

    public function currentStudentEnrollment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentEnrollment::class, 'student_id')->where('is_current', true);
    }

    public function studentStatusHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentStatusHistory::class, 'student_id');
    }

    /**
     * Official attendance records for this student.
     */
    public function studentAttendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'student_id');
    }

    public function studentGrades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentGrade::class, 'student_id');
    }

    public function studentBehaviorRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentBehaviorRecord::class, 'student_id');
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserWallet::class);
    }

    public function enrollments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LessonEnrollment::class, 'student_id');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'department',
        'job_title',
        'national_id',
        'user_type',
        'category_id',
        'student_code',
        'first_name',
        'father_name',
        'grandfather_name',
        'date_of_birth',
        'gender',
        'enrollment_date',
        'student_status',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'enrollment_date' => 'date',
        ];
    }

    // Role Checks

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function adminRole(): string
    {
        return \App\Support\Admin\AdminRole::normalize($this->role);
    }

    public function hasAdminPermission(string $permission): bool
    {
        return app(\App\Support\Admin\PermissionService::class)->can($this, $permission);
    }

    /**
     * Determine if the user is a student.
     */
    public function isStudent(): bool
    {
        return $this->user_type === 'student';
    }

    /**
     * Determine if the user is a teacher.
     */
    public function isTeacher(): bool
    {
        return $this->user_type === 'teacher';
    }

    /**
     * Determine if the user is a guardian.
     */
    public function isGuardian(): bool
    {
        return $this->user_type === 'guardian';
    }

    /**
     * Determine if the user is control staff.
    */
    public function isControlStaff(): bool
    {
        return $this->user_type === 'control_staff';
    }

    /**
     * Determine if the user is a social worker.
    */
    public function isSocialWorker(): bool
    {
        return $this->user_type === 'social_worker';
    }

    /**
     * Determine if the user is a nurse.
    */
    public function isNurse(): bool
    {
        return $this->user_type === 'nurse';
    }

    // Query Scopes

    /**
     * Scope: only students.
     * Usage: User::students()->get();
     */
    public function scopeStudents($query)
    {
        return $query->where('user_type', 'student');
    }

    /**
     * Scope: only teachers.
     */
    public function scopeTeachers($query)
    {
        return $query->where('user_type', 'teacher');
    }

    /**
     * Scope: only guardians.
     */
    public function scopeGuardians($query)
    {
        return $query->where('user_type', 'guardian');
    }

    /**
     * Scope: filter by one or more user_types.
     * Usage: User::ofType('student')->get();
     *        User::ofType(['nurse', 'social_worker'])->get();
     */
    public function scopeOfType($query, string|array $type)
    {
        return $query->whereIn('user_type', (array) $type);
    }
}

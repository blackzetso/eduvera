<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Read-only duplicate analysis. No linking, merging, or conversion.
 */
class AdmissionDuplicateDetectionService
{
    public function analyze(AdmissionApplication $application): array
    {
        $application->load(['applicants', 'contacts']);

        return [
            'possible_existing_students' => $this->findPossibleStudents($application),
            'possible_existing_guardians' => $this->findPossibleGuardians($application),
            'possible_duplicate_applications' => $this->findDuplicateApplications($application),
            'possible_existing_families' => $this->findPossibleFamilies($application),
        ];
    }

    protected function findPossibleStudents(AdmissionApplication $application): array
    {
        $results = [];

        foreach ($application->applicants as $applicant) {
            if ($applicant->national_id) {
                $byNationalId = User::query()
                    ->where('user_type', 'student')
                    ->where('national_id', $applicant->national_id)
                    ->limit(3)
                    ->get(['id', 'name', 'student_code', 'national_id']);

                foreach ($byNationalId as $student) {
                    $results[] = $this->entityMatch('student', $student, 'national_id', 100, route('admin.students.show', $student));
                }
            }

            $name = $applicant->displayName();
            if ($name && $name !== '—') {
                User::query()
                    ->where('user_type', 'student')
                    ->limit(30)
                    ->get(['id', 'name', 'student_code', 'national_id'])
                    ->each(function (User $student) use ($name, &$results) {
                        $score = $this->nameSimilarity($name, $student->name);
                        if ($score >= 75) {
                            $results[] = $this->entityMatch('student', $student, 'name_similarity', $score, route('admin.students.show', $student));
                        }
                    });
            }
        }

        return collect($results)->unique('id')->sortByDesc('confidence')->values()->take(5)->all();
    }

    protected function findPossibleGuardians(AdmissionApplication $application): array
    {
        $matcher = app(AdmissionGuardianMatcherService::class);
        $flat = [];

        foreach ($matcher->suggestMatches($application) as $group) {
            foreach ($group['matches'] as $match) {
                $flat[] = [
                    'type' => 'guardian',
                    'id' => $match['guardian_id'],
                    'name' => $match['name'],
                    'email' => $match['email'],
                    'phone' => $match['phone'],
                    'matched_by' => $match['matched_by'],
                    'confidence' => $match['confidence'],
                    'profile_url' => route('admin.parents.show', $match['guardian_id']),
                    'read_only' => true,
                ];
            }
        }

        return collect($flat)->unique('id')->sortByDesc('confidence')->values()->take(5)->all();
    }

    protected function findDuplicateApplications(AdmissionApplication $application): array
    {
        $contact = $application->contacts->firstWhere('is_primary', true)
            ?? $application->contacts->first();
        $applicant = $application->applicants->first();

        if (! $contact && ! $applicant) {
            return [];
        }

        $query = AdmissionApplication::query()
            ->where('id', '!=', $application->id)
            ->where('status', 'open')
            ->with(['primaryContact', 'primaryApplicant']);

        $query->where(function ($q) use ($contact, $applicant) {
            if ($contact?->phone) {
                $q->orWhereHas('contacts', fn ($c) => $c->where('phone', $contact->phone));
            }
            if ($contact?->email) {
                $q->orWhereHas('contacts', fn ($c) => $c->where('email', $contact->email));
            }
            if ($applicant?->first_name) {
                $q->orWhereHas('applicants', fn ($a) => $a->where('first_name', $applicant->first_name));
            }
        });

        return $query->limit(5)->get()->map(fn (AdmissionApplication $dup) => [
            'application_id' => $dup->id,
            'reference_code' => $dup->reference_code,
            'pipeline_stage' => $dup->pipeline_stage,
            'parent_name' => $dup->primaryContact?->name,
            'student_name' => $dup->primaryApplicant?->displayName(),
            'created_at' => $dup->created_at?->toIso8601String(),
            'profile_url' => route('admin.admissions.show', $dup->id),
            'read_only' => true,
        ])->values()->all();
    }

    protected function findPossibleFamilies(AdmissionApplication $application): array
    {
        $families = [];

        foreach ($application->contacts as $contact) {
            $guardianQuery = User::query()->where('user_type', 'guardian');

            if ($contact->national_id) {
                $guardianQuery->where('national_id', $contact->national_id);
            } elseif ($contact->email) {
                $guardianQuery->where('email', $contact->email);
            } elseif ($contact->phone) {
                $guardianQuery->where('phone', $contact->phone);
            } else {
                continue;
            }

            $guardian = $guardianQuery->first();
            if (! $guardian) {
                continue;
            }

            $children = $guardian->students()->limit(5)->get(['users.id', 'users.name', 'users.student_code']);

            if ($children->isNotEmpty()) {
                $families[] = [
                    'guardian_id' => $guardian->id,
                    'guardian_name' => $guardian->name,
                    'guardian_profile_url' => route('admin.parents.show', $guardian->id),
                    'children' => $children->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'student_code' => $c->student_code,
                        'profile_url' => route('admin.students.show', $c->id),
                    ])->values(),
                    'read_only' => true,
                ];
            }
        }

        return $families;
    }

    protected function entityMatch(string $type, User $user, string $matchedBy, int $confidence, ?string $profileUrl = null): array
    {
        return [
            'type' => $type,
            'id' => $user->id,
            'name' => $user->name,
            'student_code' => $user->student_code ?? null,
            'matched_by' => $matchedBy,
            'confidence' => $confidence,
            'profile_url' => $profileUrl,
            'read_only' => true,
        ];
    }

    protected function nameSimilarity(string $a, string $b): int
    {
        similar_text(Str::lower(trim($a)), Str::lower(trim($b)), $percent);

        return (int) round($percent);
    }
}

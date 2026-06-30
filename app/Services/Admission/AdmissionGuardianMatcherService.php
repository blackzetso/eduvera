<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Read-only guardian match suggestions. Does not modify admission records.
 */
class AdmissionGuardianMatcherService
{
    /**
     * Resolve the best existing guardian for a contact (does not create users).
     *
     * @return array{user: User, matched_by: string, confidence: int}|null
     */
    public function resolveGuardianUser(AdmissionContact $contact): ?array
    {
        if ($contact->matched_guardian_id) {
            $user = User::query()
                ->where('user_type', 'guardian')
                ->find($contact->matched_guardian_id);

            if ($user) {
                return [
                    'user' => $user,
                    'matched_by' => 'manual_link',
                    'confidence' => 100,
                ];
            }
        }

        if ($contact->national_id) {
            $user = User::query()
                ->where('user_type', 'guardian')
                ->where('national_id', $contact->national_id)
                ->first();

            if ($user) {
                return ['user' => $user, 'matched_by' => 'national_id', 'confidence' => 100];
            }
        }

        if ($contact->email) {
            $user = User::query()
                ->where('user_type', 'guardian')
                ->where('email', $contact->email)
                ->first();

            if ($user) {
                return ['user' => $user, 'matched_by' => 'email', 'confidence' => 90];
            }
        }

        if ($contact->phone) {
            $normalized = $this->normalizePhone($contact->phone);
            $user = User::query()
                ->where('user_type', 'guardian')
                ->whereNotNull('phone')
                ->get(['id', 'name', 'email', 'phone', 'national_id', 'user_type'])
                ->first(fn (User $u) => $this->normalizePhone($u->phone) === $normalized);

            if ($user) {
                return ['user' => $user, 'matched_by' => 'phone', 'confidence' => 85];
            }
        }

        return null;
    }

    public function suggestMatches(AdmissionApplication $application): array
    {
        $application->load('contacts');
        $suggestions = [];

        foreach ($application->contacts as $contact) {
            $matches = [];

            if ($contact->national_id) {
                $byNationalId = User::query()
                    ->where('user_type', 'guardian')
                    ->where('national_id', $contact->national_id)
                    ->limit(3)
                    ->get(['id', 'name', 'email', 'phone', 'national_id']);

                foreach ($byNationalId as $user) {
                    $matches[$user->id] = $this->matchPayload($user, 'national_id', 100);
                }
            }

            if ($contact->email) {
                $byEmail = User::query()
                    ->where('user_type', 'guardian')
                    ->where('email', $contact->email)
                    ->limit(3)
                    ->get(['id', 'name', 'email', 'phone', 'national_id']);

                foreach ($byEmail as $user) {
                    $matches[$user->id] = $matches[$user->id] ?? $this->matchPayload($user, 'email', 90);
                }
            }

            if ($contact->phone) {
                $normalized = $this->normalizePhone($contact->phone);
                $byPhone = User::query()
                    ->where('user_type', 'guardian')
                    ->whereNotNull('phone')
                    ->limit(20)
                    ->get(['id', 'name', 'email', 'phone', 'national_id'])
                    ->filter(fn (User $u) => $this->normalizePhone($u->phone) === $normalized);

                foreach ($byPhone as $user) {
                    $matches[$user->id] = $matches[$user->id] ?? $this->matchPayload($user, 'phone', 85);
                }
            }

            if ($contact->name) {
                $candidates = User::query()
                    ->where('user_type', 'guardian')
                    ->limit(50)
                    ->get(['id', 'name', 'email', 'phone', 'national_id']);

                foreach ($candidates as $user) {
                    $score = $this->nameSimilarity($contact->name, $user->name);
                    if ($score >= 70 && ! isset($matches[$user->id])) {
                        $matches[$user->id] = $this->matchPayload($user, 'name_similarity', $score);
                    }
                }
            }

            if (! empty($matches)) {
                $suggestions[] = [
                    'contact_id' => $contact->id,
                    'contact_name' => $contact->name,
                    'matches' => collect($matches)->sortByDesc('confidence')->values()->take(5)->all(),
                ];
            }
        }

        return $suggestions;
    }

    protected function matchPayload(User $user, string $matchedBy, int $confidence): array
    {
        return [
            'guardian_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'national_id' => $user->national_id,
            'matched_by' => $matchedBy,
            'confidence' => $confidence,
            'read_only' => true,
        ];
    }

    protected function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    protected function nameSimilarity(string $a, string $b): int
    {
        similar_text(Str::lower(trim($a)), Str::lower(trim($b)), $percent);

        return (int) round($percent);
    }
}

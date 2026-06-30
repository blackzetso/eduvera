<?php

namespace App\Support\Dova;

use App\Models\User;
use Illuminate\Http\Request;

class DovaContextResolver
{
    /**
     * @return array{
     *   portal: string,
     *   role: string,
     *   authenticated: bool,
     *   path: string,
     *   page_context: string,
     *   labels: array{en: array<string, string>, ar: array<string, string>},
     *   user: array{id: int, name: string}|null
     * }
     */
    public function resolve(Request $request, ?string $clientPath = null): array
    {
        $path = $this->normalisePath($clientPath ?? $request->path());
        $user = $request->user();
        $role = $this->detectRole($user);
        $portal = $this->detectPortal($path, $user);
        $pageContext = $this->detectPageContext($path);
        $locale = app()->getLocale();

        return [
            'portal' => $portal,
            'role' => $role,
            'authenticated' => $user !== null,
            'path' => $path,
            'page_context' => $pageContext,
            'labels' => [
                'en' => $this->buildLabels('en', $portal, $role, $pageContext),
                'ar' => $this->buildLabels('ar', $portal, $role, $pageContext),
            ],
            'summary' => $this->summary($locale, $portal, $role, $pageContext),
            'user' => $user ? ['id' => $user->id, 'name' => $user->name] : null,
        ];
    }

    public function normalisePath(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '/' : '/'.$path;
    }

    public function detectRole(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        return $user->user_type ?? 'guest';
    }

    public function detectPortal(string $path, ?User $user): string
    {
        $trimmed = trim($path, '/');

        return match (true) {
            str_starts_with($trimmed, 'admin') => 'admin',
            str_starts_with($trimmed, 'teacher') => 'teacher',
            str_starts_with($trimmed, 'guardian') => 'guardian',
            str_starts_with($trimmed, 'student') => 'student',
            default => $this->portalFromRole($user),
        };
    }

    protected function portalFromRole(?User $user): string
    {
        if (! $user) {
            return 'public';
        }

        return match (true) {
            $user->isAdmin() => 'admin',
            $user->isTeacher() => 'teacher',
            $user->isGuardian() => 'guardian',
            $user->isStudent() => 'student',
            $user->isControlStaff(), $user->isSocialWorker(), $user->isNurse() => 'admin',
            $user->user_type === 'department_head' => 'admin',
            $user->user_type === 'card_reader' => 'admin',
            default => 'public',
        };
    }

    public function detectPageContext(string $path): string
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '' || $trimmed === '/') {
            return 'home';
        }

        foreach (config('dova.contexts', []) as $entry) {
            foreach ($entry['patterns'] ?? [] as $pattern) {
                if ($this->pathMatches($trimmed, $pattern)) {
                    return $entry['id'];
                }
            }
        }

        return 'general';
    }

    protected function pathMatches(string $path, string $pattern): bool
    {
        if (str_starts_with($pattern, '#')) {
            return false;
        }

        $regex = '~^'.str_replace('\*', '[^/]+', preg_quote($pattern, '~')).'($|/)~';

        return (bool) preg_match($regex, $path);
    }

    /**
     * @return array{portal: string, role: string, page: string, portal_label: string, role_label: string, page_label: string}
     */
    protected function buildLabels(string $locale, string $portal, string $role, string $pageContext): array
    {
        $portalLabels = config('dova.portal_labels', []);
        $roleLabels = config('dova.role_labels', []);
        $contextLabels = config('dova.context_labels', []);

        return [
            'portal' => $portal,
            'role' => $role,
            'page' => $pageContext,
            'portal_label' => $portalLabels[$portal][$locale] ?? $portal,
            'role_label' => $roleLabels[$role][$locale] ?? $role,
            'page_label' => $contextLabels[$pageContext][$locale] ?? ($pageContext === 'general'
                ? ($locale === 'ar' ? 'هذه الصفحة' : 'This page')
                : $pageContext),
        ];
    }

    protected function summary(string $locale, string $portal, string $role, string $pageContext): string
    {
        $labels = $this->buildLabels($locale, $portal, $role, $pageContext);
        $parts = array_filter([
            $pageContext !== 'home' && $pageContext !== 'general' ? $labels['page_label'] : null,
            $role !== 'guest' ? $labels['role_label'] : $labels['portal_label'],
        ]);

        return $parts !== []
            ? implode($locale === 'ar' ? ' · ' : ' · ', $parts)
            : ($labels['portal_label'] ?? '');
    }
}

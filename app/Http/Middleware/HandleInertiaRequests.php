<?php

namespace App\Http\Middleware;

use App\Models\LiveStream;
use App\Modules\Canteen\CanteenModule;
use App\Support\Dova\DovaCopilotService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'locale' => fn () => app()->getLocale(),
            'direction' => fn () => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'strategyCreated' => fn () => $request->session()->get('strategyCreated'),
            ],
            'canEditWebsiteCms' => fn () => (bool) ($request->user()?->isAdmin()),
            'dovaCopilot' => fn () => app(DovaCopilotService::class)->forRequest($request),
            'pendingExtraSessionsCount' => fn () =>
                LiveStream::where('extra_session_status', 'pending')->count()
                + LiveStream::whereNotNull('pending_extension_minutes')->count(),
            'adminAbilities' => fn () => $request->user()?->isAdmin()
                ? app(\App\Support\Admin\PermissionService::class)->abilitiesFor($request->user())
                : [],
            'adminRole' => fn () => $request->user()?->isAdmin()
                ? $request->user()->adminRole()
                : null,
            'adminRoleLabel' => fn () => $request->user()?->isAdmin()
                ? \App\Support\Admin\AdminRole::label($request->user()->adminRole())
                : null,
            'modules' => fn () => [
                'canteen' => CanteenModule::inertiaShare(),
            ],
        ]);
    }

}

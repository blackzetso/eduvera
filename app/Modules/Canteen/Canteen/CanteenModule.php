<?php

namespace App\Modules\Canteen;

use App\Modules\Canteen\Support\CanteenMenuRegistry;

class CanteenModule
{
    public static function enabled(): bool
    {
        return (bool) config('canteen.enabled', false);
    }

    public static function routePrefix(): string
    {
        return (string) config('canteen.module.route_prefix', 'canteen');
    }

    public static function inertiaShare(): array
    {
        if (! self::enabled()) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'id' => config('canteen.module.id', 'canteen'),
            'version' => config('canteen.module.version', '1.0.0'),
            'menu' => CanteenMenuRegistry::menu(),
            'permissions' => auth()->check()
                ? \App\Modules\Canteen\Support\CanteenPermission::forUser(auth()->user())
                : [],
        ];
    }
}

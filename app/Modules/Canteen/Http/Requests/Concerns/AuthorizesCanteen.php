<?php

namespace App\Modules\Canteen\Http\Requests\Concerns;

use App\Modules\Canteen\Support\CanteenPermission;

trait AuthorizesCanteen
{
    protected function canteenAllows(string $permission): bool
    {
        return CanteenPermission::allows($this->user(), $permission);
    }
}

<?php

namespace App\Modules\Canteen\Models;

use App\Modules\Canteen\Models\Concerns\HasCanteenAudit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

abstract class CanteenModel extends Model
{
    use HasCanteenAudit;
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;
}

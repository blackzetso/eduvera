<?php

namespace App\Modules\Canteen\Models;

class Setting extends CanteenModel
{
    protected $table = 'canteen_settings';

    protected $fillable = ['key', 'value', 'created_by', 'updated_by'];

    protected $casts = ['value' => 'array'];
}

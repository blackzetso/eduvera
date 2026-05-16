<?php

namespace App\Models;

use App\Models\FormInput;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $fillable = [
        'name'
    ];

    public function inputs()
    {
        return $this->hasMany(FormInput::class);
    }
}

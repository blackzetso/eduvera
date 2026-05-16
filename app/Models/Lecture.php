<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lecture extends Model
{
    protected $fillable = ['name','lesson_id'];

    public function files()
    {
        return $this->hasMany(File::class);
    }

}

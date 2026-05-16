<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lesson extends Model
    {
        protected $fillable = [
        'name',
        'short_description',
        'description',
        'category_id',
        'teacher_id',
        'is_featured',
        'expiry_period',
        'expire_date',
        'is_free',
        'price',
        'discount_price',
        'video_url',
        'image',
        'status'
    ];


    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function lectures()
    {
        return $this->hasMany(Lecture::class);
    }

    public function timetablePeriods()
    {
        return $this->belongsToMany(TimetablePeriod::class, 'lesson_timetable_period');
    }
}

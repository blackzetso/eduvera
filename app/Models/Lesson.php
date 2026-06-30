<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'name',
        'short_description',
        'description',
        'strategies',
        'category_id',
        'teacher_id',
        'lesson_message_template_id',
        'is_featured',
        'expiry_period',
        'expire_date',
        'publish_date',
        'is_free',
        'price',
        'discount_price',
        'video_url',
        'image',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(LessonMessageTemplate::class, 'lesson_message_template_id');
    }

    public function messageTemplates(): BelongsToMany
    {
        return $this->belongsToMany(
            LessonMessageTemplate::class,
            'lesson_strategy',
            'lesson_id',
            'lesson_message_template_id'
        );
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'lesson_category');
    }

    public function lectures(): HasMany
    {
        return $this->hasMany(Lecture::class);
    }

    public function timetablePeriods(): BelongsToMany
    {
        return $this->belongsToMany(TimetablePeriod::class, 'lesson_timetable_period');
    }
}

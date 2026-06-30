<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonMessageTemplate extends Model
{
    protected $fillable = ['title', 'body', 'status'];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 'enable');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'lesson_message_template_id');
    }

    public function assignedLessons(): BelongsToMany
    {
        return $this->belongsToMany(
            Lesson::class,
            'lesson_strategy',
            'lesson_message_template_id',
            'lesson_id'
        );
    }
}

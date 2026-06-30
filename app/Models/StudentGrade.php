<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrade extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'term_label',
        'assessment_type',
        'title',
        'score',
        'max_score',
        'assessed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'assessed_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function percentage(): float
    {
        if ((float) $this->max_score <= 0) {
            return 0;
        }

        return round(((float) $this->score / (float) $this->max_score) * 100, 1);
    }
}

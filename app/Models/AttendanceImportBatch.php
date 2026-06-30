<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceImportBatch extends Model
{
    protected $fillable = [
        'file_path',
        'original_file_name',
        'scope_type',
        'scope_id',
        'attendance_date',
        'session_type',
        'total_rows',
        'success_rows',
        'error_rows',
        'skipped_rows',
        'status',
        'validation_errors_json',
        'parsed_data_json',
        'error_summary',
        'imported_by',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'validation_errors_json' => 'array',
            'parsed_data_json' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'import_batch_id');
    }
}

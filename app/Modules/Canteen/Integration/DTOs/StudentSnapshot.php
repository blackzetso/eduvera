<?php

namespace App\Modules\Canteen\Integration\DTOs;

readonly class StudentSnapshot
{
    public function __construct(
        public string $studentIdRef,
        public string $studentName,
        public ?string $grade = null,
        public ?string $className = null,
    ) {}

    public function toArray(): array
    {
        return [
            'student_id_ref' => $this->studentIdRef,
            'student_name' => $this->studentName,
            'grade' => $this->grade,
            'class_name' => $this->className,
        ];
    }
}

<?php

namespace App\Modules\Canteen\Integration\DTOs;

readonly class GuardianStudentLinkSnapshot
{
    public function __construct(
        public string $studentIdRef,
        public string $studentName,
        public ?string $relationshipType = null,
        public bool $isPrimary = false,
    ) {}

    public function toArray(): array
    {
        return [
            'student_id_ref' => $this->studentIdRef,
            'student_name' => $this->studentName,
            'relationship_type' => $this->relationshipType,
            'is_primary' => $this->isPrimary,
        ];
    }
}

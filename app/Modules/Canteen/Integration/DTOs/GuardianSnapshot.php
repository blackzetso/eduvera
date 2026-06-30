<?php

namespace App\Modules\Canteen\Integration\DTOs;

readonly class GuardianSnapshot
{
    public function __construct(
        public string $guardianIdRef,
        public string $guardianName,
        public ?string $relationshipType = null,
        public bool $isPrimary = false,
        public bool $isFinancialResponsible = false,
    ) {}

    public function toArray(): array
    {
        return [
            'guardian_id_ref' => $this->guardianIdRef,
            'guardian_name' => $this->guardianName,
            'relationship_type' => $this->relationshipType,
            'is_primary' => $this->isPrimary,
            'is_financial_responsible' => $this->isFinancialResponsible,
        ];
    }
}

<?php

namespace App\Modules\Canteen\Integration\DTOs;

readonly class FamilyContextSnapshot
{
    /**
     * @param  list<GuardianSnapshot>  $guardians
     * @param  list<GuardianStudentLinkSnapshot>  $siblings
     */
    public function __construct(
        public string $studentIdRef,
        public ?GuardianSnapshot $primaryGuardian,
        public array $guardians,
        public array $siblings,
    ) {}

    public function toArray(): array
    {
        return [
            'student_id_ref' => $this->studentIdRef,
            'primary_guardian' => $this->primaryGuardian?->toArray(),
            'guardians' => array_map(fn (GuardianSnapshot $g) => $g->toArray(), $this->guardians),
            'siblings' => array_map(fn (GuardianStudentLinkSnapshot $s) => $s->toArray(), $this->siblings),
        ];
    }
}

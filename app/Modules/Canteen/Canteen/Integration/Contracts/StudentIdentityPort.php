<?php

namespace App\Modules\Canteen\Integration\Contracts;

use App\Modules\Canteen\Integration\DTOs\StudentSnapshot;

interface StudentIdentityPort
{
    public function search(string $query, int $limit = 20): array;

    public function findByRef(string $studentIdRef): ?StudentSnapshot;
}

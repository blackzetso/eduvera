<?php

namespace Tests\Unit\Canteen;

use App\Models\User;
use App\Modules\Canteen\Models\ParentVisibilityQueue;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenPurchaseGuardianSyncService;
use Illuminate\Support\Str;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CanteenPurchaseGuardianSyncServiceTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
    }

    public function test_sync_sale_backfills_guardian_on_sale_and_visibility_queue(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian']);
        $student = User::factory()->create(['user_type' => 'student']);
        $cashier = User::factory()->create(['user_type' => 'admin']);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'primary_guardian_user_id' => $guardian->id,
            'guardian_id_ref' => (string) $guardian->id,
            'is_active' => true,
        ]);

        $sale = Sale::query()->create([
            'sale_number' => 'CN-'.Str::upper(Str::random(6)),
            'student_id_ref' => (string) $student->id,
            'student_user_id' => $student->id,
            'student_name' => $student->name,
            'subtotal' => '10.00',
            'discount' => '0',
            'total' => '10.00',
            'status' => 'completed',
            'cashier_user_id' => $cashier->id,
            'sold_at' => now(),
        ]);

        ParentVisibilityQueue::query()->create([
            'sale_id' => $sale->id,
            'student_id_ref' => (string) $student->id,
            'payload' => ['sale_number' => $sale->sale_number],
            'visibility_status' => 'pending',
        ]);

        $synced = app(CanteenPurchaseGuardianSyncService::class)->syncSale($sale);

        $this->assertSame($guardian->id, $synced->primary_guardian_user_id);
        $this->assertSame(
            (string) $guardian->id,
            ParentVisibilityQueue::query()->where('sale_id', $sale->id)->value('guardian_id_ref')
        );
    }
}

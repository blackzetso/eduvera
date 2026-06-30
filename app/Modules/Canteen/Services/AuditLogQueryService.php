<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\AuditLog;
use App\Modules\Canteen\Models\InventoryTransaction;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\RestrictionRule;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Models\StudentRestrictionAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AuditLogQueryService
{
    /** @var array<string, string> */
    protected array $actionLabels = [
        'product.created' => 'Product Created',
        'product.updated' => 'Product Updated',
        'product.deleted' => 'Product Deleted',
        'category.created' => 'Category Created',
        'category.updated' => 'Category Updated',
        'category.deleted' => 'Category Deleted',
        'inventory.recorded' => 'Inventory Adjusted',
        'sale.pending_settlement' => 'Sale Initiated',
        'sale.settlement_confirmed' => 'Sale Completed',
        'sale.settlement_failed' => 'Sale Failed',
        'sale.voided' => 'Sale Voided',
        'student_profile.created' => 'Student Limit Created',
        'student_profile.updated' => 'Student Limit Updated',
        'restriction.assigned' => 'Rule Assigned',
        'restriction.removed' => 'Rule Removed',
        'restriction.block_triggered' => 'Restriction Block',
        'restriction.warning_triggered' => 'Restriction Warning',
        'staff.assigned' => 'Staff Assignment',
        'restriction_rule.created' => 'Rule Created',
        'student_blocked_product.added' => 'Blocked Product Added',
        'student_blocked_product.temporary_added' => 'Temporary Restriction Added',
        'student_blocked_product.expired' => 'Temporary Restriction Expired',
        'student_blocked_product.removed' => 'Restriction Removed',
        'student_blocked_category.added' => 'Blocked Category Added',
        'student_blocked_category.temporary_added' => 'Temporary Restriction Added',
        'student_blocked_category.expired' => 'Temporary Restriction Expired',
        'student_blocked_category.removed' => 'Restriction Removed',
    ];

    /** @var array<string, string> */
    protected array $entityTypeLabels = [
        'canteen_products' => 'Product',
        'canteen_categories' => 'Category',
        'canteen_inventory_transactions' => 'Inventory',
        'canteen_sales' => 'Sale',
        'canteen_student_profiles' => 'Student Limit',
        'canteen_student_restriction_assignments' => 'Restriction',
        'canteen_restriction_rules' => 'Restriction Rule',
        'canteen_staff' => 'Staff',
        'canteen_student_blocked_products' => 'Blocked Product',
        'canteen_student_blocked_categories' => 'Blocked Category',
    ];

    /** @var array<string, string> */
    protected array $fieldLabels = [
        'name' => 'Name',
        'name_ar' => 'Arabic Name',
        'sku' => 'SKU',
        'barcode' => 'Barcode',
        'selling_price' => 'Price',
        'cost_price' => 'Cost',
        'quantity_delta' => 'Quantity',
        'reason' => 'Reason',
        'total' => 'Total',
        'status' => 'Status',
        'sale_number' => 'Sale Number',
        'student_name' => 'Student',
        'student_id_ref' => 'Student ID',
        'grade' => 'Grade',
        'daily_spending_limit' => 'Daily Limit',
        'role' => 'Role',
        'is_active' => 'Active',
        'code' => 'Code',
        'rule_type' => 'Rule Type',
        'limit_override_applied' => 'Limit Override',
        'limit_override_reason' => 'Override Reason',
        'void_reason' => 'Void Reason',
        'block_source' => 'Block Source',
        'notes' => 'Notes',
        'product_id' => 'Product',
        'category_id' => 'Category',
        'starts_at' => 'Start Date',
        'expires_at' => 'Expiry Date',
        'duration_days' => 'Duration (Days)',
    ];

    /** @var list<string> */
    protected array $currencyFields = [
        'selling_price', 'cost_price', 'total', 'subtotal', 'discount', 'daily_spending_limit',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('actor:id,name')
            ->orderByDesc('created_at');

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', $term)
                    ->orWhere('subject_type', 'like', $term)
                    ->orWhere('before', 'like', $term)
                    ->orWhere('after', 'like', $term);
            });
        }

        return $query->paginate($perPage)->through(fn (AuditLog $log) => $this->summarize($log));
    }

    public function find(string $id): ?array
    {
        $log = AuditLog::query()->with('actor:id,name')->find($id);

        return $log ? $this->detail($log) : null;
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $today = now()->toDateString();

        return [
            'total_records' => AuditLog::query()->count(),
            'today_activities' => AuditLog::query()->whereDate('created_at', $today)->count(),
            'inventory_actions' => AuditLog::query()->where('action', 'like', 'inventory.%')->count(),
            'sales_actions' => AuditLog::query()->where('action', 'like', 'sale.%')->count(),
            'settings_changes' => AuditLog::query()->whereIn('action', [
                'staff.assigned',
                'restriction_rule.created',
            ])->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        $actorIds = AuditLog::query()->distinct()->pluck('actor_user_id')->filter();

        return [
            'users' => User::query()
                ->whereIn('id', $actorIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'actions' => collect($this->actionLabels)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'entity_types' => collect($this->entityTypeLabels)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    protected function summarize(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'timestamp' => $log->created_at?->toDateTimeString(),
            'user' => $log->actor?->name ?? '—',
            'action' => $log->action,
            'action_label' => $this->actionLabel($log->action),
            'entity_type' => $this->entityTypeLabel($log->subject_type),
            'entity_reference' => $this->entityReference($log),
            'description' => $this->description($log),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detail(AuditLog $log): array
    {
        $related = $this->relatedEntityLink($log);

        return [
            'id' => $log->id,
            'timestamp' => $log->created_at?->toDateTimeString(),
            'user' => $log->actor?->name ?? '—',
            'action' => $log->action,
            'action_label' => $this->actionLabel($log->action),
            'entity_type' => $this->entityTypeLabel($log->subject_type),
            'entity_reference' => $this->entityReference($log),
            'description' => $this->description($log),
            'changes' => $this->formatChanges($log->before, $log->after),
            'metadata' => $this->metadata($log),
            'related_link' => $related,
        ];
    }

    public function actionLabel(string $action): string
    {
        return $this->actionLabels[$action] ?? ucwords(str_replace(['.', '_'], ' ', $action));
    }

    public function entityTypeLabel(string $subjectType): string
    {
        return $this->entityTypeLabels[$subjectType] ?? ucwords(str_replace('_', ' ', $subjectType));
    }

    protected function entityReference(AuditLog $log): string
    {
        $data = $log->after ?? $log->before ?? [];

        return match ($log->subject_type) {
            'canteen_sales' => $data['sale_number'] ?? $data['student_name'] ?? 'Sale',
            'canteen_products' => $data['name'] ?? $data['sku'] ?? 'Product',
            'canteen_categories' => $data['name'] ?? 'Category',
            'canteen_inventory_transactions' => trim(
                ($data['reason'] ?? 'Adjustment').' ('.($data['quantity_delta'] ?? '—').')'
            ),
            'canteen_student_profiles' => $data['student_name'] ?? $data['student_id_ref'] ?? 'Student',
            'canteen_student_restriction_assignments' => $data['student_id_ref'] ?? 'Restriction',
            'canteen_restriction_rules' => $data['name'] ?? $data['code'] ?? 'Rule',
            'canteen_staff' => ucfirst($data['role'] ?? 'Staff'),
            'canteen_student_blocked_products' => $data['student_id_ref'] ?? 'Blocked Product',
            'canteen_student_blocked_categories' => $data['student_id_ref'] ?? 'Blocked Category',
            default => $this->entityTypeLabel($log->subject_type),
        };
    }

    protected function description(AuditLog $log): string
    {
        $ref = $this->entityReference($log);
        $label = $this->actionLabel($log->action);

        if (str_contains($log->action, 'price') || $this->hasFieldChange($log, 'selling_price')) {
            return "Price changed for {$ref}";
        }

        return "{$label}: {$ref}";
    }

    protected function hasFieldChange(AuditLog $log, string $field): bool
    {
        $before = $log->before[$field] ?? null;
        $after = $log->after[$field] ?? null;

        return $before !== null && $after !== null && $before != $after;
    }

    /**
     * @return list<array{field: string, label: string, before: ?string, after: ?string, formatted: string}>
     */
    protected function formatChanges(?array $before, ?array $after): array
    {
        $before = $before ?? [];
        $after = $after ?? [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $skip = ['id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'metadata', 'items'];

        $changes = [];

        foreach ($keys as $field) {
            if (in_array($field, $skip, true)) {
                continue;
            }

            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($old == $new) {
                continue;
            }

            if ($old === null && $new === null) {
                continue;
            }

            $label = $this->fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
            $formattedBefore = $this->formatValue($field, $old);
            $formattedAfter = $this->formatValue($field, $new);

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'before' => $formattedBefore,
                'after' => $formattedAfter,
                'formatted' => $formattedBefore && $formattedAfter
                    ? "{$formattedBefore} → {$formattedAfter}"
                    : ($formattedAfter ?: $formattedBefore),
            ];
        }

        return $changes;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    protected function metadata(AuditLog $log): array
    {
        $items = [];

        if ($log->ip_address) {
            $items[] = ['label' => 'IP Address', 'value' => $log->ip_address];
        }

        $snapshot = $log->after ?? $log->before ?? [];

        if (! empty($snapshot['limit_override_applied'])) {
            $items[] = [
                'label' => 'Limit Override',
                'value' => $snapshot['limit_override_reason'] ?? 'Applied',
            ];
        }

        if (! empty($snapshot['void_reason'])) {
            $items[] = ['label' => 'Void Reason', 'value' => (string) $snapshot['void_reason']];
        }

        return $items;
    }

    protected function formatValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (in_array($field, $this->currencyFields, true) && is_numeric($value)) {
            return number_format((float) $value, 2).' EGP';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * @return array{label: string, url: string}|null
     */
    protected function relatedEntityLink(AuditLog $log): ?array
    {
        return match ($log->subject_type) {
            'canteen_products' => Product::query()->whereKey($log->subject_id)->exists()
                ? ['label' => 'View Product', 'url' => route('canteen.products.edit', $log->subject_id)]
                : null,
            'canteen_sales' => Sale::query()->whereKey($log->subject_id)->exists()
                ? ['label' => 'View Sale', 'url' => route('canteen.transactions.show', $log->subject_id)]
                : null,
            'canteen_inventory_transactions' => $this->inventoryLink($log),
            'canteen_student_profiles' => StudentProfile::query()->whereKey($log->subject_id)->exists()
                ? ['label' => 'View Student Limits', 'url' => route('canteen.student-limits.index')]
                : null,
            'canteen_student_restriction_assignments' => StudentRestrictionAssignment::query()->whereKey($log->subject_id)->exists()
                ? ['label' => 'View Student Limits', 'url' => route('canteen.student-limits.index')]
                : null,
            'canteen_student_blocked_products' => ['label' => 'View Student Limits', 'url' => route('canteen.student-limits.index', ['tab' => 'blocked'])],
            'canteen_student_blocked_categories' => ['label' => 'View Student Limits', 'url' => route('canteen.student-limits.index', ['tab' => 'blocked'])],
            'canteen_restriction_rules' => RestrictionRule::query()->whereKey($log->subject_id)->exists()
                ? ['label' => 'View Settings', 'url' => route('canteen.settings.index')]
                : null,
            'canteen_staff' => Staff::query()->whereKey($log->subject_id)->exists()
                ? ['label' => 'View Settings', 'url' => route('canteen.settings.index')]
                : null,
            default => null,
        };
    }

    /**
     * @return array{label: string, url: string}|null
     */
    protected function inventoryLink(AuditLog $log): ?array
    {
        $tx = InventoryTransaction::query()->find($log->subject_id);
        if (! $tx) {
            return null;
        }

        $productExists = Product::query()->whereKey($tx->product_id)->exists();
        if (! $productExists) {
            return null;
        }

        return [
            'label' => 'View Inventory Ledger',
            'url' => route('canteen.inventory.ledger', $tx->product_id),
        ];
    }
}

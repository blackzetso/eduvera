<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\RestrictionRule;
use App\Modules\Canteen\Models\Setting;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Services\AuditService;
use App\Modules\Canteen\Services\CanteenSettingsService;
use App\Modules\Canteen\Support\CanteenSettingKeys;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(
        protected AuditService $audit,
        protected CanteenSettingsService $settings,
    ) {}

    public function index()
    {
        return Inertia::render('Canteen/Settings/Index', [
            'staff' => Staff::query()->with('user:id,name,email')->get(),
            'rules' => RestrictionRule::query()->orderBy('name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'settings' => Setting::query()->pluck('value', 'key'),
            'tagSuggestions' => ['soda', 'chocolate', 'chips', 'healthy', 'restricted_default'],
        ]);
    }

    public function storeStaff(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:manager,cashier'],
        ]);

        $staff = Staff::query()->updateOrCreate(
            ['user_id' => $data['user_id']],
            ['role' => $data['role'], 'is_active' => true],
        );

        $this->audit->log('staff.assigned', $staff, after: $staff->toArray());

        return back()->with('success', 'Staff member saved.');
    }

    public function storeRule(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:canteen_restriction_rules,code'],
            'name' => ['required', 'string', 'max:255'],
            'rule_type' => ['required', 'in:block_category,block_tag,require_tag,block_product,max_qty_per_day'],
            'severity' => ['required', 'in:block,warn'],
            'config' => ['required', 'array'],
            'config.tags' => ['nullable', 'array'],
            'config.tags.*' => ['string', 'max:50'],
            'config.category_slugs' => ['nullable', 'array'],
            'config.category_slugs.*' => ['string', 'max:100'],
            'config.product_ids' => ['nullable', 'array'],
            'config.product_ids.*' => ['uuid', 'exists:canteen_products,id'],
            'config.max' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rule = RestrictionRule::query()->create($data + ['is_active' => true]);
        $this->audit->log('restriction_rule.created', $rule, after: $rule->toArray());

        return back()->with('success', 'Restriction rule created.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'default_daily_limit' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->settings->setMany([
            CanteenSettingKeys::DEFAULT_DAILY_LIMIT => $data['default_daily_limit'] ?? null,
            CanteenSettingKeys::LOW_STOCK_THRESHOLD => $data['low_stock_threshold'] ?? null,
        ]);

        return back()->with('success', 'Settings saved.');
    }
}

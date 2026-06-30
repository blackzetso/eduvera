<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\RestrictionRule;
use App\Modules\Canteen\Models\StudentBlockedCategory;
use App\Modules\Canteen\Models\StudentBlockedProduct;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Models\StudentRestrictionAssignment;
use App\Modules\Canteen\Services\AuditService;
use App\Modules\Canteen\Services\DailyLimitService;
use App\Modules\Canteen\Services\StudentBlockService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentLimitController extends Controller
{
    public function __construct(
        protected DailyLimitService $dailyLimit,
        protected AuditService $audit,
        protected StudentBlockService $studentBlocks,
    ) {}

    public function index(Request $request)
    {
        $profiles = StudentProfile::query()
            ->when($request->search, fn ($q) => $q
                ->where('student_name', 'like', '%'.$request->search.'%')
                ->orWhere('student_id_ref', 'like', '%'.$request->search.'%'))
            ->orderBy('student_name')
            ->paginate(20);

        $refs = $profiles->getCollection()->pluck('student_id_ref');
        $assignments = StudentRestrictionAssignment::query()
            ->with('rule:id,code,name,rule_type,severity,is_active')
            ->whereIn('student_id_ref', $refs)
            ->get()
            ->groupBy('student_id_ref');

        $parentBlocks = $this->studentBlocks->blocksForStudents($refs);

        $profiles->getCollection()->transform(function (StudentProfile $p) use ($assignments, $parentBlocks) {
            $p->setAttribute('spent_today', $this->dailyLimit->spentToday($p->student_id_ref));
            $p->setAttribute('remaining_today', $this->dailyLimit->remaining($p));
            $p->setAttribute('restrictions', ($assignments[$p->student_id_ref] ?? collect())
                ->map(fn (StudentRestrictionAssignment $a) => [
                    'id' => $a->id,
                    'rule_id' => $a->rule_id,
                    'rule_name' => $a->rule?->name,
                    'rule_code' => $a->rule?->code,
                    'rule_type' => $a->rule?->rule_type,
                    'severity' => $a->rule?->severity,
                    'is_active' => (bool) $a->rule?->is_active,
                    'effective_from' => $a->effective_from?->toDateString(),
                    'effective_to' => $a->effective_to?->toDateString(),
                ])->values()->all());
            $p->setAttribute('blocked_products', $parentBlocks['products'][$p->student_id_ref] ?? []);
            $p->setAttribute('blocked_categories', $parentBlocks['categories'][$p->student_id_ref] ?? []);

            return $p;
        });

        return Inertia::render('Canteen/StudentLimits/Index', [
            'profiles' => $profiles,
            'rules' => RestrictionRule::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'rule_type', 'severity']),
            'products' => Product::query()
                ->where('is_active', true)
                ->with('category:id,name,name_ar')
                ->orderBy('name')
                ->get(['id', 'name', 'name_ar', 'category_id', 'sku']),
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'name_ar', 'slug']),
            'blockSources' => [
                ['value' => StudentBlockService::SOURCE_PARENT_REQUEST, 'label' => 'طلب ولي الأمر'],
                ['value' => StudentBlockService::SOURCE_ADMIN, 'label' => 'إداري'],
            ],
            'filters' => $request->only(['search', 'tab']),
        ]);
    }

    public function storeProfile(Request $request)
    {
        $data = $request->validate([
            'student_id_ref' => ['required', 'string', 'max:100', 'unique:canteen_student_profiles,student_id_ref'],
            'student_name' => ['required', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:100'],
            'class_name' => ['nullable', 'string', 'max:100'],
            'daily_spending_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $profile = StudentProfile::query()->create($data + ['is_active' => true]);
        $this->audit->log('student_profile.created', $profile, after: $profile->toArray());

        return back()->with('success', 'Student profile saved.');
    }

    public function updateProfile(Request $request, StudentProfile $profile)
    {
        $data = $request->validate([
            'daily_spending_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $profile->toArray();
        $profile->update($data);
        $this->audit->log('student_profile.updated', $profile, before: $before, after: $profile->fresh()->toArray());

        return back()->with('success', 'Student limits updated.');
    }

    public function assignRestriction(Request $request)
    {
        $data = $request->validate([
            'student_id_ref' => ['required', 'string'],
            'rule_id' => ['required', 'uuid', 'exists:canteen_restriction_rules,id'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $assignment = StudentRestrictionAssignment::query()->updateOrCreate(
            ['student_id_ref' => $data['student_id_ref'], 'rule_id' => $data['rule_id']],
            [
                'assigned_by' => auth()->id(),
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
            ],
        );

        $this->audit->log('restriction.assigned', $assignment->load('rule'), after: $assignment->toArray());

        return back()->with('success', 'Restriction assigned.');
    }

    public function removeRestriction(StudentRestrictionAssignment $assignment)
    {
        $before = $assignment->load('rule')->toArray();
        $assignment->delete();

        $this->audit->log('restriction.removed', $assignment, before: $before);

        return back()->with('success', 'Restriction removed.');
    }

    public function storeBlockedProduct(Request $request)
    {
        $data = $request->validate([
            'student_id_ref' => ['required', 'string', 'exists:canteen_student_profiles,student_id_ref'],
            'product_id' => ['required', 'uuid', 'exists:canteen_products,id'],
            'block_source' => ['nullable', 'string', 'in:parent_request,admin'],
            'restriction_type' => ['required', 'string', 'in:permanent,temporary'],
            'duration_days' => ['required_if:restriction_type,temporary', 'nullable', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->studentBlocks->blockProduct($data['student_id_ref'], $data['product_id'], $data);

        return back()->with('success', 'Product blocked for student.');
    }

    public function removeBlockedProduct(StudentBlockedProduct $studentBlockedProduct)
    {
        $this->studentBlocks->unblockProduct($studentBlockedProduct);

        return back()->with('success', 'Product block removed.');
    }

    public function storeBlockedCategory(Request $request)
    {
        $data = $request->validate([
            'student_id_ref' => ['required', 'string', 'exists:canteen_student_profiles,student_id_ref'],
            'category_id' => ['required', 'uuid', 'exists:canteen_categories,id'],
            'block_source' => ['nullable', 'string', 'in:parent_request,admin'],
            'restriction_type' => ['required', 'string', 'in:permanent,temporary'],
            'duration_days' => ['required_if:restriction_type,temporary', 'nullable', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->studentBlocks->blockCategory($data['student_id_ref'], $data['category_id'], $data);

        return back()->with('success', 'Category blocked for student.');
    }

    public function removeBlockedCategory(StudentBlockedCategory $studentBlockedCategory)
    {
        $this->studentBlocks->unblockCategory($studentBlockedCategory);

        return back()->with('success', 'Category block removed.');
    }
}

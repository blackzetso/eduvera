<?php

namespace App\Services;

use App\Models\DepartmentPlan;
use App\Models\DepartmentPlanItem;
use App\Models\DepartmentPlanStaffing;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DepartmentPlanService
{
    public function getOrCreateForTimetable(Timetable $timetable, ?User $creator = null, ?string $departmentLabel = null): DepartmentPlan
    {
        $label = $departmentLabel ?? 'قسم عام';

        $plan = DepartmentPlan::query()
            ->where('timetable_id', $timetable->id)
            ->where('department_label', $label)
            ->where('status', '!=', 'archived')
            ->first();

        if ($plan) {
            return $plan->load(['items.subject', 'items.category', 'staffing.teacher', 'staffing.subject', 'staffing.category']);
        }

        return DepartmentPlan::create([
            'timetable_id' => $timetable->id,
            'name' => 'خطة '.$label,
            'department_label' => $label,
            'status' => 'draft',
            'created_by' => $creator?->id,
        ])->load(['items.subject', 'items.category', 'staffing.teacher', 'staffing.subject', 'staffing.category']);
    }

    /**
     * @param  array<int, array{subject_id:int, category_id?:int|null, required_periods:int}>  $items
     */
    public function syncItems(DepartmentPlan $plan, array $items): DepartmentPlan
    {
        DB::transaction(function () use ($plan, $items) {
            foreach ($items as $row) {
                DepartmentPlanItem::updateOrCreate(
                    [
                        'department_plan_id' => $plan->id,
                        'subject_id' => $row['subject_id'],
                        'category_id' => $row['category_id'] ?? null,
                    ],
                    ['required_periods' => (int) ($row['required_periods'] ?? 0)]
                );
            }
        });

        $this->syncPlanToTimetableSettings($plan->fresh(['items', 'staffing']));

        return $plan->fresh(['items.subject', 'items.category', 'staffing.teacher', 'staffing.subject', 'staffing.category']);
    }

    /**
     * @param  array<int, array{teacher_id:int, subject_id:int, category_id?:int|null, allocated_periods:int}>  $rows
     */
    public function syncStaffing(DepartmentPlan $plan, array $rows): DepartmentPlan
    {
        DB::transaction(function () use ($plan, $rows) {
            $plan->staffing()->delete();
            foreach ($rows as $row) {
                if ((int) ($row['allocated_periods'] ?? 0) <= 0) {
                    continue;
                }
                DepartmentPlanStaffing::create([
                    'department_plan_id' => $plan->id,
                    'teacher_id' => $row['teacher_id'],
                    'subject_id' => $row['subject_id'],
                    'category_id' => $row['category_id'] ?? null,
                    'allocated_periods' => (int) $row['allocated_periods'],
                ]);
            }
        });

        $this->syncPlanToTimetableSettings($plan->fresh(['items', 'staffing']));

        return $plan->fresh(['items.subject', 'items.category', 'staffing.teacher', 'staffing.subject', 'staffing.category']);
    }

    public function buildPlanRows(DepartmentPlan $plan): array
    {
        $plan->loadMissing(['items.subject', 'items.category', 'staffing.teacher', 'staffing.subject']);

        $bySubject = [];

        foreach ($plan->items as $item) {
            $sid = $item->subject_id;
            if (! isset($bySubject[$sid])) {
                $bySubject[$sid] = [
                    'subject_id' => $sid,
                    'subject_name' => $item->subject?->name,
                    'required_periods' => 0,
                    'allocated_periods' => 0,
                    'teachers' => [],
                ];
            }
            $bySubject[$sid]['required_periods'] += (int) $item->required_periods;
        }

        foreach ($plan->staffing as $row) {
            $sid = $row->subject_id;
            if (! isset($bySubject[$sid])) {
                $bySubject[$sid] = [
                    'subject_id' => $sid,
                    'subject_name' => $row->subject?->name,
                    'required_periods' => 0,
                    'allocated_periods' => 0,
                    'teachers' => [],
                ];
            }
            $bySubject[$sid]['allocated_periods'] += (int) $row->allocated_periods;
            $bySubject[$sid]['teachers'][] = [
                'teacher_id' => $row->teacher_id,
                'teacher_name' => $row->teacher?->name,
                'allocated_periods' => (int) $row->allocated_periods,
                'category_id' => $row->category_id,
            ];
        }

        return array_values(array_map(function (array $row) {
            $remaining = max(0, $row['required_periods'] - $row['allocated_periods']);
            $coverage = $row['required_periods'] > 0
                ? round(min(100, ($row['allocated_periods'] / $row['required_periods']) * 100), 1)
                : 100;

            return array_merge($row, [
                'remaining_periods' => $remaining,
                'coverage_percent' => $coverage,
                'status' => $remaining > 0 ? 'need_teacher' : ($row['allocated_periods'] > $row['required_periods'] ? 'surplus' : 'complete'),
                'status_label' => $remaining > 0
                    ? 'يحتاج معلم'
                    : ($row['allocated_periods'] > $row['required_periods'] ? 'زيادة في التغطية' : 'مكتمل'),
            ]);
        }, $bySubject));
    }

    public function workforceReport(DepartmentPlan $plan): array
    {
        $rows = $this->buildPlanRows($plan);

        return [
            'department' => $plan->department_label,
            'subjects' => array_map(function (array $row) {
                $shortage = max(0, $row['required_periods'] - $row['allocated_periods']);
                $surplus = max(0, $row['allocated_periods'] - $row['required_periods']);

                return [
                    'subject_name' => $row['subject_name'],
                    'required_periods' => $row['required_periods'],
                    'available_periods' => $row['allocated_periods'],
                    'shortage' => $shortage,
                    'surplus' => $surplus,
                    'coverage_percent' => $row['coverage_percent'],
                    'status_label' => $shortage > 0
                        ? ($shortage >= 8 ? 'يحتاج معلم إضافي' : 'نقص في التغطية')
                        : ($surplus > 0 ? "زيادة: {$surplus} حصة" : 'مكتمل'),
                ];
            }, $rows),
        ];
    }

    /**
     * @param  Collection<int, DepartmentPlan>  $plans
     */
    public function executiveSummary(Collection $plans): array
    {
        $out = [];
        foreach ($plans as $plan) {
            $rows = $this->buildPlanRows($plan);
            $totalShort = 0;
            $totalSurplus = 0;
            foreach ($rows as $row) {
                $totalShort += max(0, $row['required_periods'] - $row['allocated_periods']);
                $totalSurplus += max(0, $row['allocated_periods'] - $row['required_periods']);
            }

            if ($totalShort > 0) {
                $status = 'shortage';
                $label = 'نقص: '.$totalShort.' حصة';
            } elseif ($totalSurplus > 0) {
                $status = 'surplus';
                $label = 'زيادة: '.$totalSurplus.' حصة';
            } else {
                $status = 'complete';
                $label = 'مكتمل';
            }

            $out[] = [
                'department' => $plan->department_label,
                'status' => $status,
                'label' => $label,
            ];
        }

        return $out;
    }

    public function syncPlanToTimetableSettings(DepartmentPlan $plan): void
    {
        $plan->loadMissing(['staffing', 'items']);
        $timetable = $plan->timetable ?? Timetable::find($plan->timetable_id);
        if (! $timetable) {
            return;
        }

        $settings = is_array($timetable->settings) ? $timetable->settings : [];
        $distribution = [];

        foreach ($plan->staffing as $row) {
            $key = (string) $row->subject_id;
            if (! isset($distribution[$key])) {
                $distribution[$key] = [];
            }
            $distribution[$key][] = [
                'teacher_id' => $row->teacher_id,
                'periods' => (int) $row->allocated_periods,
                'category_id' => $row->category_id,
                'source' => 'department_plan',
            ];
        }

        $settings['department_plan'] = [
            'plan_id' => $plan->id,
            'department_label' => $plan->department_label,
            'updated_at' => now()->toIso8601String(),
            'teacher_load_distribution' => $distribution,
        ];

        $timetable->update(['settings' => $settings]);
    }

    /**
     * @return array<int, DepartmentPlan>
     */
    public function activePlansForTimetable(Timetable $timetable): Collection
    {
        return DepartmentPlan::query()
            ->where('timetable_id', $timetable->id)
            ->whereIn('status', ['draft', 'active'])
            ->with(['items', 'staffing'])
            ->get();
    }
}

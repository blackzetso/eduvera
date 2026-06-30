<?php

namespace App\Services;

use App\Models\Category;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Support\Collection;

class TimetableRoleAssignmentService
{
    private const STAGE_KEYWORDS = [
        'kg' => ['رياض', 'kg', 'أطفال', 'kindergarten'],
        'primary' => ['ابتد', 'primary'],
        'middle' => ['إعداد', 'متوسط', 'middle', 'prep'],
        'high' => ['ثانو', 'secondary', 'high'],
        'university' => ['جامع', 'university'],
    ];

    public function buildAdminContext(): array
    {
        return [
            'role' => 'admin',
            'can_self_assign' => false,
            'subject_ids' => [],
            'visible_category_ids' => [],
            'subjects' => [],
            'teachers' => [],
        ];
    }

    public function buildTeacherContext(User $teacher, Collection $categories): array
    {
        $subjects = $teacher->teachingSubjects();
        $subjectIds = $subjects->pluck('id')->all();
        $leafIds = $this->collectLeafCategoryIds($categories);
        $visibleCategoryIds = $this->visibleCategoryIdsForTeacher($teacher, $leafIds, $categories);

        return [
            'role' => 'teacher',
            'teacher_id' => $teacher->id,
            'can_self_assign' => true,
            'subject_ids' => $subjectIds,
            'visible_category_ids' => $visibleCategoryIds,
            'subjects' => $subjects->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ])->values()->all(),
            'teachers' => [[
                'id' => $teacher->id,
                'name' => $teacher->name,
                'subjects' => $subjects,
            ]],
        ];
    }

    public function canTeacherSelfAssignPeriod(User $teacher, TimetablePeriod $period): bool
    {
        if ($period->assignments()->where('type', 'main')->exists()) {
            return false;
        }

        if ($teacher->teachingSubjects()->isEmpty()) {
            return false;
        }

        $categories = Category::with('parent.parent')->get();
        $leafIds = $this->collectLeafCategoryIds($categories);
        $visible = $this->visibleCategoryIdsForTeacher($teacher, $leafIds, $categories);

        if ($period->category_id && $visible && ! in_array((int) $period->category_id, $visible, true)) {
            return false;
        }

        return true;
    }

    public function teacherTeachesSubject(User $teacher, int $subjectId): bool
    {
        return $teacher->teachesSubject($subjectId);
    }

    /**
     * @param  array<int>  $leafCategoryIds
     * @return array<int>
     */
    public function visibleCategoryIdsForTeacher(User $teacher, array $leafCategoryIds, Collection $categories): array
    {
        $stageIds = $this->inferTeacherStageIds($teacher);
        if (empty($stageIds)) {
            return $leafCategoryIds;
        }

        $byId = $categories->keyBy('id');

        return array_values(array_filter($leafCategoryIds, function (int $id) use ($byId, $stageIds) {
            $cat = $byId->get($id);
            if (! $cat) {
                return false;
            }

            return $this->categoryMatchesStages($cat, $stageIds);
        }));
    }

    public function inferTeacherStageIds(User $teacher): array
    {
        $blob = mb_strtolower(trim(($teacher->department ?? '').' '.($teacher->job_title ?? '')));
        $matched = [];
        foreach (self::STAGE_KEYWORDS as $stageId => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($blob, mb_strtolower($keyword))) {
                    $matched[] = $stageId;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    public function categoryMatchesStages(Category $category, array $stageIds): bool
    {
        $labels = $this->categoryLabelChain($category);

        foreach ($stageIds as $stageId) {
            foreach (self::STAGE_KEYWORDS[$stageId] ?? [] as $keyword) {
                if ($keyword !== '' && $this->labelChainContains($labels, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int>
     */
    private function collectLeafCategoryIds(Collection $categories): array
    {
        $ids = [];
        foreach ($categories as $category) {
            $ids = array_merge($ids, $category->getLeafDescendants()->pluck('id')->all());
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function categoryLabelChain(Category $category): array
    {
        $labels = [mb_strtolower($category->name ?? '')];
        $node = $category;
        while ($node->parent) {
            $node = $node->parent;
            $labels[] = mb_strtolower($node->name ?? '');
        }

        return $labels;
    }

    /**
     * @param  list<string>  $labels
     */
    private function labelChainContains(array $labels, string $keyword): bool
    {
        $kw = mb_strtolower($keyword);
        foreach ($labels as $label) {
            if ($label !== '' && str_contains($label, $kw)) {
                return true;
            }
        }

        return false;
    }
}

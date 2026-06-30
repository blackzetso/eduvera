<?php

namespace App\Http\Controllers\teacher;

use Inertia\Inertia;
use App\Models\Lesson;
use App\Models\Category;
use App\Models\TimetableAssignment;
use App\Models\TimetableDay;
use App\Models\TimetablePeriod;
use App\Models\LessonMessageTemplate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LessonController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user();

        $lessons = Lesson::where('teacher_id', $teacher->id)
            ->with(['category', 'classes'])
            ->when(
                $request->search,
                fn($q) => $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Teacher/Lessons/Index', [
            'lessons' => $lessons,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(Request $request)
    {
        $teacher = $request->user();

        $subjects         = $teacher->teachingSubjects();
        $messageTemplates = LessonMessageTemplate::enabled()->get(['id', 'title']);

        return Inertia::render('Teacher/Lessons/Create', [
            'subjects'         => $subjects,
            'messageTemplates' => $messageTemplates,
            'fromPeriod'       => null,
        ]);
    }

    public function createFromPeriod(Request $request, TimetablePeriod $period)
    {
        $teacher = $request->user();

        // Ensure teacher is assigned to this period
        $assignment = TimetableAssignment::where('teacher_id', $teacher->id)
            ->where('timetable_period_id', $period->id)
            ->with(['subject', 'period.category.parent.parent'])
            ->firstOrFail();

        $subjects         = $teacher->teachingSubjects();
        $messageTemplates = LessonMessageTemplate::enabled()->get(['id', 'title']);

        // Load TimetableDay via FK directly to avoid name collision with the raw `day` column
        $timetableDay = $period->timetable_day_id
            ? TimetableDay::find($period->timetable_day_id)
            : null;

        // Pre-fill data from the period
        $category = $assignment->period?->category;
        $grade    = $category?->parent;
        $stage    = $grade?->parent;

        $fromPeriod = [
            'id'            => $period->id,
            'subject_id'    => $assignment->subject_id,
            'subject_name'  => $assignment->subject?->name,
            'category_id'   => $period->category_id,
            'category_name' => $category?->name,
            'stage_id'      => $stage?->id,
            'stage_name'    => $stage?->name,
            'grade_id'      => $grade?->id,
            'grade_name'    => $grade?->name,
            'day_name'      => $timetableDay?->day_name,
            'time_from'     => $period->time_from,
            'time_to'       => $period->time_to,
        ];

        // Load the category tree for the subject so the cascade works
        $subjectCategories = [];
        if ($assignment->subject_id) {
            $subjectCategories = $this->getCategoriesForSubject($assignment->subject_id);
        }

        return Inertia::render('Teacher/Lessons/Create', [
            'subjects'           => $subjects,
            'messageTemplates'   => $messageTemplates,
            'fromPeriod'         => $fromPeriod,
            'subjectCategories'  => $subjectCategories,
        ]);
    }

    public function store(Request $request)
    {
        $teacher = $request->user();

        $data = $request->validate([
            'name'                       => 'required|string|max:255',
            'short_description'          => 'nullable|string',
            'description'                => 'nullable|string',
            'strategies'                 => 'nullable|string',
            'subject_id'                 => 'required|integer|exists:subjects,id',
            'class_ids'                  => 'nullable|array',
            'class_ids.*'                    => 'integer|exists:categories,id',
            'lesson_message_template_ids'    => 'nullable|array',
            'lesson_message_template_ids.*'  => 'integer|exists:lesson_message_templates,id',
            'timetable_period_id'            => 'nullable|integer|exists:timetable_periods,id',
            'expiry_period'              => 'required|string|in:lifetime,limited',
            'expire_date'                => 'nullable|date',
            'publish_date'               => 'nullable|date',
            'is_featured'                => 'boolean',
            'is_free'                    => 'boolean',
            'price'                      => 'nullable|numeric',
            'discount_price'             => 'nullable|numeric',
            'image'                      => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Permission: teacher must own the subject
        abort_if(! $teacher->teachesSubject((int) $data['subject_id']), 403, 'غير مصرح لك بإنشاء درس لهذه المادة.');

        // Determine primary category_id from the first selected class or timetable period
        $primaryCategoryId = null;
        if (!empty($data['class_ids'])) {
            $primaryCategoryId = $data['class_ids'][0];
        } elseif (!empty($data['timetable_period_id'])) {
            $period = TimetablePeriod::find($data['timetable_period_id']);
            $primaryCategoryId = $period?->category_id;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('lessons', 'public');
        }

        $lesson = Lesson::create([
            'name'                       => $data['name'],
            'short_description'          => $data['short_description'] ?? null,
            'description'                => $data['description'] ?? null,
            'strategies'                 => $data['strategies'] ?? null,
            'category_id'                => $primaryCategoryId,
            'teacher_id'                 => $teacher->id,
            'lesson_message_template_id' => ! empty($data['lesson_message_template_ids']) ? $data['lesson_message_template_ids'][0] : null,
            'expiry_period'              => $data['expiry_period'],
            'expire_date'                => $data['expire_date'] ?? null,
            'publish_date'               => $data['publish_date'] ?? null,
            'is_featured'                => $data['is_featured'] ?? false,
            'is_free'                    => $data['is_free'] ?? false,
            'price'                      => $data['price'] ?? null,
            'discount_price'             => $data['discount_price'] ?? null,
            'image'                      => $data['image'] ?? null,
            'status'                     => 'enable',
        ]);

        // Sync multi-class assignment
        if (!empty($data['class_ids'])) {
            $lesson->classes()->sync($data['class_ids']);
        }

        $lesson->messageTemplates()->sync($data['lesson_message_template_ids'] ?? []);

        // Link to timetable period if provided
        if (!empty($data['timetable_period_id'])) {
            $lesson->timetablePeriods()->sync([$data['timetable_period_id']]);
        }

        return redirect()->route('teacher.lessons.edit', $lesson->id)
            ->with('success', 'تم إنشاء الدرس بنجاح — يمكنك الآن إضافة المحتوى');
    }

    public function edit(Request $request, int $lesson)
    {
        $teacher = $request->user();

        $lesson = Lesson::where('teacher_id', $teacher->id)
            ->with(['lectures.files'])
            ->findOrFail($lesson);

        return Inertia::render('Teacher/Lessons/Edit', [
            'lesson' => $lesson,
        ]);
    }

    public function editDetails(Request $request, int $lesson)
    {
        $teacher = $request->user();

        $lesson = Lesson::where('teacher_id', $teacher->id)->findOrFail($lesson);

        $subjects         = $teacher->teachingSubjects();
        $messageTemplates = LessonMessageTemplate::enabled()->get(['id', 'title']);
        $lesson->load(['category', 'classes', 'timetablePeriods', 'messageTemplates']);

        $subjectId = null;
        if ($lesson->category_id) {
            $subjectId = $lesson->category->subjects()
                ->whereIn('subjects.id', $teacher->teachingSubjectIds())
                ->value('subjects.id');
        }

        $subjectCategories = $subjectId ? $this->getCategoriesForSubject($subjectId) : [];

        return Inertia::render('Teacher/Lessons/Create', [
            'lesson'             => $lesson,
            'subjects'           => $subjects,
            'messageTemplates'   => $messageTemplates,
            'fromPeriod'         => null,
            'subjectCategories'  => $subjectCategories,
            'editingSubjectId'   => $subjectId,
        ]);
    }

    public function update(Request $request, int $lesson)
    {
        $teacher = $request->user();

        $lesson = Lesson::where('teacher_id', $teacher->id)->findOrFail($lesson);

        $data = $request->validate([
            'name'                       => 'required|string|max:255',
            'short_description'          => 'nullable|string',
            'description'                => 'nullable|string',
            'strategies'                 => 'nullable|string',
            'subject_id'                 => 'required|integer|exists:subjects,id',
            'class_ids'                  => 'nullable|array',
            'class_ids.*'                    => 'integer|exists:categories,id',
            'lesson_message_template_ids'    => 'nullable|array',
            'lesson_message_template_ids.*'  => 'integer|exists:lesson_message_templates,id',
            'expiry_period'              => 'required|string|in:lifetime,limited',
            'expire_date'                => 'nullable|date',
            'publish_date'               => 'nullable|date',
            'is_featured'                => 'boolean',
            'is_free'                    => 'boolean',
            'price'                      => 'nullable|numeric',
            'discount_price'             => 'nullable|numeric',
            'image'                      => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        abort_if(! $teacher->teachesSubject((int) $data['subject_id']), 403, 'غير مصرح لك.');

        $primaryCategoryId = !empty($data['class_ids']) ? $data['class_ids'][0] : $lesson->category_id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('lessons', 'public');
        } else {
            $data['image'] = $lesson->image;
        }

        $lesson->update([
            'name'                       => $data['name'],
            'short_description'          => $data['short_description'] ?? null,
            'description'                => $data['description'] ?? null,
            'strategies'                 => $data['strategies'] ?? null,
            'category_id'                => $primaryCategoryId,
            'lesson_message_template_id' => ! empty($data['lesson_message_template_ids']) ? $data['lesson_message_template_ids'][0] : null,
            'expiry_period'              => $data['expiry_period'],
            'expire_date'                => $data['expire_date'] ?? null,
            'publish_date'               => $data['publish_date'] ?? null,
            'is_featured'                => $data['is_featured'] ?? false,
            'is_free'                    => $data['is_free'] ?? false,
            'price'                      => $data['price'] ?? null,
            'discount_price'             => $data['discount_price'] ?? null,
            'image'                      => $data['image'] ?? null,
        ]);

        $lesson->classes()->sync($data['class_ids'] ?? []);
        $lesson->messageTemplates()->sync($data['lesson_message_template_ids'] ?? []);

        return redirect()->route('teacher.lessons.edit', $lesson->id)
            ->with('success', 'تم تحديث الدرس بنجاح');
    }

    /**
     * Public endpoint for Vue cascade: return subject categories as JSON.
     */
    public function getSubjectCategoriesPublic(int $subjectId, $teacher): array
    {
        if (! $teacher->teachesSubject($subjectId)) {
            return [];
        }

        return $this->getCategoriesForSubject($subjectId);
    }

    /**
     * Get the full category tree for a given subject (leaf nodes and their ancestors).
     */
    private function getCategoriesForSubject(int $subjectId): array
    {
        // Get all leaf categories linked to this subject
        $leafCategories = Category::whereHas('subjects', fn($q) => $q->where('subjects.id', $subjectId))
            ->whereDoesntHave('children')
            ->get(['id', 'name', 'parent_id']);

        if ($leafCategories->isEmpty()) {
            return [];
        }

        // Collect all ancestor IDs
        $allIds = $leafCategories->pluck('id')->toArray();
        $parentIds = $leafCategories->pluck('parent_id')->filter()->unique()->toArray();

        // Load grade level (parents of leaves)
        $grades = Category::whereIn('id', $parentIds)->get(['id', 'name', 'parent_id']);
        $stageIds = $grades->pluck('parent_id')->filter()->unique()->toArray();

        // Load stage level
        $stages = Category::whereIn('id', $stageIds)->get(['id', 'name', 'parent_id']);

        return [
            'stages'  => $stages->values()->toArray(),
            'grades'  => $grades->values()->toArray(),
            'classes' => $leafCategories->values()->toArray(),
        ];
    }
}

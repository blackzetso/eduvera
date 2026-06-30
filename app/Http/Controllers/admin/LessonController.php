<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Lesson;
use App\Models\Lecture;
use App\Models\Category;
use App\Models\TimetablePeriod;
use App\Models\LessonMessageTemplate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LessonController extends Controller
{
    public function index(Request $request)
    {
        $totalCourses = Lesson::count();
        $activatedCourses = Lesson::where('status', 'enable')->count();
        $disabledCourses = Lesson::where('status', 'disable')->count();

        $lessons = Lesson::with('category')
            ->when($request->search, fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/theme1/Lessons/Index', [
            'lessons' => $lessons,
            'filters' => $request->only('search'),
            'stats' => [
                'total'     => $totalCourses,
                'activated' => $activatedCourses,
                'disabled'  => $disabledCourses,
            ],
        ]);

    }

    public function toggleStatus($id)
    {
        $form = Lesson::findOrFail($id);
        $form->status = $form->status === 'enable' ? 'disable' : 'enable';
        $form->save();

        return redirect()->route('admin.lessons.index')->with('success', 'تم تحديث الحالة بنجاح');
    }

    public function create(Request $request)
    {
        $teachers = User::where('user_type','teacher')->get();
        $categories = Category::with('children')->whereNull('parent_id')->get();
        
        // Get all timetable periods for selection
        $timetablePeriods = TimetablePeriod::with(['day', 'category'])
            ->orderBy('timetable_day_id')
            ->orderBy('time_from')
            ->get();
        
        // Get period if coming from timetable
        $fromPeriod = null;
        if ($request->has('from_period')) {
            $fromPeriod = TimetablePeriod::with(['day', 'category'])->find($request->from_period);
        }
        
        $messageTemplates = LessonMessageTemplate::enabled()->get(['id', 'title']);
        $leafCategories = Category::whereDoesntHave('children')->where('status', 'enable')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/theme1/Lessons/Create', [
            'categories'       => $categories,
            'teachers'         => $teachers,
            'timetablePeriods' => $timetablePeriods,
            'fromPeriod'       => $fromPeriod,
            'messageTemplates' => $messageTemplates,
            'leafCategories'   => $leafCategories,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name'                       => 'required|string|max:255',
                'short_description'          => 'nullable|string',
                'description'                => 'nullable|string',
                'strategies'                 => 'nullable|string',
                'category_id'                => 'required|integer|exists:categories,id',
                'class_ids'                  => 'nullable|array',
                'class_ids.*'                => 'integer|exists:categories,id',
                'teacher_id'                 => 'nullable|integer|exists:users,id',
                'lesson_message_template_id' => 'nullable|integer|exists:lesson_message_templates,id',
                'timetable_period_id'        => 'nullable|integer|exists:timetable_periods,id',
                'timetable_period_ids'       => 'nullable|array',
                'timetable_period_ids.*'     => 'integer|exists:timetable_periods,id',
                'is_featured'                => 'boolean',
                'expiryPeriod'               => 'nullable|string',
                'expiry_period'              => 'nullable|string',
                'expire_date'                => 'nullable|date',
                'publish_date'               => 'nullable|date',
                'is_free'                    => 'boolean',
                'price'                      => 'nullable|numeric',
                'discount_price'             => 'nullable|numeric',
                'video_url'                  => 'nullable|url',
                'image'                      => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ]);

            $expiryPeriodValue = $data['expiryPeriod'] ?? $data['expiry_period'] ?? 'lifetime';

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('lessons', 'public');
                $data['image'] = $path;
            }

            $lesson = Lesson::create([
                'name'                       => $data['name'],
                'short_description'          => $data['short_description'] ?? null,
                'description'                => $data['description'] ?? null,
                'strategies'                 => $data['strategies'] ?? null,
                'category_id'                => $data['category_id'],
                'teacher_id'                 => $data['teacher_id'] ?? null,
                'lesson_message_template_id' => $data['lesson_message_template_id'] ?? null,
                'is_featured'                => $data['is_featured'] ?? false,
                'expiry_period'              => $expiryPeriodValue,
                'expire_date'                => $data['expire_date'] ?? null,
                'publish_date'               => $data['publish_date'] ?? null,
                'is_free'                    => $data['is_free'] ?? false,
                'price'                      => $data['price'] ?? null,
                'discount_price'             => $data['discount_price'] ?? null,
                'video_url'                  => $data['video_url'] ?? null,
                'image'                      => $data['image'] ?? null,
            ]);

            // Link lesson to timetable periods if provided
            $periodIds = [];
            
            // Support both single period (from_period) and multiple periods
            if (!empty($data['timetable_period_ids']) && is_array($data['timetable_period_ids'])) {
                $periodIds = $data['timetable_period_ids'];
            } elseif (!empty($data['timetable_period_id'])) {
                // For backward compatibility with from_period
                $periodIds = [$data['timetable_period_id']];
            }
            
            if (!empty($periodIds)) {
                $lesson->timetablePeriods()->sync($periodIds);
            }

            // Sync multi-class assignment
            if (!empty($data['class_ids'])) {
                $lesson->classes()->sync($data['class_ids']);
            }

            return redirect()->route('admin.lessons.edit', $lesson->id);

        } catch (\Throwable $e) {
             return back()->withErrors([
                'message' => $e->getMessage()
            ]);
        }
    }



    public function edit($id)
    {
        $lesson = Lesson::with([
            'lectures.files',
            'timetablePeriods.day',
            'timetablePeriods.category',
            'classes',
        ])->findOrFail($id);
        $teachers = User::where('user_type','teacher')->get();
        $categories = Category::with('children')->whereNull('parent_id')->get();
        $lectures = $lesson->lectures;
        $messageTemplates = LessonMessageTemplate::enabled()->get(['id', 'title']);
        $leafCategories = Category::whereDoesntHave('children')->where('status', 'enable')->orderBy('name')->get(['id', 'name']);

        $timetablePeriods = TimetablePeriod::with(['day', 'category'])
            ->orderBy('timetable_day_id')
            ->orderBy('time_from')
            ->get();

        return Inertia::render('Admin/theme1/Lessons/Edit', [
            'lesson'           => $lesson,
            'categories'       => $categories,
            'teachers'         => $teachers,
            'lectures'         => $lectures,
            'timetablePeriods' => $timetablePeriods,
            'messageTemplates' => $messageTemplates,
            'leafCategories'   => $leafCategories,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $lesson = Lesson::findOrFail($id);

            $data = $request->validate([
                'name'                       => 'required|string|max:255',
                'short_description'          => 'nullable|string',
                'description'                => 'nullable|string',
                'strategies'                 => 'nullable|string',
                'category_id'                => 'required|integer|exists:categories,id',
                'class_ids'                  => 'nullable|array',
                'class_ids.*'                => 'integer|exists:categories,id',
                'teacher_id'                 => 'nullable|integer|exists:users,id',
                'lesson_message_template_id' => 'nullable|integer|exists:lesson_message_templates,id',
                'timetable_period_ids'       => 'nullable|array',
                'timetable_period_ids.*'     => 'integer|exists:timetable_periods,id',
                'is_featured'                => 'boolean',
                'expiryPeriod'               => 'nullable|string',
                'expiry_period'              => 'nullable|string',
                'expire_date'                => 'nullable|date',
                'publish_date'               => 'nullable|date',
                'is_free'                    => 'boolean',
                'price'                      => 'nullable|numeric',
                'discount_price'             => 'nullable|numeric',
                'video_url'                  => 'nullable|url',
                'image'                      => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ]);

            $expiryPeriodValue = $data['expiryPeriod'] ?? $data['expiry_period'] ?? 'lifetime';

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('lessons', 'public');
                $data['image'] = $path;
            } else {
                $data['image'] = $lesson->image;
            }

            $lesson->update([
                'name'                       => $data['name'],
                'short_description'          => $data['short_description'] ?? null,
                'description'                => $data['description'] ?? null,
                'strategies'                 => $data['strategies'] ?? null,
                'category_id'                => $data['category_id'],
                'teacher_id'                 => $data['teacher_id'] ?? null,
                'lesson_message_template_id' => $data['lesson_message_template_id'] ?? null,
                'is_featured'                => $data['is_featured'] ?? false,
                'expiry_period'              => $expiryPeriodValue,
                'expire_date'                => $data['expire_date'] ?? null,
                'publish_date'               => $data['publish_date'] ?? null,
                'is_free'                    => $data['is_free'] ?? false,
                'price'                      => $data['price'] ?? null,
                'discount_price'             => $data['discount_price'] ?? null,
                'video_url'                  => $data['video_url'] ?? null,
                'image'                      => $data['image'] ?? null,
            ]);

            // Sync timetable periods
            if (isset($data['timetable_period_ids'])) {
                $lesson->timetablePeriods()->sync($data['timetable_period_ids']);
            } else {
                $lesson->timetablePeriods()->detach();
            }

            // Sync multi-class assignment
            $lesson->classes()->sync($data['class_ids'] ?? []);

            return redirect()->route('admin.lessons.edit', $lesson->id)
                            ->with('success', 'تم تعديل الدرس بنجاح');

        } catch (\Throwable $e) {
            return back()->withErrors([
                'message' => $e->getMessage()
            ]);
        }
    }


    public function destroy($id)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->delete();

        return redirect()->route('admin.lessons.index')->with('success', 'Lesson deleted successfully');
    }

    public function status($id)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->status = $lesson->status === 'enable' ? 'disable' : 'enable';
        $lesson->save();

        return redirect()->back()->with('success', 'Status updated');
    }

    public function search($phrase, Request $request)
    {
        $lessons = Lesson::where('name', 'like', '%' . $phrase . '%')
            ->with('category')
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/theme1/Lessons/Index', [
            'lessons' => $lessons,
            'filters' => ['search' => $phrase],
        ]);
    }
}

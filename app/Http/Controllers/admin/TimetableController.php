<?php

namespace App\Http\Controllers\admin;

use Inertia\Inertia;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\Category;
use App\Models\Timetable;
use App\Models\TimetableDay;
use App\Models\TimetablePeriod;
use App\Models\TimetableAssignment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppService;

class TimetableController extends Controller
{
    /**
     * Get or create the single timetable
     */
    private function getOrCreateTimetable()
    {
        $timetable = Timetable::first();

        if (!$timetable) {
            $timetable = Timetable::create([
                'name' => 'الجدول الدراسي',
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'status' => 'active',
            ]);
        }

        return $timetable;
    }

    /**
     * Show the form for editing the timetable.
     */
    public function edit()
    {
        $timetable = $this->getOrCreateTimetable();

        $timetable->load([
            'days.periods.category',
            'days.periods.assignments.teacher',
            'days.periods.assignments.subject',
            'periods.category',
            'periods.assignments.teacher',
            'periods.assignments.subject',
        ]);

        // Get all categories with nested children recursively
        $categories = Category::with(['children' => function ($query) {
            $query->with('children');
        }])->whereNull('parent_id')->get();

        return Inertia::render('Admin/theme1/Timetables/Edit', [
            'timetable' => $timetable,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the timetable.
     */
    public function update(Request $request)
    {
        $timetable = $this->getOrCreateTimetable();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'academic_year' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $timetable->update($data);

        return back()->with('success', 'تم تحديث الجدول بنجاح');
    }

    /**
     * Display the timetable.
     */
    public function show()
    {
        $timetable = $this->getOrCreateTimetable();

        $timetable->load([
            'days.periods.category',
            'days.periods.assignments.teacher',
            'days.periods.assignments.subject',
            'periods.category',
            'periods.assignments.teacher',
            'periods.assignments.subject',
        ]);

        $teachers = User::where('user_type', 'teacher')->get();
        $subjects = Subject::all();

        // Get all categories with nested children recursively
        $categories = Category::with(['children' => function ($query) {
            $query->with('children');
        }])->whereNull('parent_id')->get();

        return Inertia::render('Admin/theme1/Timetables/Show', [
            'timetable' => $timetable,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'categories' => $categories,
        ]);
    }

    /**
     * List periods for a specific day and time.
     */
    public function listPeriods(Request $request)
    {
        $request->validate([
            'day_id' => 'required|exists:timetable_days,id',
            'time' => 'nullable|string',
            'period_ids' => 'nullable|string',
        ]);

        $day = TimetableDay::findOrFail($request->day_id);

        // Get periods - either by IDs or by day and time
        if ($request->has('period_ids') && !empty($request->period_ids)) {
            // Convert comma-separated string to array
            $periodIds = array_filter(explode(',', $request->period_ids));
            $periodIds = array_map('intval', $periodIds);

            $periods = TimetablePeriod::whereIn('id', $periodIds)
                ->with(['category.parent', 'assignments.teacher', 'assignments.subject'])
                ->get();
        } else {
            // If no period IDs, get all periods for the day
            $periods = TimetablePeriod::where('timetable_day_id', $day->id)
                ->with(['category.parent', 'assignments.teacher', 'assignments.subject'])
                ->get();
        }

        $teachers = User::where('user_type', 'teacher')->get();
        $subjects = Subject::all();
        $categories = \App\Models\Category::with(['children.children.children'])->whereNull('parent_id')->get();

        return Inertia::render('Admin/theme1/Timetables/PeriodsList', [
            'day' => $day,
            'periods' => $periods,
            'time' => $request->time,
            'showAssignments' => true,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'categories' => $categories,
        ]);
    }

    /**
     * Add a new day to the timetable.
     */
    public function addDay(Request $request)
    {
        $timetable = $this->getOrCreateTimetable();

        $data = $request->validate([
            'day_name' => 'required|string|max:255',
            'day_order' => 'nullable|integer|min:0',
        ]);

        // Check if day name already exists for this timetable
        $existing = TimetableDay::where('timetable_id', $timetable->id)
            ->where('day_name', $data['day_name'])
            ->first();

        if ($existing) {
            return back()->withErrors([
                'day_name' => 'هذا اليوم موجود بالفعل'
            ]);
        }

        $maxOrder = TimetableDay::where('timetable_id', $timetable->id)->max('day_order') ?? 0;

        TimetableDay::create([
            'timetable_id' => $timetable->id,
            'day_name' => $data['day_name'],
            'day_order' => $data['day_order'] ?? ($maxOrder + 1),
            'is_active' => true,
        ]);

        return back()->with('success', 'تم إضافة اليوم بنجاح');
    }

    /**
     * Update a day.
     */
    public function updateDay(Request $request, $id)
    {
        $day = TimetableDay::findOrFail($id);

        $data = $request->validate([
            'day_name' => 'required|string|max:255',
            'day_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Check if day name already exists for this timetable (excluding current day)
        $existing = TimetableDay::where('timetable_id', $day->timetable_id)
            ->where('day_name', $data['day_name'])
            ->where('id', '!=', $id)
            ->first();

        if ($existing) {
            return back()->withErrors([
                'day_name' => 'هذا اليوم موجود بالفعل'
            ]);
        }

        $day->update($data);

        return back()->with('success', 'تم تحديث اليوم بنجاح');
    }

    /**
     * Delete a day.
     */
    public function deleteDay($id)
    {
        $day = TimetableDay::findOrFail($id);
        $day->delete();

        return back()->with('success', 'تم حذف اليوم بنجاح');
    }

    /**
     * Reorder days.
     */
    public function reorderDays(Request $request)
    {
        $data = $request->validate([
            'days' => 'required|array',
            'days.*.id' => 'required|exists:timetable_days,id',
            'days.*.order' => 'required|integer',
        ]);

        foreach ($data['days'] as $dayData) {
            TimetableDay::where('id', $dayData['id'])
                ->update(['day_order' => $dayData['order']]);
        }

        return back()->with('success', 'تم تحديث ترتيب الأيام بنجاح');
    }

    /**
     * Get all category IDs including children recursively
     * @deprecated Use Category::getLeafDescendants() instead
     */
    private function getAllCategoryIds($categoryId, $allIds = [])
    {
        $category = Category::with('children')->find($categoryId);
        if (!$category) {
            return $allIds;
        }

        $allIds[] = $category->id;

        if ($category->children && $category->children->count() > 0) {
            foreach ($category->children as $child) {
                $allIds = $this->getAllCategoryIds($child->id, $allIds);
            }
        }

        return $allIds;
    }

    /**
     * Add a new period to the timetable.
     */
    public function addPeriod(Request $request)
    {
        $timetable = $this->getOrCreateTimetable();

        $data = $request->validate([
            'timetable_day_id' => 'required|exists:timetable_days,id',
            'period_number' => 'required|integer|min:1',
            'time_from' => 'required|date_format:H:i',
            'time_to' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    $timeFrom = $request->input('time_from');
                    if ($timeFrom && $value && strtotime($value) <= strtotime($timeFrom)) {
                        $fail('وقت النهاية يجب أن يكون بعد وقت البداية.');
                    }
                },
            ],
            'category_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    // Allow 'all' string or valid category ID
                    if ($value !== null && $value !== 'all' && $value !== '') {
                        // Must be a valid category ID
                        if (!is_numeric($value) || !Category::find((int)$value)) {
                            $fail('المرحلة الدراسية المختارة غير صحيحة.');
                        }
                    }
                }
            ]
        ]);

        // Verify the day belongs to this timetable
        $day = TimetableDay::where('id', $data['timetable_day_id'])
            ->where('timetable_id', $timetable->id)
            ->firstOrFail();

        // Check if "all" categories is selected
        $addForAllCategories = $data['category_id'] === 'all' || $data['category_id'] === null || $data['category_id'] === '';

        if ($addForAllCategories) {
            // Get all root categories (parent_id is null) then get their leaf nodes
            $allRootCategories = Category::whereNull('parent_id')->get();

            if ($allRootCategories->isEmpty()) {
                return back()->withErrors([
                    'category_id' => 'لا توجد مراحل دراسية متاحة'
                ]);
            }

            // Get all leaf nodes from all root categories
            $allLeafCategories = collect();
            foreach ($allRootCategories as $rootCategory) {
                $leafNodes = $rootCategory->getLeafDescendants();
                $allLeafCategories = $allLeafCategories->merge($leafNodes);
            }

            // Remove duplicates
            $allLeafCategories = $allLeafCategories->unique('id');

            if ($allLeafCategories->isEmpty()) {
                return back()->withErrors([
                    'category_id' => 'لا توجد فصول دراسية متاحة (عقد نهائية)'
                ]);
            }

            $createdCount = 0;
            $skippedCount = 0;
            $errors = [];
            $createdCategories = collect();

            foreach ($allLeafCategories as $leafCategory) {
                // Check if period_number already exists for the same day and category
                $existingPeriodNumber = TimetablePeriod::where('timetable_id', $timetable->id)
                    ->where('timetable_day_id', $data['timetable_day_id'])
                    ->where('period_number', $data['period_number'])
                    ->where('category_id', $leafCategory->id)
                    ->first();

                if ($existingPeriodNumber) {
                    $errors[] = "حصة برقم {$data['period_number']} موجودة بالفعل للفصل: {$leafCategory->name}";
                    $skippedCount++;
                    continue;
                }

                // Check for time conflicts
                $conflict = TimetablePeriod::where('timetable_id', $timetable->id)
                    ->where('timetable_day_id', $data['timetable_day_id'])
                    ->where('category_id', $leafCategory->id)
                    ->where(function($query) use ($data) {
                        $query->where(function($q) use ($data) {
                            $q->where('time_from', '<', $data['time_to'])
                              ->where('time_to', '>', $data['time_from']);
                        });
                    })
                    ->first();

                if ($conflict) {
                    $errors[] = "تعارض في التوقيت للفصل: {$leafCategory->name}";
                    $skippedCount++;
                    continue;
                }

                $period = TimetablePeriod::create([
                    'timetable_id' => $timetable->id,
                    'timetable_day_id' => $data['timetable_day_id'],
                    'period_number' => $data['period_number'],
                    'time_from' => $data['time_from'],
                    'time_to' => $data['time_to'],
                    'category_id' => $leafCategory->id,
                ]);

                $createdCount++;
                $createdCategories->push($leafCategory);
            }

            // Build success/warning messages
            $successMessage = '';
            $warningMessage = '';

            if ($createdCount > 0) {
                $categoryNames = $createdCategories->pluck('name')->join('، ');
                $successMessage = "تم إضافة {$createdCount} حصة للفصول التالية: {$categoryNames}";
            }

            if ($skippedCount > 0) {
                $warningMessage = "تم تخطي {$skippedCount} فصول: " . implode('، ', $errors);
            }

            if ($createdCount === 0) {
                return back()->withErrors([
                    'category_id' => 'لم يتم إضافة أي حصص. ' . $warningMessage
                ]);
            }

            if ($warningMessage) {
                return back()->with('success', $successMessage)->with('warning', $warningMessage);
            }

            return back()->with('success', $successMessage);

        } else {
            // Single category - apply recursive distribution to leaf nodes
            $categoryId = (int) $data['category_id'];
            $selectedCategory = Category::find($categoryId);

            if (!$selectedCategory) {
                return back()->withErrors([
                    'category_id' => 'المرحلة الدراسية غير موجودة'
                ]);
            }

            // Get leaf nodes for the selected category
            $leafCategories = $selectedCategory->getLeafDescendants();
            
            // Debug: Log what we found
            \Log::info('Debug - Selected Category: ' . $selectedCategory->name . ' (ID: ' . $selectedCategory->id . ')');
            \Log::info('Debug - Direct Children Count: ' . $selectedCategory->children->count());
            \Log::info('Debug - Leaf Descendants Found: ' . $leafCategories->count());
            foreach ($leafCategories as $leaf) {
                \Log::info('Debug - Leaf Node: ' . $leaf->name . ' (ID: ' . $leaf->id . ')');
            }

            $createdCount = 0;
            $skippedCount = 0;
            $errors = [];
            $createdCategories = collect();

            foreach ($leafCategories as $leafCategory) {
                // Check if period_number already exists for the same day and category
                $existingPeriodNumber = TimetablePeriod::where('timetable_id', $timetable->id)
                    ->where('timetable_day_id', $data['timetable_day_id'])
                    ->where('period_number', $data['period_number'])
                    ->where('category_id', $leafCategory->id)
                    ->first();

                if ($existingPeriodNumber) {
                    $errors[] = "حصة برقم {$data['period_number']} موجودة بالفعل للفصل: {$leafCategory->name}";
                    $skippedCount++;
                    continue;
                }

                // Check for time conflicts in the same day and category
                $conflict = TimetablePeriod::where('timetable_id', $timetable->id)
                    ->where('timetable_day_id', $data['timetable_day_id'])
                    ->where('category_id', $leafCategory->id)
                    ->where(function($query) use ($data) {
                        $query->where(function($q) use ($data) {
                            $q->where('time_from', '<', $data['time_to'])
                              ->where('time_to', '>', $data['time_from']);
                        });
                    })
                    ->first();

                if ($conflict) {
                    $errors[] = "تعارض في التوقيت للفصل: {$leafCategory->name}";
                    $skippedCount++;
                    continue;
                }

                $period = TimetablePeriod::create([
                    'timetable_id' => $timetable->id,
                    'timetable_day_id' => $data['timetable_day_id'],
                    'period_number' => $data['period_number'],
                    'time_from' => $data['time_from'],
                    'time_to' => $data['time_to'],
                    'category_id' => $leafCategory->id,
                ]);

                $createdCount++;
                $createdCategories->push($leafCategory);
            }

            // Build success/warning messages
            if ($createdCount === 0) {
                $errorMessage = 'لم يتم إضافة أي حصص';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . implode('، ', $errors);
                }
                return back()->withErrors(['category_id' => $errorMessage]);
            }

            if ($createdCount === 1) {
                $successMessage = 'تم إضافة الحصة بنجاح للفصل: ' . $createdCategories->first()->name;
            } else {
                $categoryNames = $createdCategories->pluck('name')->join('، ');
                $successMessage = "تم إضافة {$createdCount} حصص للفصول التالية: {$categoryNames}";
            }

            if ($skippedCount > 0) {
                $warningMessage = "تم تخطي {$skippedCount} فصول: " . implode('، ', $errors);
                return back()->with('success', $successMessage)->with('warning', $warningMessage);
            }

            return back()->with('success', $successMessage);
        }
    }

    /**
     * Update a period.
     */
    public function updatePeriod(Request $request, $id)
    {
        $period = TimetablePeriod::with('category')->findOrFail($id);

        $data = $request->validate([
            'timetable_day_id' => 'required|exists:timetable_days,id',
            'period_number' => 'required|integer|min:1',
            'time_from' => 'required|date_format:H:i',
            'time_to' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    $timeFrom = $request->input('time_from');
                    if ($timeFrom && $value && strtotime($value) <= strtotime($timeFrom)) {
                        $fail('وقت النهاية يجب أن يكون بعد وقت البداية.');
                    }
                },
            ],
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        // Verify the day belongs to this timetable
        $day = TimetableDay::where('id', $data['timetable_day_id'])
            ->where('timetable_id', $period->timetable_id)
            ->firstOrFail();

        // التحقق من تغيير الفئة
        $oldCategoryId = $period->category_id;
        $newCategoryId = $data['category_id'];
        
        if ($oldCategoryId !== $newCategoryId) {
            // إذا تم تغيير الفئة، نطبق منطق التوزيع التكراري
            
            $newCategory = Category::find($newCategoryId);
            $leafCategories = $newCategory->getLeafDescendants();
            
            // إذا كانت الفئة الجديدة لها أكثر من leaf node واحد
            if ($leafCategories->count() > 1) {
                // حذف الحصة الحالية
                $periodData = $period->toArray();
                $period->delete();
                
                // إنشاء حصص جديدة لكل leaf category
                $createdCount = 0;
                $skippedCount = 0;
                $errors = [];
                $createdCategories = collect();
                
                foreach ($leafCategories as $leafCategory) {
                    // Check if period_number already exists for the same day and category
                    $existingPeriodNumber = TimetablePeriod::where('timetable_id', $periodData['timetable_id'])
                        ->where('timetable_day_id', $data['timetable_day_id'])
                        ->where('period_number', $data['period_number'])
                        ->where('category_id', $leafCategory->id)
                        ->first();

                    if ($existingPeriodNumber) {
                        $errors[] = "حصة برقم {$data['period_number']} موجودة بالفعل للفصل: {$leafCategory->name}";
                        $skippedCount++;
                        continue;
                    }

                    // Check for time conflicts
                    $conflict = TimetablePeriod::where('timetable_id', $periodData['timetable_id'])
                        ->where('timetable_day_id', $data['timetable_day_id'])
                        ->where('category_id', $leafCategory->id)
                        ->where(function($query) use ($data) {
                            $query->where(function($q) use ($data) {
                                $q->where('time_from', '<', $data['time_to'])
                                  ->where('time_to', '>', $data['time_from']);
                            });
                        })
                        ->first();

                    if ($conflict) {
                        $errors[] = "تعارض في التوقيت للفصل: {$leafCategory->name}";
                        $skippedCount++;
                        continue;
                    }

                    $newPeriod = TimetablePeriod::create([
                        'timetable_id' => $periodData['timetable_id'],
                        'timetable_day_id' => $data['timetable_day_id'],
                        'period_number' => $data['period_number'],
                        'time_from' => $data['time_from'],
                        'time_to' => $data['time_to'],
                        'category_id' => $leafCategory->id,
                    ]);
                    
                    $createdCount++;
                    $createdCategories->push($leafCategory);
                }
                
                if ($createdCount === 0) {
                    $errorMessage = 'لم يتم إنشاء أي حصص جديدة';
                    if (!empty($errors)) {
                        $errorMessage .= ': ' . implode('، ', $errors);
                    }
                    return back()->withErrors(['category_id' => $errorMessage]);
                }
                
                $categoryNames = $createdCategories->pluck('name')->join('، ');
                $successMessage = "تم إنشاء {$createdCount} حصص للفصول التالية: {$categoryNames}";
                
                if ($skippedCount > 0) {
                    $warningMessage = "تم تخطي {$skippedCount} فصول: " . implode('، ', $errors);
                    return back()->with('success', $successMessage)->with('warning', $warningMessage);
                }
                
                return back()->with('success', $successMessage);
                
            } else {
                // إذا كانت الفئة الجديدة leaf node واحد، حدث الحصة عادي
                $leafCategoryId = $leafCategories->first()->id;
                
                // Check if period_number already exists for the same day and category (excluding current period)
                $existingPeriodNumber = TimetablePeriod::where('timetable_id', $period->timetable_id)
                    ->where('timetable_day_id', $data['timetable_day_id'])
                    ->where('period_number', $data['period_number'])
                    ->where('category_id', $leafCategoryId)
                    ->where('id', '!=', $period->id)
                    ->first();

                if ($existingPeriodNumber) {
                    return back()->withErrors([
                        'period_number' => 'تم إضافة حصة برقم ' . $data['period_number'] . ' في نفس اليوم والفصل الدراسي من قبل. يرجى اختيار رقم حصة مختلف.'
                    ]);
                }

                // Check for time conflicts (excluding current period)
                $conflict = TimetablePeriod::where('timetable_id', $period->timetable_id)
                    ->where('id', '!=', $id)
                    ->where('timetable_day_id', $data['timetable_day_id'])
                    ->where('category_id', $leafCategoryId)
                    ->where(function($query) use ($data) {
                        $query->where(function($q) use ($data) {
                            $q->where('time_from', '<', $data['time_to'])
                              ->where('time_to', '>', $data['time_from']);
                        });
                    })
                    ->first();

                if ($conflict) {
                    return back()->withErrors([
                        'time_from' => 'هناك تعارض في التوقيت مع حصة أخرى في نفس اليوم والفصل الدراسي'
                    ]);
                }
                
                $period->update([
                    'timetable_day_id' => $data['timetable_day_id'],
                    'period_number' => $data['period_number'],
                    'time_from' => $data['time_from'],
                    'time_to' => $data['time_to'],
                    'category_id' => $leafCategoryId,
                ]);
            }
        } else {
            // إذا لم تتغير الفئة، حدث الحصة عادي
            
            // Check if period_number already exists for the same day and category (excluding current period)
            $existingPeriodNumber = TimetablePeriod::where('timetable_id', $period->timetable_id)
                ->where('timetable_day_id', $data['timetable_day_id'])
                ->where('period_number', $data['period_number'])
                ->where('category_id', $data['category_id'])
                ->where('id', '!=', $period->id)
                ->first();

            if ($existingPeriodNumber) {
                return back()->withErrors([
                    'period_number' => 'تم إضافة حصة برقم ' . $data['period_number'] . ' في نفس اليوم والمرحلة الدراسية من قبل. يرجى اختيار رقم حصة مختلف.'
                ]);
            }

            // Check for time conflicts (excluding current period)
            $conflict = TimetablePeriod::where('timetable_id', $period->timetable_id)
                ->where('id', '!=', $id)
                ->where('timetable_day_id', $data['timetable_day_id'])
                ->where('category_id', $data['category_id'])
                ->where(function($query) use ($data) {
                    $query->where(function($q) use ($data) {
                        $q->where('time_from', '<', $data['time_to'])
                          ->where('time_to', '>', $data['time_from']);
                    });
                })
                ->first();

            if ($conflict) {
                return back()->withErrors([
                    'time_from' => 'هناك تعارض في التوقيت مع حصة أخرى في نفس اليوم والمرحلة الدراسية'
                ]);
            }

            $period->update($data);
        }

        return back()->with('success', 'تم تحديث الحصة بنجاح');
    }

    /**
     * Delete a period.
     */
    public function deletePeriod($id)
    {
        $period = TimetablePeriod::findOrFail($id);
        $period->delete();

        return back()->with('success', 'تم حذف الحصة بنجاح');
    }

    /**
     * Assign a teacher to a period.
     */
    public function assignTeacher(Request $request)
    {
        $data = $request->validate([
            'timetable_period_id' => 'required|exists:timetable_periods,id',
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'type' => 'required|in:main,backup',
        ]);

        $period = TimetablePeriod::with(['timetable', 'day', 'category'])->findOrFail($data['timetable_period_id']);
        $teacher = User::findOrFail($data['teacher_id']);
        $subject = Subject::findOrFail($data['subject_id']);

        // Check if teacher is already assigned to this period with ANY type (main or backup)
        $existingAnyType = TimetableAssignment::where('timetable_period_id', $data['timetable_period_id'])
            ->where('teacher_id', $data['teacher_id'])
            ->first();

        if ($existingAnyType) {
            $existingTypeText = $existingAnyType->type === 'backup' ? 'احتياطية' : 'أساسية';
            $requestedTypeText = $data['type'] === 'backup' ? 'احتياطية' : 'أساسية';

            return back()->withErrors([
                'teacher_id' => "لا يمكن تعيين المدرس كحصة {$requestedTypeText} لأنه معين بالفعل كحصة {$existingTypeText} على نفس الحصة."
            ]);
        }

        // Check if there's already a main teacher assigned to this period (for different teachers)
        if ($data['type'] === 'main') {
            $existingMainTeacher = TimetableAssignment::where('timetable_period_id', $data['timetable_period_id'])
                ->where('type', 'main')
                ->first();

            if ($existingMainTeacher) {
                $existingTeacher = User::find($existingMainTeacher->teacher_id);
                return back()->withErrors([
                    'teacher_id' => "لا يمكن تعيين أكثر من مدرس أساسي واحد لنفس الحصة. المدرس الأساسي الحالي: " . ($existingTeacher ? $existingTeacher->name : 'غير معروف')
                ]);
            }
        }

        // Check for conflicts - same teacher, same time, different period (only for main assignments)
        if ($data['type'] === 'main') {
            $conflict = TimetableAssignment::whereHas('period', function($query) use ($period, $data) {
                $query->where('timetable_id', $period->timetable_id)
                    ->where('timetable_day_id', $period->timetable_day_id)
                    ->where('teacher_id', $data['teacher_id'])
                    ->where('type', 'main')
                    ->where(function($q) use ($period) {
                        $q->where('time_from', '<', $period->time_to)
                          ->where('time_to', '>', $period->time_from);
                    });
            })->first();

            if ($conflict) {
                return back()->withErrors([
                    'teacher_id' => 'المدرس لديه حصة أساسية أخرى في نفس الوقت'
                ]);
            }
        }

        $assignment = TimetableAssignment::create([
            'timetable_period_id' => $data['timetable_period_id'],
            'teacher_id' => $data['teacher_id'],
            'subject_id' => $data['subject_id'],
            'assigned_by' => auth()->id(),
            'status' => 'approved',
            'type' => $data['type'],
        ]);

        // Send WhatsApp notification
        try {
            $whatsappService = new WhatsAppService();
            $whatsappService->sendAssignmentNotification($teacher, $period, $subject, $data['type']);
        } catch (\Exception $e) {
            // Silent fail - don't break assignment if WhatsApp fails
        }

        $typeText = $data['type'] === 'backup' ? 'احتياطية' : 'أساسية';
        return back()->with('success', "تم تعيين المدرس كحصة {$typeText} بنجاح");
    }

    /**
     * Remove an assignment.
     */
    public function removeAssignment($id)
    {
        $assignment = TimetableAssignment::findOrFail($id);
        $assignment->delete();

        return back()->with('success', 'تم إزالة التعيين بنجاح');
    }

    /**
     * Create a lesson from a timetable period.
     */
    public function createLessonFromPeriod(Request $request, $id)
    {
        $period = TimetablePeriod::with('category')->findOrFail($id);

        // Redirect to lesson create page with period ID
        return redirect()->route('admin.lessons.create', ['from_period' => $period->id]);
    }

    /**
     * Filter backup assignments only.
     */
    public function filterBackupAssignments(Request $request)
    {
        // #region agent log
        file_put_contents('d:\project\eduvera\laravel-inertia.js\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_filterBackup','timestamp'=>time()*1000,'location'=>'TimetableController.php:604','message'=>'filterBackupAssignments called','data'=>['request_path'=>$request->path()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
        // #endregion

        $timetable = $this->getOrCreateTimetable();

        $assignments = TimetableAssignment::with([
            'period.day',
            'period.category',
            'teacher',
            'subject'
        ])
        ->whereHas('period', function($query) use ($timetable) {
            $query->where('timetable_id', $timetable->id);
        })
        ->where('type', 'backup')
        ->orderBy('created_at', 'desc')
        ->get();

        // #region agent log
        file_put_contents('d:\project\eduvera\laravel-inertia.js\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_beforeRender','timestamp'=>time()*1000,'location'=>'TimetableController.php:621','message'=>'About to render BackupAssignments','data'=>['assignments_count'=>count($assignments),'timetable_id'=>$timetable->id],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
        // #endregion

        return Inertia::render('Admin/theme1/Timetables/BackupAssignments', [
            'assignments' => $assignments,
            'timetable' => $timetable,
        ]);
    }

    /**
     * Filter teacher schedule.
     */
    public function filterTeacherSchedule(Request $request, $teacherId)
    {
        $teacher = User::where('user_type', 'teacher')->findOrFail($teacherId);
        $timetable = $this->getOrCreateTimetable();

        $assignments = TimetableAssignment::with([
            'period.day',
            'period.category',
            'subject'
        ])
        ->whereHas('period', function($query) use ($timetable) {
            $query->where('timetable_id', $timetable->id);
        })
        ->where('teacher_id', $teacherId)
        ->orderBy('created_at', 'desc')
        ->get();

        return Inertia::render('Admin/theme1/Timetables/TeacherSchedule', [
            'assignments' => $assignments,
            'teacher' => $teacher,
            'timetable' => $timetable,
        ]);
    }

    /**
     * Filter backup assignments and empty periods by date range and time.
     */
    public function filterBackupByDateRange(Request $request)
    {
        $data = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'from_time' => 'nullable|date_format:H:i',
            'to_time' => 'nullable|date_format:H:i',
            'teacher_id' => 'nullable|exists:users,id',
            'category_id' => 'nullable|exists:categories,id',
            'filter_type' => 'nullable|in:backup,empty,all', // backup, empty, or all
        ]);

        $timetable = $this->getOrCreateTimetable();
        $filterType = $data['filter_type'] ?? 'all';

        $assignments = [];
        $emptyPeriods = [];

        // Get backup assignments if needed
        if (in_array($filterType, ['backup', 'all'])) {
            $query = TimetableAssignment::with([
                'period.day',
                'period.category',
                'teacher',
                'subject'
            ])
            ->whereHas('period', function($q) use ($timetable) {
                $q->where('timetable_id', $timetable->id);
            })
            ->where('type', 'backup');

            // Apply date range filter
            if (!empty($data['from_date'])) {
                $toDate = $data['to_date'] ?? $data['from_date'];
                $query->whereBetween('created_at', [
                    $data['from_date'] . ' 00:00:00',
                    $toDate . ' 23:59:59'
                ]);
            }

            // Apply time range filter for period times
            if (!empty($data['from_time']) && !empty($data['to_time'])) {
                $query->whereHas('period', function($q) use ($data) {
                    $q->where('time_from', '>=', $data['from_time'])
                      ->where('time_to', '<=', $data['to_time']);
                });
            }

            // Apply teacher filter
            if (!empty($data['teacher_id'])) {
                $query->where('teacher_id', $data['teacher_id']);
            }

            // Apply category filter
            if (!empty($data['category_id'])) {
                $query->whereHas('period', function($q) use ($data) {
                    $q->where('category_id', $data['category_id']);
                });
            }

            $assignments = $query->orderBy('created_at', 'desc')->get();
        }

        // Get empty periods if needed
        if (in_array($filterType, ['empty', 'all'])) {
            $query = TimetablePeriod::with([
                'day',
                'category'
            ])
            ->where('timetable_id', $timetable->id)
            ->whereDoesntHave('assignments'); // No assignments at all

            // Apply time range filter
            if (!empty($data['from_time']) && !empty($data['to_time'])) {
                $query->where('time_from', '>=', $data['from_time'])
                      ->where('time_to', '<=', $data['to_time']);
            }

            // Apply category filter
            if (!empty($data['category_id'])) {
                $query->where('category_id', $data['category_id']);
            }

            $emptyPeriods = $query->orderBy('timetable_day_id', 'asc')
                                   ->orderBy('time_from', 'asc')
                                   ->get();
        }

        $teachers = User::where('user_type', 'teacher')->get();
        $categories = Category::whereNull('parent_id')->with('children')->get();

        return Inertia::render('Admin/theme1/Timetables/BackupReport', [
            'assignments' => $assignments,
            'emptyPeriods' => $emptyPeriods,
            'timetable' => $timetable,
            'from_date' => $data['from_date'] ?? null,
            'to_date' => $data['to_date'] ?? null,
            'from_time' => $data['from_time'] ?? null,
            'to_time' => $data['to_time'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'filter_type' => $filterType,
            'teachers' => $teachers,
            'categories' => $categories,
        ]);
    }

}

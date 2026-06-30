<?php

namespace App\Http\Controllers\teacher;

use App\Http\Controllers\Controller;
use App\Models\Lecture;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LectureController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'lesson_id' => 'required|integer|exists:lessons,id',
        ]);

        Lesson::where('teacher_id', $request->user()->id)
            ->findOrFail($validated['lesson_id']);

        Lecture::create($validated);

        return back()->with('success', 'تم إضافة المحاضرة بنجاح');
    }

    public function destroy(Request $request, Lecture $lecture)
    {
        $lecture->load('lesson');
        abort_if($lecture->lesson->teacher_id !== $request->user()->id, 403);

        $lecture->delete();

        return back()->with('success', 'تم حذف المحاضرة بنجاح');
    }
}

<?php

namespace App\Http\Controllers\admin;

use Inertia\Inertia;
use App\Models\LessonMessageTemplate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LessonMessageTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = LessonMessageTemplate::when(
                $request->search,
                fn($q) => $q->where('title', 'like', '%' . $request->search . '%')
            )
            ->orderBy('id', 'DESC')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/theme1/LessonMessageTemplates/Index', [
            'templates' => $templates,
            'filters'   => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        LessonMessageTemplate::create([
            'title' => $data['title'],
            'body'  => '',
        ]);

        return redirect()->route('admin.lesson-message-templates.index')
            ->with('success', 'تم إضافة الاستراتيجية بنجاح');
    }

    public function update(Request $request, LessonMessageTemplate $lessonMessageTemplate)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $lessonMessageTemplate->update([
            'title' => $data['title'],
            'body'  => '',
        ]);

        return redirect()->route('admin.lesson-message-templates.index')
            ->with('success', 'تم تحديث الاستراتيجية بنجاح');
    }

    public function destroy(LessonMessageTemplate $lessonMessageTemplate)
    {
        $lessonMessageTemplate->delete();

        return redirect()->route('admin.lesson-message-templates.index')
            ->with('success', 'تم حذف الاستراتيجية بنجاح');
    }

    public function toggleStatus(LessonMessageTemplate $lessonMessageTemplate)
    {
        $lessonMessageTemplate->status = $lessonMessageTemplate->status === 'enable' ? 'disable' : 'enable';
        $lessonMessageTemplate->save();

        return redirect()->route('admin.lesson-message-templates.index')
            ->with('success', 'تم تحديث الحالة بنجاح');
    }
}

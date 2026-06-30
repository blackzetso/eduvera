<?php

namespace App\Http\Controllers\teacher;

use App\Http\Controllers\Controller;
use App\Models\LessonMessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonMessageTemplateController extends Controller
{
    public function index(): Response
    {
        $strategies = LessonMessageTemplate::enabled()
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Teacher/LessonStrategies/Index', [
            'strategies' => $strategies,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $template = LessonMessageTemplate::create([
            'title'  => $data['title'],
            'body'   => '',
            'status' => 'enable',
        ]);

        $payload = $template->only(['id', 'title']);

        if ($request->header('X-Inertia')) {
            return back()->with('strategyCreated', $payload);
        }

        if ($request->expectsJson()) {
            return response()->json(['template' => $payload], 201);
        }

        return back()->with('strategyCreated', $payload);
    }
}

<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\DailyAbsenceCoverageService;
use App\Services\DailyLessonSwapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DailyAbsenceCoverageController extends Controller
{
    public function preview(Request $request, DailyAbsenceCoverageService $service)
    {
        $date = $request->query('date', today()->toDateString());

        return response()->json($service->buildPreview($date));
    }

    public function saveDraft(Request $request, DailyAbsenceCoverageService $service)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'assignments' => 'nullable|array',
            'assignments.*.period_id' => 'required|exists:timetable_periods,id',
            'assignments.*.replacement_teacher_id' => 'nullable|exists:users,id',
            'assignments.*.absent_teacher_id' => 'nullable|exists:users,id',
            'assignments.*.match_score' => 'nullable|integer|min:0|max:100',
            'assignments.*.match_reasons' => 'nullable|array',
            'wizard_state' => 'nullable|array',
            'wizard_state.selected_teacher_id' => 'nullable|integer',
            'wizard_state.selected_period_id' => 'nullable|integer',
            'wizard_state.wizard_step' => 'nullable|string|max:32',
        ]);

        $assignments = collect($data['assignments'] ?? [])
            ->filter(fn ($row) => !empty($row['replacement_teacher_id']))
            ->values()
            ->all();

        $result = $service->saveCoverageDraft(
            $data['date'],
            $assignments,
            $data['wizard_state'] ?? [],
            $request->user()?->id
        );

        Log::info('Daily coverage draft saved', [
            'date' => $data['date'],
            'count' => count($assignments),
        ]);

        return response()->json($result);
    }

    public function approve(Request $request, DailyAbsenceCoverageService $service)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'assignments' => 'nullable|array',
            'assignments.*.period_id' => 'required|exists:timetable_periods,id',
            'assignments.*.replacement_teacher_id' => 'required|exists:users,id',
            'assignments.*.absent_teacher_id' => 'nullable|exists:users,id',
            'assignments.*.match_score' => 'nullable|integer|min:0|max:100',
            'assignments.*.match_reasons' => 'nullable|array',
            'assignments.*.reason' => 'nullable|string|max:500',
        ]);

        $result = $service->approveCoverage(
            $data['date'],
            $data['assignments'],
            $request->user()?->id
        );

        Log::info('Daily coverage approved', ['date' => $data['date'], 'count' => count($data['assignments'])]);

        return response()->json($result);
    }

    public function distributionReport(Request $request, DailyAbsenceCoverageService $service)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'assignments' => 'nullable|array',
            'assignments.*.period_id' => 'required_with:assignments|exists:timetable_periods,id',
            'assignments.*.replacement_teacher_id' => 'nullable|exists:users,id',
            'assignments.*.absent_teacher_id' => 'nullable|exists:users,id',
            'assignments.*.status' => 'nullable|string|max:32',
        ]);

        $assignments = null;
        if (! empty($data['assignments'])) {
            $assignments = collect($data['assignments'])
                ->filter(fn ($row) => ! empty($row['replacement_teacher_id']))
                ->values()
                ->all();
        }

        return response()->json(
            $service->buildSubstituteDistributionReport($data['date'], $assignments)
        );
    }

    public function notifySubstitute(Request $request, DailyAbsenceCoverageService $service)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'period_id' => 'required|exists:timetable_periods,id',
            'replacement_teacher_id' => 'required|exists:users,id',
            'absent_teacher_id' => 'nullable|exists:users,id',
        ]);

        $sent = $service->notifySubstituteAssignment($data['date'], $data);

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'تم إرسال إشعار للمعلم البديل'
                : 'تعذر إرسال الإشعار — تحقق من بيانات المعلم',
        ]);
    }

    public function markTeacherAbsent(Request $request, DailyAbsenceCoverageService $service)
    {
        $data = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|string|max:50',
            'reason' => 'nullable|string|max:255',
        ]);

        $service->markTeacherAbsent(
            (int) $data['teacher_id'],
            $data['date'],
            $data['status'],
            $data['reason'] ?? null,
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'preview' => $service->buildPreview($data['date']),
        ]);
    }

    public function close(Request $request, DailyAbsenceCoverageService $service)
    {
        $date = $request->input('date', today()->toDateString());
        $report = $service->closeDay($date);

        return response()->json([
            'success' => true,
            'message' => 'تم إغلاق تغطية اليوم وأرشفة التقرير',
            'report' => $report,
        ]);
    }

    public function swapCandidates(Request $request, DailyLessonSwapService $swap)
    {
        $data = $request->validate([
            'date' => 'nullable|date',
            'trigger_period_id' => 'required|exists:timetable_periods,id',
        ]);

        return response()->json($swap->swapCandidates(
            $data['date'] ?? today()->toDateString(),
            (int) $data['trigger_period_id']
        ));
    }

    public function swapPreview(Request $request, DailyLessonSwapService $swap)
    {
        $data = $this->validateSwapRequest($request);

        return response()->json($swap->previewSwap($data));
    }

    public function applySwap(Request $request, DailyLessonSwapService $swap, DailyAbsenceCoverageService $coverage)
    {
        $data = $this->validateSwapRequest($request);
        $data['trigger_period_id'] = $data['trigger_period_id'] ?? null;

        $adjustment = $swap->applySwap($data, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'تم تطبيق التبديل المؤقت لليوم',
            'adjustment' => [
                'id' => $adjustment->id,
                'swap_type' => $adjustment->swap_type,
                'impact_preview' => $adjustment->impact_preview,
            ],
            'preview' => $coverage->buildPreview($data['date']),
        ]);
    }

    public function cancelLesson(Request $request, DailyLessonSwapService $swap, DailyAbsenceCoverageService $coverage)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'period_id' => 'required|exists:timetable_periods,id',
            'adjustment_id' => 'nullable|integer|exists:daily_timetable_adjustments,id',
        ]);

        if (!empty($data['adjustment_id'])) {
            $swap->cancelAdjustment((int) $data['adjustment_id']);
        }
        $swap->cancelLessonResolution($data['date'], (int) $data['period_id']);

        return response()->json([
            'success' => true,
            'preview' => $coverage->buildPreview($data['date']),
        ]);
    }

    protected function validateSwapRequest(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date',
            'swap_type' => 'required|in:move_lesson,swap_lessons,replace_teacher',
            'teacher_id' => 'required|exists:users,id',
            'source_period_id' => 'required|exists:timetable_periods,id',
            'target_period_id' => 'nullable|exists:timetable_periods,id',
            'secondary_teacher_id' => 'nullable|exists:users,id',
            'secondary_period_id' => 'nullable|exists:timetable_periods,id',
            'replacement_teacher_id' => 'nullable|exists:users,id',
            'trigger_period_id' => 'nullable|exists:timetable_periods,id',
            'reason' => 'nullable|string|max:500',
        ]);
    }
}

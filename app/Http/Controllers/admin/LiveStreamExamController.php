<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\LiveStreamExamSession;
use App\Models\LiveStreamQuiz;
use App\Models\LiveStreamQuizAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveStreamExamController extends Controller
{
    /**
     * Abort with 403 if the authenticated user is a teacher who does not own the stream.
     * Uses teacher_email because live_streams has no teacher_id column.
     */
    private function assertTeacherOwns(LiveStream $stream): void
    {
        if (auth()->user()?->user_type === 'teacher') {
            abort_if($stream->teacher_email !== auth()->user()->email, 403, 'هذا البث لا يخصك.');
        }
    }

    /**
     * Teacher: launch all draft quizzes (without an exam session) as a timed exam.
     */
    public function launch(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->assertTeacherOwns($liveStream);

        $request->validate(['time_limit' => 'required|integer|min:10|max:86400']);

        // Prevent launching if an exam is already active
        $existing = $liveStream->examSessions()
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json(['error' => 'يوجد امتحان نشط بالفعل'], 422);
        }

        // Grab all draft quizzes not yet tied to any exam session
        $drafts = LiveStreamQuiz::where('live_stream_id', $liveStream->id)
            ->where('status', 'draft')
            ->whereNull('exam_session_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($drafts->isEmpty()) {
            return response()->json(['error' => 'لا توجد أسئلة في الطابور'], 422);
        }

        $session = LiveStreamExamSession::create([
            'live_stream_id' => $liveStream->id,
            'time_limit'     => $request->time_limit,
            'status'         => 'active',
            'activated_at'   => now(),
        ]);

        // Link all draft quizzes to this session (keep status = draft so single-question mode is unaffected)
        LiveStreamQuiz::whereIn('id', $drafts->pluck('id'))
            ->update(['exam_session_id' => $session->id]);

        $session->load('quizzes');

        return response()->json([
            'session'  => $this->formatSession($session),
            'quizzes'  => $session->quizzes->map(fn ($q) => $this->formatQuiz($q)),
        ]);
    }

    /**
     * Teacher: close/end an active exam session.
     */
    public function close(LiveStream $liveStream, LiveStreamExamSession $session): JsonResponse
    {
        $this->assertTeacherOwns($liveStream);
        abort_if($session->live_stream_id !== $liveStream->id, 403);

        $session->update(['status' => 'closed', 'closed_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Teacher: polling – get current exam status, answer counts, remaining time.
     */
    public function status(LiveStream $liveStream, LiveStreamExamSession $session): JsonResponse
    {
        $this->assertTeacherOwns($liveStream);
        abort_if($session->live_stream_id !== $liveStream->id, 403);

        $session->refresh();

        // Auto-close if time expired
        if ($session->status === 'active' && $session->remainingSeconds() <= 0) {
            $session->update(['status' => 'closed', 'closed_at' => now()]);
        }

        $quizIds = $session->quizzes()->pluck('id');

        // How many unique students submitted (at least one answer in this session)
        $submittedCount = LiveStreamQuizAnswer::whereIn('live_stream_quiz_id', $quizIds)
            ->distinct('student_identifier')
            ->count('student_identifier');

        // Per-question answer counts
        $perQuestion = LiveStreamQuiz::whereIn('id', $quizIds)
            ->with(['answers' => fn ($q) => $q->select('live_stream_quiz_id', 'answer', 'is_correct')])
            ->get()
            ->map(function ($quiz) {
                return [
                    'id'           => $quiz->id,
                    'question_text'=> $quiz->question_text,
                    'total'        => $quiz->answers->count(),
                    'correct'      => $quiz->answers->where('is_correct', true)->count(),
                ];
            });

        return response()->json([
            'session'          => $this->formatSession($session->fresh()),
            'submitted_count'  => $submittedCount,
            'per_question'     => $perQuestion,
        ]);
    }

    /**
     * PUBLIC (student): get the currently active exam session + questions for this stream.
     */
    public function activeExam(LiveStream $liveStream): JsonResponse
    {
        $session = $liveStream->examSessions()
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$session) {
            return response()->json(['exam' => null]);
        }

        // Auto-close if time expired
        if ($session->remainingSeconds() <= 0) {
            $session->update(['status' => 'closed', 'closed_at' => now()]);
            return response()->json(['exam' => null]);
        }

        $quizzes = $session->quizzes()
            ->select(['id', 'question_text', 'question_type', 'options', 'allow_multiple', 'time_limit', 'attachment_path', 'sort_order'])
            ->get()
            ->map(fn ($q) => $this->formatQuiz($q));

        return response()->json([
            'exam' => [
                'id'                => $session->id,
                'time_limit'        => $session->time_limit,
                'remaining_seconds' => $session->remainingSeconds(),
                'activated_at'      => $session->activated_at->toIso8601String(),
                'quizzes'           => $quizzes,
            ],
        ]);
    }

    /**
     * PUBLIC (student): submit all answers for the exam at once.
     */
    public function submitExam(Request $request, LiveStream $liveStream, LiveStreamExamSession $session): JsonResponse
    {
        abort_if($session->live_stream_id !== $liveStream->id, 403);

        // Allow 10s grace period for network latency
        if ($session->status === 'closed' && $session->remainingSeconds() < -10) {
            return response()->json(['error' => 'انتهى وقت الامتحان'], 422);
        }

        $request->validate([
            'student_name'       => 'required|string|max:255',
            'student_identifier' => 'required|string|max:255',
            'answers'            => 'required|array',
            'answers.*.quiz_id'  => 'required|integer',
            'answers.*.answer'   => 'present|nullable|string',
            'answers.*.correction' => 'nullable|string|max:1000',
        ]);

        $studentName       = strip_tags($request->student_name);
        $studentIdentifier = $request->student_identifier;

        // Get all quiz IDs belonging to this session
        $validQuizIds = LiveStreamQuiz::where('exam_session_id', $session->id)
            ->pluck('id')
            ->flip(); // for O(1) lookup

        $quizMap = LiveStreamQuiz::whereIn('id', array_keys($validQuizIds->toArray()))
            ->get()
            ->keyBy('id');

        foreach ($request->answers as $item) {
            $quizId = (int) $item['quiz_id'];
            if (!isset($validQuizIds[$quizId])) continue;

            $quiz   = $quizMap[$quizId] ?? null;
            if (!$quiz) continue;

            $answerText = isset($item['answer']) ? (string) $item['answer'] : '';
            $correction = isset($item['correction']) ? strip_tags($item['correction']) : null;

            // Auto-calculate correctness
            $isCorrect = null;
            if ($quiz->correct_answer !== null && $answerText !== '') {
                if ($quiz->question_type === 'true_false' || $quiz->question_type === 'true_false_correction') {
                    $isCorrect = ($answerText === $quiz->correct_answer);
                } elseif ($quiz->question_type === 'multiple_choice') {
                    try {
                        $selected = json_decode($answerText, true) ?? [$answerText];
                        $correct  = is_array($quiz->correct_answer)
                            ? $quiz->correct_answer
                            : [$quiz->correct_answer];
                        sort($selected);
                        sort($correct);
                        $isCorrect = ($selected == $correct);
                    } catch (\Throwable) {
                        $isCorrect = false;
                    }
                }
            }

            // Upsert: if already answered (e.g., double-submit), ignore
            LiveStreamQuizAnswer::firstOrCreate(
                [
                    'live_stream_quiz_id' => $quizId,
                    'student_identifier'  => $studentIdentifier,
                ],
                [
                    'live_stream_id' => $liveStream->id,
                    'student_name'   => $studentName,
                    'answer'         => $answerText,
                    'correction'     => $correction,
                    'is_correct'     => $isCorrect,
                    'submitted_at'   => now(),
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatSession(LiveStreamExamSession $session): array
    {
        return [
            'id'                => $session->id,
            'time_limit'        => $session->time_limit,
            'status'            => $session->status,
            'remaining_seconds' => $session->remainingSeconds(),
            'activated_at'      => $session->activated_at->toIso8601String(),
        ];
    }

    private function formatQuiz(LiveStreamQuiz $quiz): array
    {
        return [
            'id'             => $quiz->id,
            'question_text'  => $quiz->question_text,
            'question_type'  => $quiz->question_type,
            'options'        => $quiz->options,
            'allow_multiple' => $quiz->allow_multiple,
            'attachment_url' => $quiz->attachment_url ?? null,
        ];
    }
}

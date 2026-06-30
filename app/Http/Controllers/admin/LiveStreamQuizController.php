<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\LiveStreamQuiz;
use App\Models\LiveStreamQuizAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LiveStreamQuizController extends Controller
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

    // ── Teacher Methods (admin auth) ──────────────────────────────────────────

    /**
     * List all quiz questions for a stream with answer counts.
     */
    public function index(LiveStream $liveStream)
    {
        $this->assertTeacherOwns($liveStream);
        $quizzes = $liveStream->quizzes()
            ->withCount('answers')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn($q) => $this->formatQuizForTeacher($q));

        return response()->json(['quizzes' => $quizzes]);
    }

    /**
     * Create a new quiz question (supports PDF upload for pdf_exam type).
     */
    public function store(Request $request, LiveStream $liveStream)
    {
        $this->assertTeacherOwns($liveStream);

        $data = $request->validate([
            'question_text'  => 'required|string|max:2000',
            'question_type'  => 'required|in:true_false,true_false_correction,fill_blank,multiple_choice,essay,pdf_exam',
            'options'        => 'nullable|array|min:2|max:10',
            'options.*'      => 'required|string|max:500',
            'correct_answer' => 'nullable|string|max:500',
            'allow_multiple' => 'nullable|boolean',
            'time_limit'     => 'required|integer|min:10|max:3600',
            'attachment'     => 'nullable|file|mimes:pdf|max:20480',
        ]);

        $attachmentPath = null;

        if ($request->hasFile('attachment') && $data['question_type'] === 'pdf_exam') {
            $file           = $request->file('attachment');
            $filename       = Str::uuid() . '.pdf';
            $attachmentPath = $file->storeAs(
                "live-quizzes/{$liveStream->id}",
                $filename,
                'public'
            );
        }

        $quiz = $liveStream->quizzes()->create([
            'question_text'  => $data['question_text'],
            'question_type'  => $data['question_type'],
            'options'        => $data['options'] ?? null,
            'correct_answer' => $data['correct_answer'] ?? null,
            'allow_multiple' => $data['allow_multiple'] ?? false,
            'time_limit'     => $data['time_limit'],
            'status'         => 'draft',
            'attachment_path'=> $attachmentPath,
        ]);

        return response()->json([
            'quiz'    => $this->formatQuizForTeacher($quiz->loadCount('answers')),
            'message' => 'تم إنشاء السؤال بنجاح.',
        ], 201);
    }

    /**
     * Update a draft quiz question.
     */
    public function update(Request $request, LiveStream $liveStream, LiveStreamQuiz $quiz)
    {
        $this->assertTeacherOwns($liveStream);

        if ($quiz->live_stream_id !== $liveStream->id) {
            abort(404);
        }

        if ($quiz->status !== 'draft') {
            return response()->json(['error' => 'لا يمكن تعديل سؤال نشط أو مغلق.'], 422);
        }

        $data = $request->validate([
            'question_text'  => 'required|string|max:2000',
            'question_type'  => 'required|in:true_false,true_false_correction,fill_blank,multiple_choice,essay,pdf_exam',
            'options'        => 'nullable|array|min:2|max:10',
            'options.*'      => 'required|string|max:500',
            'correct_answer' => 'nullable|string|max:500',
            'allow_multiple' => 'nullable|boolean',
            'time_limit'     => 'required|integer|min:10|max:3600',
            'attachment'     => 'nullable|file|mimes:pdf|max:20480',
        ]);

        $attachmentPath = $quiz->attachment_path;

        if ($request->hasFile('attachment') && $data['question_type'] === 'pdf_exam') {
            // Delete old PDF if exists
            if ($attachmentPath) {
                Storage::disk('public')->delete($attachmentPath);
            }
            $filename       = Str::uuid() . '.pdf';
            $attachmentPath = $request->file('attachment')->storeAs(
                "live-quizzes/{$liveStream->id}",
                $filename,
                'public'
            );
        }

        // If question type changed away from pdf_exam, remove old PDF
        if ($data['question_type'] !== 'pdf_exam' && $quiz->attachment_path) {
            Storage::disk('public')->delete($quiz->attachment_path);
            $attachmentPath = null;
        }

        $quiz->update([
            'question_text'  => $data['question_text'],
            'question_type'  => $data['question_type'],
            'options'        => $data['options'] ?? null,
            'correct_answer' => $data['correct_answer'] ?? null,
            'allow_multiple' => $data['allow_multiple'] ?? false,
            'time_limit'     => $data['time_limit'],
            'attachment_path'=> $attachmentPath,
        ]);

        return response()->json([
            'quiz'    => $this->formatQuizForTeacher($quiz->fresh()->loadCount('answers')),
            'message' => 'تم تحديث السؤال بنجاح.',
        ]);
    }

    /**
     * Delete a quiz question and its PDF file if any.
     */
    public function destroy(LiveStream $liveStream, LiveStreamQuiz $quiz)
    {
        $this->assertTeacherOwns($liveStream);

        if ($quiz->live_stream_id !== $liveStream->id) {
            abort(404);
        }

        if ($quiz->attachment_path) {
            Storage::disk('public')->delete($quiz->attachment_path);
        }

        $quiz->delete();

        return response()->json(['message' => 'تم حذف السؤال بنجاح.']);
    }

    /**
     * Set a unified time_limit on all draft questions for this stream.
     */
    public function setTime(Request $request, LiveStream $liveStream)
    {
        $this->assertTeacherOwns($liveStream);

        $data = $request->validate([
            'time_limit' => 'required|integer|min:10|max:3600',
        ]);

        $liveStream->quizzes()
            ->where('status', 'draft')
            ->update(['time_limit' => $data['time_limit']]);

        return response()->json(['message' => 'تم تعيين الوقت لجميع الأسئلة في الطابور.']);
    }

    /**
     * Activate a quiz question (closes any currently active question first).
     */
    public function activate(LiveStream $liveStream, LiveStreamQuiz $quiz)
    {
        $this->assertTeacherOwns($liveStream);

        if ($quiz->live_stream_id !== $liveStream->id) {
            abort(404);
        }

        if ($quiz->status === 'closed') {
            return response()->json(['error' => 'لا يمكن إعادة تفعيل سؤال مغلق.'], 422);
        }

        // Close any currently active question for this stream
        $liveStream->quizzes()
            ->where('status', 'active')
            ->where('id', '!=', $quiz->id)
            ->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);

        $quiz->update([
            'status'       => 'active',
            'activated_at' => now(),
            'closed_at'    => null,
        ]);

        return response()->json([
            'quiz'    => $this->formatQuizForTeacher($quiz->fresh()->loadCount('answers')),
            'message' => 'تم تفعيل السؤال بنجاح.',
        ]);
    }

    /**
     * Manually close an active quiz question.
     */
    public function close(LiveStream $liveStream, LiveStreamQuiz $quiz)
    {
        $this->assertTeacherOwns($liveStream);

        if ($quiz->live_stream_id !== $liveStream->id) {
            abort(404);
        }

        $quiz->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'quiz'    => $this->formatQuizForTeacher($quiz->fresh()->loadCount('answers')),
            'message' => 'تم إغلاق السؤال.',
        ]);
    }

    /**
     * Get answers for a specific quiz with statistics.
     */
    public function answers(LiveStream $liveStream, LiveStreamQuiz $quiz)
    {
        $this->assertTeacherOwns($liveStream);

        if ($quiz->live_stream_id !== $liveStream->id) {
            abort(404);
        }

        $answers = $quiz->answers()->orderBy('submitted_at')->get();

        $total        = $answers->count();
        $correctCount = $answers->whereNotNull('is_correct')->where('is_correct', true)->count();

        // Per-option count for multiple_choice
        $optionCounts = [];
        if ($quiz->question_type === 'multiple_choice' && $quiz->options) {
            foreach ($quiz->options as $idx => $option) {
                $optionCounts[$idx] = $answers->filter(function ($a) use ($idx) {
                    $ans = json_decode($a->answer, true);
                    if (is_array($ans)) {
                        return in_array((string) $idx, array_map('strval', $ans));
                    }
                    return (string) $a->answer === (string) $idx;
                })->count();
            }
        }

        // true/false counts
        $trueFalseCount = [];
        if (in_array($quiz->question_type, ['true_false', 'true_false_correction'])) {
            $trueFalseCount = [
                'true'  => $answers->where('answer', 'true')->count(),
                'false' => $answers->where('answer', 'false')->count(),
            ];
        }

        return response()->json([
            'quiz'            => $this->formatQuizForTeacher($quiz),
            'total'           => $total,
            'correct_count'   => $correctCount,
            'option_counts'   => $optionCounts,
            'true_false_count'=> $trueFalseCount,
            'answers'         => $answers->map(fn($a) => [
                'id'                 => $a->id,
                'student_name'       => $a->student_name,
                'student_identifier' => $a->student_identifier,
                'answer'             => $a->answer,
                'correction'         => $a->correction,
                'is_correct'         => $a->is_correct,
                'submitted_at'       => $a->submitted_at?->format('H:i:s'),
            ]),
        ]);
    }

    // ── Student Methods (public - no auth) ────────────────────────────────────

    /**
     * Return the currently active quiz question for a live stream.
     * Called by students via polling every 2-3 seconds.
     */
    public function activeQuestion(LiveStream $liveStream)
    {
        if ($liveStream->status === 'ended') {
            return response()->json(['quiz' => null]);
        }

        /** @var LiveStreamQuiz|null $quiz */
        $quiz = $liveStream->quizzes()
            ->where('status', 'active')
            ->first();

        if (!$quiz) {
            return response()->json(['quiz' => null]);
        }

        // Calculate remaining seconds
        $elapsed   = $quiz->activated_at
            ? (int) now()->diffInSeconds($quiz->activated_at, false) * -1
            : 0;
        $remaining = max(0, $quiz->time_limit - $elapsed);

        // Auto-close if time expired
        if ($remaining <= 0) {
            $quiz->update(['status' => 'closed', 'closed_at' => now()]);
            return response()->json(['quiz' => null]);
        }

        $data = [
            'id'               => $quiz->id,
            'question_text'    => $quiz->question_text,
            'question_type'    => $quiz->question_type,
            'options'          => $quiz->options,
            'allow_multiple'   => $quiz->allow_multiple,
            'time_limit'       => $quiz->time_limit,
            'remaining_seconds'=> $remaining,
            'activated_at'     => $quiz->activated_at?->toISOString(),
            'attachment_url'   => $quiz->question_type === 'pdf_exam' ? $quiz->attachment_url : null,
        ];

        return response()->json(['quiz' => $data]);
    }

    /**
     * Submit a student answer for an active quiz question.
     */
    public function submitAnswer(Request $request, LiveStream $liveStream, LiveStreamQuiz $quiz)
    {
        if ($quiz->live_stream_id !== $liveStream->id) {
            abort(404);
        }

        if ($quiz->status !== 'active') {
            return response()->json(['error' => 'هذا السؤال لم يعد نشطاً.'], 403);
        }

        // Server-side time check
        if ($quiz->activated_at) {
            $elapsed = (int) now()->diffInSeconds($quiz->activated_at, false) * -1;
            if ($elapsed >= $quiz->time_limit) {
                // Close the quiz server-side
                $quiz->update(['status' => 'closed', 'closed_at' => now()]);
                return response()->json(['error' => 'انتهى وقت الإجابة.'], 403);
            }
        }

        $data = $request->validate([
            'student_name'       => 'required|string|max:200',
            'student_identifier' => 'required|string|max:100',
            'answer'             => 'required|string|max:5000',
            'correction'         => 'nullable|string|max:2000',
        ]);

        // Calculate is_correct
        $isCorrect = null;
        if ($quiz->correct_answer !== null) {
            if (in_array($quiz->question_type, ['true_false', 'true_false_correction'])) {
                $isCorrect = strtolower($data['answer']) === strtolower($quiz->correct_answer);
            } elseif ($quiz->question_type === 'multiple_choice') {
                if ($quiz->allow_multiple) {
                    $submitted = json_decode($data['answer'], true) ?? [];
                    $correct   = json_decode($quiz->correct_answer, true) ?? [$quiz->correct_answer];
                    sort($submitted);
                    sort($correct);
                    $isCorrect = array_map('strval', $submitted) === array_map('strval', $correct);
                } else {
                    $isCorrect = (string) $data['answer'] === (string) $quiz->correct_answer;
                }
            }
        }

        // Upsert — prevent duplicate but allow updating
        $existing = LiveStreamQuizAnswer::where('live_stream_quiz_id', $quiz->id)
            ->where('student_identifier', $data['student_identifier'])
            ->first();

        if ($existing) {
            return response()->json(['error' => 'لقد أجبت على هذا السؤال بالفعل.'], 422);
        }

        LiveStreamQuizAnswer::create([
            'live_stream_quiz_id'=> $quiz->id,
            'live_stream_id'     => $liveStream->id,
            'student_name'       => $data['student_name'],
            'student_identifier' => $data['student_identifier'],
            'answer'             => $data['answer'],
            'correction'         => $data['correction'] ?? null,
            'is_correct'         => $isCorrect,
            'submitted_at'       => now(),
        ]);

        return response()->json(['message' => 'تم تسجيل إجابتك بنجاح.']);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function formatQuizForTeacher(LiveStreamQuiz $quiz): array
    {
        $remaining = null;
        if ($quiz->status === 'active' && $quiz->activated_at) {
            $elapsed   = (int) now()->diffInSeconds($quiz->activated_at, false) * -1;
            $remaining = max(0, $quiz->time_limit - $elapsed);
        }

        return [
            'id'               => $quiz->id,
            'question_text'    => $quiz->question_text,
            'question_type'    => $quiz->question_type,
            'options'          => $quiz->options,
            'correct_answer'   => $quiz->correct_answer,
            'allow_multiple'   => $quiz->allow_multiple,
            'time_limit'       => $quiz->time_limit,
            'status'           => $quiz->status,
            'activated_at'     => $quiz->activated_at?->toISOString(),
            'closed_at'        => $quiz->closed_at?->toISOString(),
            'sort_order'       => $quiz->sort_order,
            'attachment_path'  => $quiz->attachment_path,
            'attachment_url'   => $quiz->attachment_url,
            'answers_count'    => $quiz->answers_count ?? 0,
            'remaining_seconds'=> $remaining,
        ];
    }
}

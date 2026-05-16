<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    streamId   : { type: Number, required: true },
    studentName: { type: String, default: '' },
})

// ── Persistent student ID per stream ─────────────────────────────────────────
const STORAGE_ID_KEY      = `livestream_${props.streamId}_id`
const STORAGE_ANSWERS_KEY = `livestream_${props.streamId}_answered`

function getStudentId() {
    let id = localStorage.getItem(STORAGE_ID_KEY)
    if (!id) {
        id = crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now()
        localStorage.setItem(STORAGE_ID_KEY, id)
    }
    return id
}

function getAnsweredIds() {
    try { return JSON.parse(localStorage.getItem(STORAGE_ANSWERS_KEY) || '[]') } catch { return [] }
}

function markAnswered(quizId) {
    const answered = getAnsweredIds()
    if (!answered.includes(quizId)) {
        answered.push(quizId)
        localStorage.setItem(STORAGE_ANSWERS_KEY, JSON.stringify(answered))
    }
}

const studentId = getStudentId()

// ── State ──────────────────────────────────────────────────────────────────────
const activeQuiz    = ref(null)
const answer        = ref('')       // for true_false, fill_blank, essay, pdf_exam
const selectedOpts  = ref([])       // for multiple_choice
const correction    = ref('')       // for true_false_correction
const submitting    = ref(false)
const submitted     = ref(false)
const submitError   = ref('')
const pollRef       = ref(null)

const showOverlay = computed(() =>
    activeQuiz.value !== null && !submitted.value
)

// ── Countdown ─────────────────────────────────────────────────────────────────
const countdown         = ref(0)
const countdownInterval = ref(null)

function startCountdown(remaining) {
    stopCountdown()
    countdown.value = Math.max(0, remaining)
    if (countdown.value <= 0) return
    countdownInterval.value = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
            stopCountdown()
        }
    }, 1000)
}

function stopCountdown() {
    if (countdownInterval.value) {
        clearInterval(countdownInterval.value)
        countdownInterval.value = null
    }
}

// ── Polling ────────────────────────────────────────────────────────────────────
async function fetchActive() {
    try {
        const res = await axios.get(`/join/${props.streamId}/quiz/active`)
        const quiz = res.data.quiz

        if (!quiz) {
            // No active question — clear if we had one (it was closed)
            if (activeQuiz.value && !submitted.value) {
                clearQuiz()
            }
            return
        }

        // Already answered?
        if (getAnsweredIds().includes(quiz.id)) {
            return
        }

        // New question appeared or same one updated
        if (!activeQuiz.value || activeQuiz.value.id !== quiz.id) {
            // New question
            activeQuiz.value = quiz
            submitted.value  = false
            submitError.value= ''
            answer.value     = ''
            correction.value = ''
            selectedOpts.value = []
            startCountdown(quiz.remaining_seconds)
        } else {
            // Same question — just sync remaining seconds if countdown was off
            if (Math.abs(countdown.value - quiz.remaining_seconds) > 5) {
                startCountdown(quiz.remaining_seconds)
            }
        }
    } catch {
        // silent
    }
}

function clearQuiz() {
    stopCountdown()
    activeQuiz.value = null
    submitted.value  = false
    answer.value     = ''
    correction.value = ''
    selectedOpts.value = []
}

function startPolling() {
    stopPolling()
    fetchActive()
    pollRef.value = setInterval(fetchActive, 2500)
}

function stopPolling() {
    if (pollRef.value) { clearInterval(pollRef.value); pollRef.value = null }
}

onMounted(startPolling)
onUnmounted(() => { stopPolling(); stopCountdown(); stopExamPolling(); stopExamCountdown() })

// ── EXAM MODE ──────────────────────────────────────────────────────────────────

// Exam state
const examSession        = ref(null)   // { id, time_limit, remaining_seconds, quizzes[] }
const examQuestions      = ref([])
const examAnswers        = ref({})     // keyed by quiz id: { answer, correction, selectedOpts }
const currentQIdx        = ref(0)
const examSubmitted      = ref(false)
const examSubmitting     = ref(false)
const examSubmitError    = ref('')
const examPollRef        = ref(null)
const examCountdown      = ref(0)
const examCountdownRef   = ref(null)
const submittedSessionId = ref(null)   // prevent double-submit

const showExamOverlay = computed(() =>
    examSession.value !== null &&
    !examSubmitted.value &&
    examQuestions.value.length > 0 &&
    examCountdown.value > 0
)

const examCountdownPct = computed(() => {
    if (!examSession.value?.time_limit) return 0
    return Math.round((examCountdown.value / examSession.value.time_limit) * 100)
})

const examCountdownClass = computed(() => {
    if (examCountdown.value <= 30) return 'text-danger fw-bold'
    if (examCountdown.value <= 120) return 'text-warning fw-bold'
    return 'text-success fw-bold'
})

const currentQuestion = computed(() => examQuestions.value[currentQIdx.value] ?? null)

function isQuestionAnswered(qId) {
    const a = examAnswers.value[qId]
    if (!a) return false
    if (a.selectedOpts?.length > 0) return true
    return a.answer?.trim().length > 0
}

function startExamCountdown(remaining) {
    stopExamCountdown()
    examCountdown.value = Math.max(0, remaining)
    if (examCountdown.value <= 0) return
    examCountdownRef.value = setInterval(() => {
        examCountdown.value--
        if (examCountdown.value <= 0) {
            stopExamCountdown()
            // Auto-submit when timer hits 0
            submitExamAnswers(true)
        }
    }, 1000)
}

function stopExamCountdown() {
    if (examCountdownRef.value) { clearInterval(examCountdownRef.value); examCountdownRef.value = null }
}

async function fetchActiveExam() {
    try {
        const res = await axios.get(`/join/${props.streamId}/exam/active`)
        const exam = res.data.exam

        if (!exam) {
            // Exam ended while student was in it
            if (examSession.value && !examSubmitted.value) {
                // Auto-submit remaining
                submitExamAnswers(true)
            }
            return
        }

        if (submittedSessionId.value === exam.id) return

        // New exam appeared or same one
        if (!examSession.value || examSession.value.id !== exam.id) {
            examSession.value   = exam
            examQuestions.value = exam.quizzes
            examAnswers.value   = {}
            currentQIdx.value   = 0
            examSubmitted.value = false
            examSubmitError.value = ''
            submittedSessionId.value = null
            startExamCountdown(exam.remaining_seconds)
        } else {
            // Sync countdown if drifted
            if (Math.abs(examCountdown.value - exam.remaining_seconds) > 5) {
                startExamCountdown(exam.remaining_seconds)
            }
        }
    } catch { /* silent */ }
}

function startExamPolling() {
    stopExamPolling()
    fetchActiveExam()
    examPollRef.value = setInterval(fetchActiveExam, 2500)
}

function stopExamPolling() {
    if (examPollRef.value) { clearInterval(examPollRef.value); examPollRef.value = null }
}

function getExamAnswerPayload() {
    return examQuestions.value.map(q => {
        const a = examAnswers.value[q.id] ?? {}
        let answerText = ''
        if (q.question_type === 'multiple_choice') {
            answerText = JSON.stringify(a.selectedOpts ?? [])
        } else {
            answerText = a.answer ?? ''
        }
        return {
            quiz_id    : q.id,
            answer     : answerText,
            correction : a.correction ?? null,
        }
    })
}

async function submitExamAnswers(auto = false) {
    if (examSubmitting.value) return
    if (submittedSessionId.value === examSession.value?.id) return

    const session = examSession.value
    if (!session) return

    examSubmitting.value = true
    examSubmitError.value = ''
    try {
        await axios.post(`/join/${props.streamId}/exam/${session.id}/submit`, {
            student_name       : props.studentName || 'طالب',
            student_identifier : studentId,
            answers            : getExamAnswerPayload(),
        })
        submittedSessionId.value = session.id
        stopExamPolling()
        stopExamCountdown()
        examSubmitted.value = true
        setTimeout(() => {
            examSession.value    = null
            examSubmitted.value  = false
            examQuestions.value  = []
            examAnswers.value    = {}
        }, 3000)
    } catch (err) {
        examSubmitError.value = err.response?.data?.error || 'حدث خطأ، حاول مجدداً'
    } finally {
        examSubmitting.value = false
    }
}

function confirmSubmitExam() {
    if (!confirm('هل أنت متأكد من تسليم الامتحان؟\nالأسئلة غير المجاوبة ستُحسب إجابة فارغة.')) return
    submitExamAnswers(false)
}

// Exam answer helpers
function examToggleOpt(qId, idx, allowMultiple) {
    if (!examAnswers.value[qId]) examAnswers.value[qId] = { answer: '', correction: '', selectedOpts: [] }
    const opts = examAnswers.value[qId].selectedOpts
    if (!allowMultiple) {
        examAnswers.value[qId].selectedOpts = [idx]
        return
    }
    const i = opts.indexOf(idx)
    if (i === -1) opts.push(idx)
    else opts.splice(i, 1)
}

function isExamOptSelected(qId, idx) {
    return examAnswers.value[qId]?.selectedOpts?.includes(idx) ?? false
}

function ensureExamAnswer(qId) {
    if (!examAnswers.value[qId])
        examAnswers.value[qId] = { answer: '', correction: '', selectedOpts: [] }
}

// Start exam polling alongside normal quiz polling
startExamPolling()


// ── Submit ─────────────────────────────────────────────────────────────────────
async function submitAnswer() {
    if (submitting.value || countdown.value <= 0) return

    const q = activeQuiz.value
    if (!q) return

    // Build final answer string
    let finalAnswer = ''
    if (q.question_type === 'multiple_choice') {
        finalAnswer = JSON.stringify(selectedOpts.value)
    } else {
        finalAnswer = answer.value
    }

    if (!finalAnswer.trim() && q.question_type !== 'essay' && q.question_type !== 'pdf_exam') {
        submitError.value = 'يجب اختيار إجابة'
        return
    }

    submitting.value  = true
    submitError.value = ''
    try {
        await axios.post(`/join/${props.streamId}/quiz/${q.id}/answer`, {
            student_name       : props.studentName || 'طالب',
            student_identifier : studentId,
            answer             : finalAnswer,
            correction         : q.question_type === 'true_false_correction' ? correction.value : undefined,
        })
        markAnswered(q.id)
        submitted.value = true
        stopCountdown()
        setTimeout(() => clearQuiz(), 2000)
    } catch (err) {
        submitError.value = err.response?.data?.error || 'حدث خطأ، حاول مجدداً'
    } finally {
        submitting.value = false
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function toggleOpt(idx) {
    if (!activeQuiz.value?.allow_multiple) {
        selectedOpts.value = [idx]
        return
    }
    const i = selectedOpts.value.indexOf(idx)
    if (i === -1) selectedOpts.value.push(idx)
    else selectedOpts.value.splice(i, 1)
}

function isOptSelected(idx) {
    return selectedOpts.value.includes(idx)
}

function formatCountdown(s) {
    if (s <= 0) return '00:00'
    const m = Math.floor(s / 60)
    const sec = s % 60
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
}

const countdownClass = computed(() => {
    if (countdown.value <= 10) return 'text-danger fw-bold'
    if (countdown.value <= 30) return 'text-warning fw-bold'
    return 'text-success fw-bold'
})

const countdownPct = computed(() => {
    if (!activeQuiz.value?.time_limit) return 0
    return Math.round((countdown.value / activeQuiz.value.time_limit) * 100)
})

const timesUp = computed(() => countdown.value <= 0 && activeQuiz.value !== null)
</script>

<template>
    <!-- Overlay shown above the video -->
    <Transition name="quiz-overlay">
        <div v-if="showOverlay || submitted"
             class="quiz-overlay"
             :class="{ 'overlay-submitted': submitted }">

            <!-- Card -->
            <div class="quiz-card" :class="{ 'card-submitted': submitted }">

                <!-- Success message -->
                <template v-if="submitted">
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem;"></i>
                        <div class="text-white mt-2 fw-semibold">تم تسجيل إجابتك ✓</div>
                    </div>
                </template>

                <template v-else-if="showOverlay && activeQuiz">
                    <!-- Header: timer + close -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-primary" style="font-size:11px;">
                            <i class="bi bi-patch-question me-1"></i>سؤال تفاعلي
                        </span>
                        <span :class="countdownClass" style="font-size:18px;font-variant-numeric:tabular-nums;">
                            <i class="bi bi-clock me-1" style="font-size:13px;"></i>
                            {{ formatCountdown(countdown) }}
                        </span>
                    </div>

                    <!-- Countdown progress bar -->
                    <div class="progress mb-3" style="height:4px;background:#2a2a4a;">
                        <div
                            class="progress-bar"
                            :class="countdownPct <= 20 ? 'bg-danger' : countdownPct <= 50 ? 'bg-warning' : 'bg-success'"
                            :style="`width:${countdownPct}%;transition:width 1s linear;`"
                        ></div>
                    </div>

                    <!-- Question text -->
                    <p class="text-white fw-semibold mb-3" style="font-size:15px;line-height:1.6;">
                        {{ activeQuiz.question_text }}
                    </p>

                    <!-- ── PDF viewer ──────────────────────────────────────── -->
                    <template v-if="activeQuiz.question_type === 'pdf_exam'">
                        <div class="mb-3 rounded overflow-hidden" style="height:280px;background:#000;">
                            <iframe
                                v-if="activeQuiz.attachment_url"
                                :src="`${activeQuiz.attachment_url}#toolbar=0`"
                                style="width:100%;height:100%;border:none;"
                                title="امتحان PDF"
                            ></iframe>
                            <div v-else class="d-flex align-items-center justify-content-center h-100 text-secondary">
                                <span>ملف PDF غير متاح</span>
                            </div>
                        </div>
                        <textarea
                            v-model="answer"
                            rows="3"
                            class="quiz-input w-100 mb-2"
                            :disabled="timesUp"
                            placeholder="اكتب إجابتك هنا (اختياري)..."
                        ></textarea>
                    </template>

                    <!-- ── True / False ────────────────────────────────────── -->
                    <template v-else-if="activeQuiz.question_type === 'true_false' || activeQuiz.question_type === 'true_false_correction'">
                        <div class="d-flex gap-3 mb-3">
                            <button
                                @click="answer = 'true'"
                                class="btn flex-fill fw-semibold"
                                :class="answer === 'true' ? 'btn-success' : 'btn-outline-success'"
                                :disabled="timesUp"
                                style="font-size:16px;"
                            >✅ صح</button>
                            <button
                                @click="answer = 'false'"
                                class="btn flex-fill fw-semibold"
                                :class="answer === 'false' ? 'btn-danger' : 'btn-outline-danger'"
                                :disabled="timesUp"
                                style="font-size:16px;"
                            >❌ غلط</button>
                        </div>
                        <!-- Correction field (true_false_correction type when answered غلط) -->
                        <div v-if="activeQuiz.question_type === 'true_false_correction' && answer === 'false'" class="mb-3">
                            <label class="text-secondary small mb-1">صحِّح الخطأ:</label>
                            <input
                                v-model="correction"
                                type="text"
                                class="quiz-input w-100"
                                :disabled="timesUp"
                                placeholder="اكتب الإجابة الصحيحة..."
                            />
                        </div>
                    </template>

                    <!-- ── Fill blank ──────────────────────────────────────── -->
                    <template v-else-if="activeQuiz.question_type === 'fill_blank'">
                        <input
                            v-model="answer"
                            type="text"
                            class="quiz-input w-100 mb-3"
                            :disabled="timesUp"
                            placeholder="اكتب إجابتك..."
                        />
                    </template>

                    <!-- ── Multiple choice ─────────────────────────────────── -->
                    <template v-else-if="activeQuiz.question_type === 'multiple_choice'">
                        <div class="d-flex flex-column gap-2 mb-3">
                            <button
                                v-for="(opt, idx) in activeQuiz.options"
                                :key="idx"
                                @click="toggleOpt(idx)"
                                class="btn text-start"
                                :class="isOptSelected(idx) ? 'btn-primary' : 'btn-outline-secondary'"
                                :disabled="timesUp"
                                style="font-size:13px;"
                            >{{ opt }}</button>
                        </div>
                    </template>

                    <!-- ── Essay ───────────────────────────────────────────── -->
                    <template v-else-if="activeQuiz.question_type === 'essay'">
                        <textarea
                            v-model="answer"
                            rows="4"
                            class="quiz-input w-100 mb-3"
                            :disabled="timesUp"
                            placeholder="اكتب إجابتك المفصلة هنا..."
                        ></textarea>
                    </template>

                    <!-- Error -->
                    <div v-if="submitError" class="alert alert-danger py-1 mb-2 small">{{ submitError }}</div>

                    <!-- Time's up banner -->
                    <div v-if="timesUp" class="text-center text-danger fw-semibold mb-2">
                        <i class="bi bi-alarm me-1"></i>انتهى وقت الإجابة
                    </div>

                    <!-- Submit button -->
                    <button
                        v-if="!timesUp"
                        @click="submitAnswer"
                        class="btn btn-primary w-100 fw-semibold"
                        :disabled="submitting"
                    >
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="bi bi-send me-2"></i>
                        إرسال الإجابة
                    </button>
                </template>

            </div>
        </div>
    </Transition>

    <!-- ── EXAM OVERLAY ────────────────────────────────────────────────────── -->
    <Transition name="quiz-overlay">
        <div v-if="showExamOverlay || examSubmitted"
             class="quiz-overlay"
             :class="{ 'overlay-submitted': examSubmitted }">
            <div class="quiz-card exam-card" :class="{ 'card-submitted': examSubmitted }">

                <!-- Submitted state -->
                <template v-if="examSubmitted">
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem;"></i>
                        <div class="text-white mt-2 fw-semibold">تم تسليم الامتحان ✓</div>
                        <div class="text-secondary small mt-1">شكراً، سيتم مراجعة إجاباتك</div>
                    </div>
                </template>

                <template v-else-if="showExamOverlay && examSession">

                    <!-- Header: exam badge + countdown -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-warning text-dark" style="font-size:11px;">
                            <i class="bi bi-journal-check me-1"></i>امتحان — {{ examQuestions.length }} سؤال
                        </span>
                        <span :class="examCountdownClass" style="font-size:18px;font-variant-numeric:tabular-nums;">
                            <i class="bi bi-clock me-1" style="font-size:13px;"></i>
                            {{ formatCountdown(examCountdown) }}
                        </span>
                    </div>

                    <!-- Global countdown bar -->
                    <div class="progress mb-3" style="height:4px;background:#2a2a4a;">
                        <div
                            class="progress-bar"
                            :class="examCountdownPct <= 20 ? 'bg-danger' : examCountdownPct <= 50 ? 'bg-warning' : 'bg-success'"
                            :style="`width:${examCountdownPct}%;transition:width 1s linear;`"
                        ></div>
                    </div>

                    <!-- Question navigator pills -->
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <button
                            v-for="(q, qi) in examQuestions"
                            :key="q.id"
                            @click="currentQIdx = qi"
                            class="btn btn-sm"
                            :style="currentQIdx === qi
                                ? 'background:#0d6efd;color:#fff;min-width:32px;padding:2px 6px;font-size:12px;'
                                : isQuestionAnswered(q.id)
                                    ? 'background:#198754;color:#fff;min-width:32px;padding:2px 6px;font-size:12px;'
                                    : 'background:#2a2a4a;color:#aaa;min-width:32px;padding:2px 6px;font-size:12px;'"
                        >{{ qi + 1 }}</button>
                    </div>

                    <!-- Current question -->
                    <template v-if="currentQuestion">
                        <!-- Question text -->
                        <p class="text-white fw-semibold mb-3" style="font-size:14px;line-height:1.6;">
                            {{ currentQIdx + 1 }}. {{ currentQuestion.question_text }}
                        </p>

                        <!-- PDF viewer -->
                        <template v-if="currentQuestion.question_type === 'pdf_exam'">
                            <div class="mb-3 rounded overflow-hidden" style="height:220px;background:#000;">
                                <iframe
                                    v-if="currentQuestion.attachment_url"
                                    :src="`${currentQuestion.attachment_url}#toolbar=0`"
                                    style="width:100%;height:100%;border:none;"
                                    title="امتحان PDF"
                                ></iframe>
                                <div v-else class="d-flex align-items-center justify-content-center h-100 text-secondary"><span>ملف PDF غير متاح</span></div>
                            </div>
                            {{ ensureExamAnswer(currentQuestion.id) }}
                            <textarea
                                v-model="examAnswers[currentQuestion.id].answer"
                                rows="3"
                                class="quiz-input w-100 mb-2"
                                placeholder="اكتب إجابتك هنا (اختياري)..."
                            ></textarea>
                        </template>

                        <!-- True / False -->
                        <template v-else-if="currentQuestion.question_type === 'true_false' || currentQuestion.question_type === 'true_false_correction'">
                            {{ ensureExamAnswer(currentQuestion.id) }}
                            <div class="d-flex gap-3 mb-3">
                                <button
                                    @click="examAnswers[currentQuestion.id].answer = 'true'"
                                    class="btn flex-fill fw-semibold"
                                    :class="examAnswers[currentQuestion.id]?.answer === 'true' ? 'btn-success' : 'btn-outline-success'"
                                    style="font-size:15px;"
                                >✅ صح</button>
                                <button
                                    @click="examAnswers[currentQuestion.id].answer = 'false'"
                                    class="btn flex-fill fw-semibold"
                                    :class="examAnswers[currentQuestion.id]?.answer === 'false' ? 'btn-danger' : 'btn-outline-danger'"
                                    style="font-size:15px;"
                                >❌ غلط</button>
                            </div>
                            <div v-if="currentQuestion.question_type === 'true_false_correction' && examAnswers[currentQuestion.id]?.answer === 'false'" class="mb-3">
                                <label class="text-secondary small mb-1">صحِّح الخطأ:</label>
                                <input
                                    v-model="examAnswers[currentQuestion.id].correction"
                                    type="text"
                                    class="quiz-input w-100"
                                    placeholder="اكتب الإجابة الصحيحة..."
                                />
                            </div>
                        </template>

                        <!-- Fill blank -->
                        <template v-else-if="currentQuestion.question_type === 'fill_blank'">
                            {{ ensureExamAnswer(currentQuestion.id) }}
                            <input
                                v-model="examAnswers[currentQuestion.id].answer"
                                type="text"
                                class="quiz-input w-100 mb-3"
                                placeholder="اكتب إجابتك..."
                            />
                        </template>

                        <!-- Multiple choice -->
                        <template v-else-if="currentQuestion.question_type === 'multiple_choice'">
                            {{ ensureExamAnswer(currentQuestion.id) }}
                            <div class="d-flex flex-column gap-2 mb-3">
                                <button
                                    v-for="(opt, optIdx) in currentQuestion.options"
                                    :key="optIdx"
                                    @click="examToggleOpt(currentQuestion.id, optIdx, currentQuestion.allow_multiple)"
                                    class="btn text-start"
                                    :class="isExamOptSelected(currentQuestion.id, optIdx) ? 'btn-primary' : 'btn-outline-secondary'"
                                    style="font-size:13px;"
                                >{{ opt }}</button>
                            </div>
                        </template>

                        <!-- Essay -->
                        <template v-else-if="currentQuestion.question_type === 'essay'">
                            {{ ensureExamAnswer(currentQuestion.id) }}
                            <textarea
                                v-model="examAnswers[currentQuestion.id].answer"
                                rows="4"
                                class="quiz-input w-100 mb-3"
                                placeholder="اكتب إجابتك المفصلة هنا..."
                            ></textarea>
                        </template>
                    </template>

                    <!-- Navigation arrows -->
                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <button
                            @click="currentQIdx = Math.max(0, currentQIdx - 1)"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="currentQIdx === 0"
                        ><i class="bi bi-chevron-right me-1"></i>السابق</button>
                        <button
                            @click="currentQIdx = Math.min(examQuestions.length - 1, currentQIdx + 1)"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="currentQIdx === examQuestions.length - 1"
                        >التالي<i class="bi bi-chevron-left ms-1"></i></button>
                    </div>

                    <!-- Error -->
                    <div v-if="examSubmitError" class="alert alert-danger py-1 mb-2 small">{{ examSubmitError }}</div>

                    <!-- Submit exam button -->
                    <button
                        @click="confirmSubmitExam"
                        class="btn btn-danger w-100 fw-semibold"
                        :disabled="examSubmitting"
                    >
                        <span v-if="examSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="bi bi-check2-square me-2"></i>
                        تسليم الامتحان
                        <span class="badge bg-white text-danger ms-1" style="font-size:10px;">
                            {{ examQuestions.filter(q => isQuestionAnswered(q.id)).length }}/{{ examQuestions.length }}
                        </span>
                    </button>
                    <div class="text-secondary text-center mt-1" style="font-size:10px;">
                        الأسئلة الخضراء تم الإجابة عليها — الرمادية لم تُجَب بعد
                    </div>

                </template>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.quiz-overlay {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1050;
    display: flex;
    justify-content: center;
    padding: 0 16px 16px;
    pointer-events: none;
}
.quiz-card {
    background: #1a1a2e;
    border: 1px solid #2a2a4a;
    border-radius: 16px;
    padding: 20px;
    width: 100%;
    max-width: 520px;
    max-height: 85vh;
    overflow-y: auto;
    pointer-events: all;
    box-shadow: 0 -4px 30px rgba(0,0,0,.6);
}
.card-submitted {
    border-color: #198754;
    max-height: 140px;
}
.exam-card {
    max-width: 600px;
    max-height: 90vh;
    border-color: #f0ad00;
}
.overlay-submitted { pointer-events: none; }

.quiz-input {
    background: #0d0d1a;
    color: #fff;
    border: 1px solid #2a2a4a;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    outline: none;
    resize: vertical;
}
.quiz-input:focus { border-color: #0d6efd; }
.quiz-input:disabled { opacity: .5; }

/* Transition */
.quiz-overlay-enter-active, .quiz-overlay-leave-active { transition: all .3s ease; }
.quiz-overlay-enter-from, .quiz-overlay-leave-to { transform: translateY(100%); opacity: 0; }
</style>

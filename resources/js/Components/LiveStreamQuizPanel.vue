<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import axios from 'axios'
import { toast } from 'vue3-toastify'

const emit = defineEmits(['close'])

const props = defineProps({
    streamId:    { type: Number, required: true },
    routePrefix: { type: String, default: 'admin' },
})

const baseUrl = computed(() => `/${props.routePrefix}/live-streams/${props.streamId}`)

// ── State ──────────────────────────────────────────────────────────────────────
const activeTab      = ref('new')   // 'new' | 'active' | 'queue' | 'history'
const quizzes        = ref([])
const loadingQuizzes = ref(false)
const submitting     = ref(false)

// Active question live answers
const activeQuiz     = computed(() => quizzes.value.find(q => q.status === 'active') ?? null)
const activeAnswers  = ref(null)
const loadingAnswers = ref(false)
const answersPollRef = ref(null)

// Multi-question queue
const autoAdvance = ref(false)
const globalTime  = ref('')
const settingTime = ref(false)

// Exam mode
const activeExam       = ref(null)   // { id, time_limit, status, remaining_seconds, activated_at }
const examStatus       = ref(null)   // { submitted_count, per_question[] }
const examPollRef      = ref(null)
const launchingExam    = ref(false)
const closingExam      = ref(false)
const examCountdown    = ref(0)
const examCountdownRef = ref(null)

// Form state
const form = ref(resetForm())

function resetForm() {
    return {
        question_text : '',
        question_type : 'true_false',
        options       : ['', ''],
        correct_answer: '',
        allow_multiple: false,
        time_limit    : 60,
        attachment    : null,
        attachmentName: '',
    }
}

// Question type definitions
const questionTypes = [
    { value: 'true_false',            label: '✅ صح / غلط',         desc: 'إجابة صح أو غلط فقط' },
    { value: 'true_false_correction', label: '✅ صح/غلط + تصحيح',   desc: 'صح أو غلط مع تصحيح الخطأ' },
    { value: 'fill_blank',            label: '✏️ أكمل الفراغ',       desc: 'إجابة نصية قصيرة' },
    { value: 'multiple_choice',       label: '🔘 اختيار متعدد',      desc: 'اختر من قائمة خيارات' },
    { value: 'essay',                 label: '📝 مقالي',              desc: 'إجابة مفتوحة طويلة' },
    { value: 'pdf_exam',              label: '📄 امتحان PDF',         desc: 'ارفع ملف PDF كامتحان' },
]

const timeLimitPresets = [
    { label: '30ث', value: 30 },
    { label: '1د',  value: 60 },
    { label: '2د',  value: 120 },
    { label: '5د',  value: 300 },
    { label: '10د', value: 600 },
]

// ── Computed ───────────────────────────────────────────────────────────────────
const draftQuizzes  = computed(() => quizzes.value.filter(q => q.status === 'draft'))
const closedQuizzes = computed(() => quizzes.value.filter(q => q.status === 'closed'))
const hasActive     = computed(() => activeQuiz.value !== null)
const queueCount    = computed(() => draftQuizzes.value.length)

function formatTime(seconds) {
    const m = Math.floor(seconds / 60)
    const s = seconds % 60
    return m > 0 ? `${m}:${String(s).padStart(2, '0')}` : `${s}ث`
}

// ── Fetch quizzes list ─────────────────────────────────────────────────────────
async function fetchQuizzes() {
    loadingQuizzes.value = true
    try {
        const res = await axios.get(`${baseUrl.value}/quiz`)
        quizzes.value = res.data.quizzes
    } catch {
        // silent
    } finally {
        loadingQuizzes.value = false
    }
}

// ── Poll active answers every 2s ──────────────────────────────────────────────
function startAnswerPolling(quizId) {
    stopAnswerPolling()
    answersPollRef.value = setInterval(() => fetchAnswers(quizId), 2000)
    fetchAnswers(quizId)
}

function stopAnswerPolling() {
    if (answersPollRef.value) {
        clearInterval(answersPollRef.value)
        answersPollRef.value = null
    }
}

async function fetchAnswers(quizId) {
    if (loadingAnswers.value) return
    loadingAnswers.value = true
    try {
        const res = await axios.get(`${baseUrl.value}/quiz/${quizId}/answers`)
        activeAnswers.value = res.data
        const idx = quizzes.value.findIndex(q => q.id === quizId)
        if (idx !== -1 && activeAnswers.value.quiz) {
            quizzes.value[idx].remaining_seconds = activeAnswers.value.quiz.remaining_seconds
        }
        if (activeAnswers.value.quiz?.status === 'closed') {
            stopAnswerPolling()
            await fetchQuizzes()
            if (autoAdvance.value && draftQuizzes.value.length > 0) {
                await activateQuiz(draftQuizzes.value[0])
            } else {
                autoAdvance.value = false
            }
        }
    } catch {
        // silent
    } finally {
        loadingAnswers.value = false
    }
}

watch(activeQuiz, (q) => {
    if (q) {
        startAnswerPolling(q.id)
        activeTab.value = 'active'
    } else {
        stopAnswerPolling()
        activeAnswers.value = null
    }
}, { immediate: true })

onUnmounted(() => { stopAnswerPolling(); stopExamPolling(); stopExamCountdown() })

// ── Exam mode ─────────────────────────────────────────────────────────────────
function startExamCountdown(remaining) {
    stopExamCountdown()
    examCountdown.value = Math.max(0, remaining)
    if (examCountdown.value <= 0) return
    examCountdownRef.value = setInterval(() => {
        examCountdown.value--
        if (examCountdown.value <= 0) stopExamCountdown()
    }, 1000)
}
function stopExamCountdown() {
    if (examCountdownRef.value) { clearInterval(examCountdownRef.value); examCountdownRef.value = null }
}

async function fetchExamStatus() {
    if (!activeExam.value) return
    try {
        const res = await axios.get(`${baseUrl.value}/exam/${activeExam.value.id}/status`)
        examStatus.value  = res.data
        const sess = res.data.session
        activeExam.value  = sess
        if (sess.status === 'closed') {
            stopExamPolling()
            stopExamCountdown()
            activeExam.value = null
            examStatus.value  = null
            await fetchQuizzes()
            toast.info('انتهى الامتحان')
        } else {
            // Sync countdown if drifted
            if (Math.abs(examCountdown.value - sess.remaining_seconds) > 5) {
                startExamCountdown(sess.remaining_seconds)
            }
        }
    } catch { /* silent */ }
}

function startExamPolling() {
    stopExamPolling()
    fetchExamStatus()
    examPollRef.value = setInterval(fetchExamStatus, 2000)
}
function stopExamPolling() {
    if (examPollRef.value) { clearInterval(examPollRef.value); examPollRef.value = null }
}

async function launchExam() {
    const t = parseInt(globalTime.value)
    if (!t || t < 10) { toast.error('حدد وقتاً للامتحان (على الأقل 10 ثواني)'); return }
    if (!draftQuizzes.value.length) { toast.error('لا توجد أسئلة في الطابور'); return }
    launchingExam.value = true
    try {
        const res = await axios.post(`${baseUrl.value}/exam/launch`, { time_limit: t })
        activeExam.value = res.data.session
        examCountdown.value = res.data.session.remaining_seconds
        startExamCountdown(res.data.session.remaining_seconds)
        startExamPolling()
        await fetchQuizzes()
        activeTab.value = 'active'
        toast.success(`تم إطلاق الامتحان — ${res.data.quizzes.length} سؤال`)
    } catch (err) {
        toast.error(err.response?.data?.error || 'حدث خطأ')
    } finally {
        launchingExam.value = false
    }
}

async function closeExam() {
    if (!activeExam.value) return
    if (!confirm('هل تريد إنهاء الامتحان الآن؟')) return
    closingExam.value = true
    try {
        await axios.patch(`${baseUrl.value}/exam/${activeExam.value.id}/close`)
        stopExamPolling()
        stopExamCountdown()
        activeExam.value = null
        examStatus.value  = null
        await fetchQuizzes()
        toast.success('تم إنهاء الامتحان')
    } catch (err) {
        toast.error(err.response?.data?.error || 'حدث خطأ')
    } finally {
        closingExam.value = false
    }
}

function getExamCountdownClass() {
    if (examCountdown.value <= 30) return 'text-danger fw-bold'
    if (examCountdown.value <= 120) return 'text-warning fw-bold'
    return 'text-success fw-bold'
}

// ── Form: options management ───────────────────────────────────────────────────
function addOption() {
    if (form.value.options.length < 10) form.value.options.push('')
}
function removeOption(idx) {
    if (form.value.options.length > 2) form.value.options.splice(idx, 1)
}

// ── File picker ────────────────────────────────────────────────────────────────
function onFileChange(e) {
    const file = e.target.files[0]
    if (!file) return
    if (file.size > 20 * 1024 * 1024) {
        toast.error('حجم الملف يجب أن يكون أقل من 20MB')
        e.target.value = ''
        return
    }
    form.value.attachment     = file
    form.value.attachmentName = file.name
}

// ── Build FormData from form ───────────────────────────────────────────────────
function buildFormData() {
    const fd = new FormData()
    fd.append('question_text',  form.value.question_text)
    fd.append('question_type',  form.value.question_type)
    fd.append('time_limit',     form.value.time_limit)
    fd.append('allow_multiple', form.value.allow_multiple ? '1' : '0')
    if (form.value.correct_answer) fd.append('correct_answer', form.value.correct_answer)
    if (form.value.question_type === 'multiple_choice') {
        form.value.options.filter(o => o.trim()).forEach((opt, i) => fd.append(`options[${i}]`, opt))
    }
    if (form.value.attachment) fd.append('attachment', form.value.attachment)
    return fd
}

function validateForm() {
    if (!form.value.question_text.trim()) { toast.error('أدخل نص السؤال'); return false }
    if (form.value.question_type === 'pdf_exam' && !form.value.attachment) { toast.error('يجب رفع ملف PDF'); return false }
    if (form.value.question_type === 'multiple_choice' && form.value.options.filter(o => o.trim()).length < 2) {
        toast.error('أضف على الأقل خيارين'); return false
    }
    return true
}

// ── Add to queue (save as draft) ──────────────────────────────────────────────
async function addToQueue() {
    if (!validateForm()) return
    submitting.value = true
    try {
        const res = await axios.post(`${baseUrl.value}/quiz`, buildFormData(), {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        quizzes.value.push(res.data.quiz)
        toast.success(`✅ تمت الإضافة — أضف سؤالاً آخر أو انتقل للطابور`)
        form.value = resetForm()
        // Stay on 'new' tab so teacher can immediately add another question
    } catch (err) {
        toast.error(err.response?.data?.message || 'حدث خطأ')
    } finally {
        submitting.value = false
    }
}

// ── Launch single immediately ──────────────────────────────────────────────────
async function launchNow() {
    if (!validateForm()) return
    submitting.value = true
    try {
        const res = await axios.post(`${baseUrl.value}/quiz`, buildFormData(), {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        const quiz = res.data.quiz
        quizzes.value.push(quiz)
        form.value = resetForm()
        await activateQuiz(quiz)
    } catch (err) {
        toast.error(err.response?.data?.message || 'حدث خطأ')
    } finally {
        submitting.value = false
    }
}

// ── Set global time on all drafts ─────────────────────────────────────────────
async function applyGlobalTime() {
    const t = parseInt(globalTime.value)
    if (!t || t < 10) { toast.error('أدخل وقتاً صحيحاً (على الأقل 10 ثواني)'); return }
    settingTime.value = true
    try {
        await axios.patch(`${baseUrl.value}/quiz/set-time`, { time_limit: t })
        quizzes.value = quizzes.value.map(q =>
            q.status === 'draft' ? { ...q, time_limit: t } : q
        )
        toast.success(`تم تعيين ${formatTime(t)} لجميع (${queueCount.value}) أسئلة`)
    } catch (err) {
        toast.error(err.response?.data?.message || 'حدث خطأ')
    } finally {
        settingTime.value = false
    }
}

// ── Launch full queue ─────────────────────────────────────────────────────────
async function launchQueue() {
    if (!draftQuizzes.value.length) { toast.error('لا توجد أسئلة في الطابور'); return }
    autoAdvance.value = true
    await activateQuiz(draftQuizzes.value[0])
}

// ── Activate / Close ──────────────────────────────────────────────────────────
async function activateQuiz(quiz) {
    try {
        await axios.patch(`${baseUrl.value}/quiz/${quiz.id}/activate`)
        await fetchQuizzes()
        toast.success('تم تفعيل السؤال')
    } catch (err) {
        toast.error(err.response?.data?.error || 'حدث خطأ')
    }
}

async function closeQuiz(quiz) {
    try {
        await axios.patch(`${baseUrl.value}/quiz/${quiz.id}/close`)
        stopAnswerPolling()
        activeAnswers.value = null
        await fetchQuizzes()
        if (autoAdvance.value && draftQuizzes.value.length > 0) {
            await activateQuiz(draftQuizzes.value[0])
        } else {
            autoAdvance.value = false
            toast.success('تم إغلاق السؤال')
        }
    } catch (err) {
        toast.error(err.response?.data?.error || 'حدث خطأ')
    }
}

async function deleteQuiz(quiz) {
    if (!confirm('هل تريد حذف هذا السؤال؟')) return
    try {
        await axios.delete(`${baseUrl.value}/quiz/${quiz.id}`)
        quizzes.value = quizzes.value.filter(q => q.id !== quiz.id)
        toast.success('تم الحذف')
    } catch {
        toast.error('حدث خطأ')
    }
}

// ── Edit draft time inline ────────────────────────────────────────────────────
async function updateDraftTime(quiz, newTime) {
    const t = parseInt(newTime)
    if (!t || t < 10) return
    try {
        await axios.put(
            `${baseUrl.value}/quiz/${quiz.id}`,
            {
                question_text:  quiz.question_text,
                question_type:  quiz.question_type,
                options:        quiz.options,
                correct_answer: quiz.correct_answer,
                allow_multiple: quiz.allow_multiple,
                time_limit:     t,
            }
        )
        const idx = quizzes.value.findIndex(q => q.id === quiz.id)
        if (idx !== -1) quizzes.value[idx].time_limit = t
    } catch {
        toast.error('فشل تحديث الوقت')
    }
}

// ── Countdown display ─────────────────────────────────────────────────────────
function getCountdownLabel(quiz) {
    if (!quiz || quiz.status !== 'active') return ''
    const r = quiz.remaining_seconds ?? 0
    return r <= 0 ? 'انتهى الوقت' : formatTime(r)
}
function getCountdownClass(quiz) {
    const r = quiz?.remaining_seconds ?? 0
    if (r <= 10) return 'text-danger fw-bold'
    if (r <= 30) return 'text-warning fw-bold'
    return 'text-success fw-bold'
}
function getTypeLabel(type) {
    return questionTypes.find(t => t.value === type)?.label ?? type
}
function getAnswerLabel(quiz, answer) {
    if (quiz.question_type === 'true_false' || quiz.question_type === 'true_false_correction') {
        return answer === 'true' ? '✅ صح' : '❌ غلط'
    }
    if (quiz.question_type === 'multiple_choice' && quiz.options) {
        try {
            const parsed = JSON.parse(answer)
            if (Array.isArray(parsed)) return parsed.map(i => quiz.options[i] ?? i).join('، ')
        } catch {}
        return quiz.options[answer] ?? answer
    }
    return answer
}

// Initialise
fetchQuizzes()
</script>

<template>
    <div class="quiz-panel d-flex flex-column" style="height:100%;background:#1a1a2e;overflow:hidden;">

        <!-- Header -->
        <div class="px-3 py-2 d-flex align-items-center justify-content-between flex-shrink-0"
             style="background:#12122a;border-bottom:1px solid #2a2a4a;">
            <span class="text-white fw-semibold" style="font-size:14px;">
                <i class="bi bi-patch-question me-1 text-warning"></i>الأسئلة التفاعلية
            </span>
            <div class="d-flex align-items-center gap-2">
                <span v-if="autoAdvance" class="badge bg-warning text-dark" style="font-size:10px;">
                    <i class="bi bi-play-circle me-1"></i>تشغيل تلقائي
                </span>
                <span v-if="activeExam" class="badge bg-success" style="font-size:11px;">
                    <i class="bi bi-journal-check me-1"></i>امتحان نشط
                </span>
                <span v-else-if="hasActive" class="badge bg-danger" style="font-size:11px;">نشط</span>
                <button @click="emit('close')" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-1" title="إغلاق">
                    <i class="bi bi-x-lg" style="font-size:13px;"></i>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="d-flex flex-shrink-0" style="border-bottom:1px solid #2a2a4a;">
            <button
                v-if="hasActive || activeExam"
                @click="activeTab = 'active'"
                class="flex-fill py-2 border-0 small fw-semibold"
                :style="activeTab === 'active' ? 'background:#0d6efd;color:#fff;' : 'background:transparent;color:#aaa;'"
            >{{ activeExam ? '📋 الامتحان النشط' : 'السؤال النشط' }}</button>
            <button
                @click="activeTab = 'new'"
                class="flex-fill py-2 border-0 small fw-semibold"
                :style="activeTab === 'new' ? 'background:#0d6efd;color:#fff;' : 'background:transparent;color:#aaa;'"
            >+ سؤال جديد</button>
            <button
                @click="activeTab = 'queue'; fetchQuizzes()"
                class="flex-fill py-2 border-0 small fw-semibold position-relative"
                :style="activeTab === 'queue' ? 'background:#0d6efd;color:#fff;' : 'background:transparent;color:#aaa;'"
            >
                الطابور
                <span v-if="queueCount > 0"
                    class="badge bg-warning text-dark position-absolute"
                    style="top:4px;right:6px;font-size:9px;">{{ queueCount }}</span>
            </button>
            <button
                @click="activeTab = 'history'; fetchQuizzes()"
                class="flex-fill py-2 border-0 small fw-semibold"
                :style="activeTab === 'history' ? 'background:#0d6efd;color:#fff;' : 'background:transparent;color:#aaa;'"
            >السابقة</button>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-grow-1 overflow-auto p-3">

            <!-- ACTIVE EXAM TAB -->
            <template v-if="activeTab === 'active' && activeExam && !activeQuiz">
                <div class="text-white mb-3">
                    <!-- Exam header -->
                    <div class="d-flex align-items-center justify-content-between mb-3 p-2 rounded"
                         style="background:#12122a;border:1px solid #2a2a4a;">
                        <div>
                            <div class="badge bg-success mb-1" style="font-size:11px;">
                                <i class="bi bi-journal-check me-1"></i>امتحان نشط
                            </div>
                            <div class="text-secondary small">{{ examStatus?.per_question?.length ?? 0 }} سؤال</div>
                        </div>
                        <div class="text-end">
                            <div :class="getExamCountdownClass()" style="font-size:22px;font-variant-numeric:tabular-nums;">
                                <i class="bi bi-clock me-1" style="font-size:14px;"></i>
                                {{ formatTime(examCountdown) }}
                            </div>
                            <div class="text-secondary" style="font-size:11px;">الوقت المتبقي</div>
                        </div>
                    </div>

                    <!-- Global progress bar -->
                    <div class="progress mb-3" style="height:6px;background:#2a2a4a;">
                        <div class="progress-bar"
                             :class="examCountdown / activeExam.time_limit <= 0.2 ? 'bg-danger' : examCountdown / activeExam.time_limit <= 0.5 ? 'bg-warning' : 'bg-success'"
                             :style="`width:${activeExam.time_limit ? Math.round(examCountdown / activeExam.time_limit * 100) : 0}%;transition:width 1s linear;`"
                        ></div>
                    </div>

                    <!-- Submissions count -->
                    <div class="rounded p-2 mb-3 d-flex align-items-center gap-2"
                         style="background:#1e3a1e;border:1px solid #2d5a2d;font-size:13px;">
                        <i class="bi bi-people-fill text-success"></i>
                        <span class="text-white fw-semibold">{{ examStatus?.submitted_count ?? 0 }}</span>
                        <span class="text-secondary">طالب سلّم الامتحان</span>
                    </div>

                    <!-- Per-question answer counts -->
                    <div v-if="examStatus?.per_question?.length" class="mb-3">
                        <div class="small text-secondary mb-2 fw-semibold">استجابات لكل سؤال</div>
                        <div v-for="(qStat, qi) in examStatus.per_question" :key="qStat.id"
                             class="d-flex align-items-center gap-2 mb-2 p-2 rounded"
                             style="background:#12122a;border:1px solid #2a2a4a;font-size:12px;">
                            <span class="badge bg-secondary bg-opacity-50" style="font-size:10px;">{{ qi + 1 }}</span>
                            <span class="text-white flex-grow-1 text-truncate">{{ qStat.question_text }}</span>
                            <span class="badge bg-primary bg-opacity-25 text-primary">{{ qStat.total }}</span>
                            <span v-if="qStat.correct > 0" class="badge bg-success bg-opacity-25 text-success">{{ qStat.correct }} ✓</span>
                        </div>
                    </div>

                    <!-- Close exam button -->
                    <button @click="closeExam" class="btn btn-outline-danger w-100" :disabled="closingExam">
                        <span v-if="closingExam" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-stop-circle me-1"></i>إنهاء الامتحان
                    </button>
                </div>
            </template>

            <!-- ACTIVE QUESTION TAB -->
            <template v-if="activeTab === 'active' && activeQuiz">
                <div class="text-white mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-success">{{ getTypeLabel(activeQuiz.question_type) }}</span>
                        <span :class="getCountdownClass(activeQuiz)" style="font-size:20px;">
                            <i class="bi bi-clock me-1" style="font-size:14px;"></i>
                            {{ getCountdownLabel(activeQuiz) }}
                        </span>
                    </div>

                    <div v-if="autoAdvance && queueCount > 0" class="mb-2 rounded px-2 py-1 d-flex align-items-center gap-2"
                         style="background:#1e3a1e;border:1px solid #2d5a2d;font-size:12px;">
                        <i class="bi bi-collection-play text-warning"></i>
                        <span class="text-warning">{{ queueCount }} سؤال تالٍ في الطابور</span>
                    </div>

                    <p class="text-white mb-3" style="font-size:14px;line-height:1.6;">
                        {{ activeQuiz.question_text }}
                    </p>

                    <div v-if="activeAnswers" class="mb-3">
                        <div class="d-flex justify-content-between small text-secondary mb-1">
                            <span>إجابات الطلاب</span>
                            <span class="text-white fw-semibold">{{ activeAnswers.total }}</span>
                        </div>

                        <!-- TF bar chart -->
                        <template v-if="activeAnswers.true_false_count && Object.keys(activeAnswers.true_false_count).length">
                            <div class="mb-1">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-success">✅ صح</span>
                                    <span class="text-white">{{ activeAnswers.true_false_count.true }}</span>
                                </div>
                                <div class="progress" style="height:6px;background:#2a2a4a;">
                                    <div class="progress-bar bg-success" :style="`width:${activeAnswers.total ? Math.round(activeAnswers.true_false_count.true / activeAnswers.total * 100) : 0}%`"></div>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-danger">❌ غلط</span>
                                    <span class="text-white">{{ activeAnswers.true_false_count.false }}</span>
                                </div>
                                <div class="progress" style="height:6px;background:#2a2a4a;">
                                    <div class="progress-bar bg-danger" :style="`width:${activeAnswers.total ? Math.round(activeAnswers.true_false_count.false / activeAnswers.total * 100) : 0}%`"></div>
                                </div>
                            </div>
                        </template>

                        <!-- MC bar chart -->
                        <template v-else-if="activeQuiz.question_type === 'multiple_choice' && activeAnswers.option_counts">
                            <div v-for="(opt, i) in activeQuiz.options" :key="i" class="mb-1">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-secondary">{{ opt }}</span>
                                    <span class="text-white">{{ activeAnswers.option_counts[i] ?? 0 }}</span>
                                </div>
                                <div class="progress" style="height:6px;background:#2a2a4a;">
                                    <div class="progress-bar bg-primary"
                                         :style="`width:${activeAnswers.total ? Math.round((activeAnswers.option_counts[i] ?? 0) / activeAnswers.total * 100) : 0}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Answers list -->
                    <div v-if="activeAnswers && activeAnswers.answers.length" class="mb-3">
                        <div class="small text-secondary mb-2">استجابات الطلاب</div>
                        <div class="d-flex flex-column gap-1" style="max-height:200px;overflow-y:auto;">
                            <div v-for="ans in activeAnswers.answers" :key="ans.id"
                                 class="d-flex align-items-start gap-2 py-1 px-2 rounded"
                                 style="background:#12122a;font-size:12px;">
                                <span class="fw-semibold text-white text-truncate" style="max-width:110px;">{{ ans.student_name }}</span>
                                <span class="flex-grow-1 text-secondary text-truncate">{{ getAnswerLabel(activeQuiz, ans.answer) }}</span>
                                <span v-if="ans.is_correct === true" class="text-success">✓</span>
                                <span v-else-if="ans.is_correct === false" class="text-danger">✗</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button @click="closeQuiz(activeQuiz)" class="btn btn-sm btn-outline-danger flex-grow-1">
                            <i class="bi bi-stop-circle me-1"></i>إغلاق مبكر
                        </button>
                        <button v-if="autoAdvance" @click="autoAdvance = false" class="btn btn-sm btn-outline-warning" title="إيقاف التشغيل التلقائي">
                            <i class="bi bi-pause-circle"></i>
                        </button>
                    </div>
                </div>
            </template>

            <!-- NEW QUESTION TAB -->
            <template v-if="activeTab === 'new'">

                <!-- Type selector -->
                <div class="mb-3">
                    <label class="form-label text-secondary small mb-2">نوع السؤال</label>
                    <div class="row g-2">
                        <div v-for="qt in questionTypes" :key="qt.value" class="col-6">
                            <div
                                @click="form.question_type = qt.value; form.attachment = null; form.attachmentName = ''"
                                class="rounded p-2 text-center"
                                :style="form.question_type === qt.value
                                    ? 'background:#0d6efd22;border:1px solid #0d6efd;cursor:pointer;'
                                    : 'background:#12122a;border:1px solid #2a2a4a;cursor:pointer;'"
                            >
                                <div style="font-size:13px;" :class="form.question_type === qt.value ? 'text-white fw-semibold' : 'text-secondary'">
                                    {{ qt.label }}
                                </div>
                                <div class="text-secondary" style="font-size:10px;">{{ qt.desc }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question text -->
                <div class="mb-3">
                    <label class="form-label text-secondary small">
                        {{ form.question_type === 'pdf_exam' ? 'تعليمات للطلاب' : 'نص السؤال' }}
                        <span class="text-danger">*</span>
                    </label>
                    <textarea
                        v-model="form.question_text"
                        rows="3"
                        class="form-control form-control-sm"
                        style="background:#0d0d1a;color:#fff;border-color:#2a2a4a;font-size:13px;"
                        :placeholder="form.question_type === 'pdf_exam' ? 'مثلاً: راجع الامتحان المرفق...' : 'اكتب السؤال هنا...'"
                    ></textarea>
                </div>

                <!-- PDF upload -->
                <div v-if="form.question_type === 'pdf_exam'" class="mb-3">
                    <label class="form-label text-secondary small">ملف PDF <span class="text-danger">*</span></label>
                    <div class="rounded p-3 text-center" style="background:#12122a;border:2px dashed #2a2a4a;">
                        <template v-if="form.attachmentName">
                            <i class="bi bi-file-earmark-pdf text-danger fs-4 d-block mb-1"></i>
                            <div class="text-white small">{{ form.attachmentName }}</div>
                            <button @click="form.attachment = null; form.attachmentName = ''" class="btn btn-sm btn-link text-danger p-0 mt-1">حذف</button>
                        </template>
                        <template v-else>
                            <i class="bi bi-upload text-secondary fs-4 d-block mb-1"></i>
                            <label class="btn btn-sm btn-outline-secondary" style="cursor:pointer;">
                                اختر ملف PDF
                                <input type="file" accept=".pdf" class="d-none" @change="onFileChange" />
                            </label>
                            <div class="text-secondary mt-1" style="font-size:11px;">الحد الأقصى 20MB</div>
                        </template>
                    </div>
                </div>

                <!-- True/False correct answer -->
                <div v-if="form.question_type === 'true_false' || form.question_type === 'true_false_correction'" class="mb-3">
                    <label class="form-label text-secondary small">الإجابة الصحيحة</label>
                    <div class="d-flex gap-2">
                        <button @click="form.correct_answer = 'true'" class="btn btn-sm flex-fill"
                            :class="form.correct_answer === 'true' ? 'btn-success' : 'btn-outline-secondary'">✅ صح</button>
                        <button @click="form.correct_answer = 'false'" class="btn btn-sm flex-fill"
                            :class="form.correct_answer === 'false' ? 'btn-danger' : 'btn-outline-secondary'">❌ غلط</button>
                    </div>
                </div>

                <!-- Multiple choice options -->
                <div v-if="form.question_type === 'multiple_choice'" class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-secondary small mb-0">الخيارات</label>
                        <div class="form-check form-switch mb-0">
                            <input v-model="form.allow_multiple" class="form-check-input" type="checkbox" role="switch" id="allowMulti" />
                            <label class="form-check-label text-secondary small" for="allowMulti">تعدد الإجابات</label>
                        </div>
                    </div>
                    <div v-for="(opt, idx) in form.options" :key="idx" class="d-flex gap-1 mb-2 align-items-center">
                        <div @click="form.correct_answer = String(idx)"
                            class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center"
                            :style="form.correct_answer === String(idx)
                                ? 'width:22px;height:22px;background:#198754;cursor:pointer;color:#fff;font-size:11px;'
                                : 'width:22px;height:22px;background:#2a2a4a;cursor:pointer;color:#aaa;font-size:11px;'"
                            title="اجعله الإجابة الصحيحة"
                        >✓</div>
                        <input v-model="form.options[idx]" type="text" class="form-control form-control-sm flex-grow-1"
                            style="background:#0d0d1a;color:#fff;border-color:#2a2a4a;font-size:12px;"
                            :placeholder="`الخيار ${idx + 1}`" />
                        <button @click="removeOption(idx)" class="btn btn-sm btn-outline-danger py-0 px-1" :disabled="form.options.length <= 2">
                            <i class="bi bi-x" style="font-size:14px;"></i>
                        </button>
                    </div>
                    <button @click="addOption" class="btn btn-sm btn-outline-secondary w-100" :disabled="form.options.length >= 10">
                        <i class="bi bi-plus me-1"></i>إضافة خيار
                    </button>
                </div>

                <!-- Time limit -->
                <div class="mb-3">
                    <label class="form-label text-secondary small">وقت الإجابة</label>
                    <div class="d-flex gap-1 mb-2 flex-wrap">
                        <button v-for="preset in timeLimitPresets" :key="preset.value"
                            @click="form.time_limit = preset.value"
                            class="btn btn-sm"
                            :class="form.time_limit === preset.value ? 'btn-primary' : 'btn-outline-secondary'"
                            style="font-size:11px;padding:2px 8px;">{{ preset.label }}</button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input v-model.number="form.time_limit" type="number" min="10" max="3600"
                            class="form-control form-control-sm"
                            style="background:#0d0d1a;color:#fff;border-color:#2a2a4a;width:80px;font-size:13px;" />
                        <span class="text-secondary small">ثانية</span>
                        <span class="text-primary small ms-auto">= {{ formatTime(form.time_limit) }}</span>
                    </div>
                </div>

                <!-- Submit buttons -->
                <div class="d-flex gap-2">
                    <button @click="launchNow" class="btn btn-sm btn-primary flex-fill" :disabled="submitting">
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-play-fill me-1"></i>إطلاق فوراً
                    </button>
                    <button @click="addToQueue" class="btn btn-sm btn-outline-warning flex-fill" :disabled="submitting">
                        <i class="bi bi-collection-play me-1"></i>أضف للطابور
                    </button>
                </div>
                <p class="text-secondary mt-2 mb-0" style="font-size:11px;">
                    <i class="bi bi-info-circle me-1"></i>«أضف للطابور» يحفظ السؤال وتطلق الكل من تبويب «الطابور»
                </p>
            </template>

            <!-- QUEUE TAB -->
            <template v-if="activeTab === 'queue'">

                <div v-if="!queueCount" class="text-center py-5 text-secondary">
                    <i class="bi bi-collection fs-2 d-block mb-2"></i>
                    الطابور فارغ — أضف أسئلة من تبويب «+ سؤال جديد»
                </div>

                <template v-else>
                    <!-- Global time setter -->
                    <div class="rounded p-3 mb-3" style="background:#12122a;border:1px solid #2a2a4a;">
                        <div class="small text-secondary mb-2 fw-semibold">
                            <i class="bi bi-clock-history me-1 text-warning"></i>وقت موحد لجميع الأسئلة
                        </div>
                        <div class="d-flex gap-1 flex-wrap mb-2">
                            <button v-for="preset in timeLimitPresets" :key="preset.value"
                                @click="globalTime = preset.value"
                                class="btn btn-sm"
                                :class="String(globalTime) === String(preset.value) ? 'btn-warning text-dark' : 'btn-outline-secondary'"
                                style="font-size:11px;padding:2px 8px;">{{ preset.label }}</button>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input v-model.number="globalTime" type="number" min="10" max="3600"
                                class="form-control form-control-sm"
                                style="background:#0d0d1a;color:#fff;border-color:#2a2a4a;width:70px;font-size:13px;"
                                placeholder="ث" />
                            <span class="text-secondary small">{{ globalTime ? formatTime(Number(globalTime)) : '' }}</span>
                            <button @click="applyGlobalTime" class="btn btn-sm btn-warning text-dark ms-auto" :disabled="settingTime || !globalTime">
                                <span v-if="settingTime" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2-all me-1"></i>تطبيق على الكل
                            </button>
                        </div>
                    </div>

                    <!-- Launch buttons row -->
                    <div class="d-flex gap-2 mb-2">
                        <button @click="launchQueue" class="btn btn-success flex-fill" :disabled="hasActive || activeExam">
                            <i class="bi bi-play-circle-fill me-1"></i>
                            إطلاق الطابور
                        </button>
                        <button @click="launchExam" class="btn btn-warning text-dark flex-fill" :disabled="hasActive || activeExam || launchingExam || !globalTime">
                            <span v-if="launchingExam" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-journal-check me-1"></i>
                            إطلاق كامتحان
                        </button>
                    </div>
                    <div class="text-secondary text-center mb-3" style="font-size:11px;" v-if="hasActive || activeExam">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <span v-if="activeExam">يوجد امتحان نشط بالفعل</span>
                        <span v-else>أغلق السؤال النشط أولاً أو انتظر انتهاء وقته</span>
                    </div>

                    <!-- Draft list -->
                    <div class="small text-secondary mb-2 fw-semibold">أسئلة الطابور ({{ queueCount }})</div>
                    <div v-for="(q, idx) in draftQuizzes" :key="q.id"
                         class="rounded p-2 mb-2 d-flex align-items-start gap-2"
                         style="background:#12122a;border:1px solid #2a2a4a;">
                        <div class="rounded-circle bg-primary bg-opacity-25 text-primary d-flex align-items-center justify-content-center flex-shrink-0 fw-bold"
                             style="width:24px;height:24px;font-size:12px;">{{ idx + 1 }}</div>
                        <div class="flex-grow-1" style="min-width:0;">
                            <span class="badge bg-secondary mb-1" style="font-size:10px;">{{ getTypeLabel(q.question_type) }}</span>
                            <p class="text-white mb-1 small" style="word-break:break-word;">{{ q.question_text }}</p>
                            <div class="d-flex align-items-center gap-1">
                                <i class="bi bi-clock text-secondary" style="font-size:11px;"></i>
                                <input
                                    :value="q.time_limit"
                                    @change="updateDraftTime(q, $event.target.value)"
                                    type="number" min="10" max="3600"
                                    class="form-control form-control-sm text-center"
                                    style="background:#0d0d1a;color:#fff;border-color:#2a2a4a;width:60px;font-size:11px;padding:1px 4px;"
                                />
                                <span class="text-secondary" style="font-size:11px;">= {{ formatTime(q.time_limit) }}</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-1 flex-shrink-0">
                            <button @click="activateQuiz(q)" class="btn btn-sm btn-success py-0 px-2"
                                    style="font-size:11px;" :disabled="hasActive" title="إطلاق هذا السؤال فقط">
                                <i class="bi bi-play-fill"></i>
                            </button>
                            <button @click="deleteQuiz(q)" class="btn btn-sm btn-outline-danger py-0 px-2"
                                    style="font-size:11px;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </template>

            <!-- HISTORY TAB -->
            <template v-if="activeTab === 'history'">
                <div v-if="loadingQuizzes" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-secondary"></div>
                </div>
                <template v-else>
                    <div v-if="!closedQuizzes.length" class="text-center py-5 text-secondary">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        لا توجد أسئلة مغلقة بعد
                    </div>
                    <div v-for="q in closedQuizzes" :key="q.id"
                         class="rounded p-2 mb-2"
                         style="background:#12122a;border:1px solid #2a2a4a;opacity:0.85;">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge bg-secondary" style="font-size:10px;">{{ getTypeLabel(q.question_type) }}</span>
                            <span class="badge bg-secondary bg-opacity-50 small">{{ q.answers_count }} إجابة</span>
                        </div>
                        <p class="text-secondary mb-0 small">{{ q.question_text }}</p>
                    </div>
                </template>
            </template>

        </div>
    </div>
</template>

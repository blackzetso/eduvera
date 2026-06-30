import { ref } from 'vue'
import { route } from 'ziggy-js'

function csrfHeaders() {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const meta = document.head.querySelector('meta[name="csrf-token"]')?.content
  if (meta) headers['X-CSRF-TOKEN'] = meta
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  if (match) headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1])
  return headers
}

const loading = ref(false)
const preview = ref(null)
const error = ref(null)

export function useDailyAbsenceCoverage() {

  async function fetchPreview(date) {
    loading.value = true
    error.value = null
    try {
      const params = date ? { date } : {}
      const { data } = await window.axios.get(route('admin.timetable.daily-coverage.preview'), {
        params,
        headers: csrfHeaders(),
      })
      preview.value = data
      return data
    } catch (e) {
      error.value = e.response?.data?.message || e.message || 'تعذر تحميل بيانات التغطية'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function saveCoverageDraft(date, lessons, wizardState = {}) {
    const assignments = lessons
      .filter((l) => !l.cancelled && l.resolution !== 'temporary_swap' && !l.adjustment?.id)
      .filter((l) => l.suggestion?.replacement_teacher_id)
      .map((l) => ({
        period_id: l.period_id,
        absent_teacher_id: l.absent_teacher_id,
        replacement_teacher_id: l.suggestion.replacement_teacher_id,
        match_score: l.suggestion.match_percent,
        match_reasons: l.suggestion.reasons,
      }))

    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.save-draft'),
      { date, assignments, wizard_state: wizardState },
      { headers: csrfHeaders() }
    )
    if (data.preview) {
      preview.value = data.preview
    }
    return data
  }

  function mapAssignmentsFromLessons(lessons) {
    return lessons
      .filter((l) => !l.cancelled && l.resolution !== 'temporary_swap' && !l.adjustment?.id)
      .filter((l) => l.suggestion?.replacement_teacher_id)
      .map((l) => ({
        period_id: l.period_id,
        absent_teacher_id: l.absent_teacher_id,
        replacement_teacher_id: l.suggestion.replacement_teacher_id,
        status: l.status === 'approved' ? 'approved' : 'pending',
      }))
  }

  async function fetchDistributionReport(date, lessons = null) {
    const assignments = lessons ? mapAssignmentsFromLessons(lessons) : null
    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.distribution-report'),
      { date, assignments: assignments ?? undefined },
      { headers: csrfHeaders() }
    )
    return data
  }

  async function notifySubstituteTeacher(date, lesson) {
    if (!lesson?.suggestion?.replacement_teacher_id) {
      return { success: false, message: 'لا يوجد معلم بديل' }
    }
    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.notify-substitute'),
      {
        date,
        period_id: lesson.period_id,
        replacement_teacher_id: lesson.suggestion.replacement_teacher_id,
        absent_teacher_id: lesson.absent_teacher_id,
      },
      { headers: csrfHeaders() }
    )
    return data
  }

  async function approveCoverage(date, lessons) {
    const assignments = lessons
      .filter((l) => !l.cancelled && l.resolution !== 'temporary_swap' && !l.adjustment?.id)
      .filter((l) => l.suggestion?.replacement_teacher_id)
      .map((l) => ({
        period_id: l.period_id,
        absent_teacher_id: l.absent_teacher_id,
        replacement_teacher_id: l.suggestion.replacement_teacher_id,
        match_score: l.suggestion.match_percent,
        match_reasons: l.suggestion.reasons,
      }))

    if (assignments.length === 0) {
      return { success: true, message: 'لا توجد تغطيات بديلة للاعتماد (تبديلات مؤقتة فقط)' }
    }

    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.approve'),
      { date, assignments },
      { headers: csrfHeaders() }
    )
    return data
  }

  async function markTeacherAbsent(payload) {
    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.mark-absent'),
      payload,
      { headers: csrfHeaders() }
    )
    preview.value = data.preview
    return data
  }

  async function fetchSwapCandidates(triggerPeriodId, date) {
    const { data } = await window.axios.get(route('admin.timetable.daily-coverage.swap-candidates'), {
      params: { trigger_period_id: triggerPeriodId, date },
      headers: csrfHeaders(),
    })
    return data
  }

  async function previewSwap(payload) {
    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.swap-preview'),
      payload,
      { headers: csrfHeaders() }
    )
    return data
  }

  async function applySwap(payload) {
    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.apply-swap'),
      payload,
      { headers: csrfHeaders() }
    )
    preview.value = data.preview
    return data
  }

  async function seedDemoData(date = null) {
    loading.value = true
    error.value = null
    try {
      const payload = date ? { date } : {}
      const { data } = await window.axios.post(route('admin.absence.demo-data'), payload, {
        headers: csrfHeaders(),
      })
      if (data.preview) {
        preview.value = data.preview
      }
      return data
    } catch (e) {
      const msg = e.response?.data?.message || e.message || 'تعذر إنشاء البيانات التجريبية'
      error.value = msg
      throw e
    } finally {
      loading.value = false
    }
  }

  async function cancelLesson(date, periodId, adjustmentId = null) {
    const { data } = await window.axios.post(
      route('admin.timetable.daily-coverage.cancel-lesson'),
      { date, period_id: periodId, adjustment_id: adjustmentId },
      { headers: csrfHeaders() }
    )
    preview.value = data.preview
    return data
  }

  return {
    loading,
    preview,
    error,
    fetchPreview,
    saveCoverageDraft,
    approveCoverage,
    fetchDistributionReport,
    notifySubstituteTeacher,
    markTeacherAbsent,
    fetchSwapCandidates,
    previewSwap,
    applySwap,
    cancelLesson,
    seedDemoData,
  }
}

export function isLessonResolved(lesson) {
  if (lesson.cancelled) return true
  if (lesson.resolution === 'temporary_swap' || lesson.adjustment?.id) return true
  if (lesson.status === 'approved' && lesson.suggestion?.replacement_teacher_id) return true
  return !!lesson.suggestion?.replacement_teacher_id
}

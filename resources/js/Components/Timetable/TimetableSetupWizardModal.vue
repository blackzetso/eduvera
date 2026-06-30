<script setup>
import { ref, watch, defineExpose, computed } from 'vue'
import { toast } from 'vue3-toastify'
import {
  useTimetableSetupWizard,
  WEEK_DAYS,
  EDUCATIONAL_STAGES,
  BREAK_PRESETS,
  SETUP_STEPS,
} from '@/composables/useTimetableSetupWizard'
import '../../../css/timetable-setup-wizard.css'

const props = defineProps({
  show: Boolean,
  teachers: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  teacherBySubject: { type: Object, default: () => ({}) },
  timetableSettings: { type: Object, default: null },
  processing: Boolean,
  hasApprovedDistribution: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'save-framework', 'generate'])

const setup = useTimetableSetupWizard()
const stepError = ref('')

const {
  currentStep,
  workingDays,
  selectedStages,
  customStageName,
  selectedStagesCount,
  stageLabelsSummary,
  hasCustomStage,
  hydrateStagesFromSettings,
  toggleStage,
  isStageSelected,
  dailyLessons,
  startTime,
  lessonDurationMin,
  gapBetweenLessonsMin,
  breaks,
  previewSlots,
  assignByRole,
  subjectRows,
  selectedDaysCount,
  toggleDay,
  addBreak,
  removeBreak,
  rebuildPreview,
  initSubjectRows,
  getSettingsPayload,
  teacherAvailability,
  setupSubStep,
  initTeacherAvailability,
  toggleTeacherDay,
  toggleBlockedPeriod,
  isPeriodBlocked,
  nextStep,
  prevStep,
  goToStep,
  open,
} = setup

const lessonPreviewSlots = computed(() =>
  previewSlots.value.filter((p) => p.kind === 'lesson' || Number(p.period_number) > 0)
)

const kindLabels = {
  lesson: 'حصة',
  break: 'استراحة',
  assembly: 'طابور',
  prayer: 'صلاة',
}

function hydrateFromSettings(settings) {
  if (!settings) return
  if (settings.working_days?.length) workingDays.value = settings.working_days
  hydrateStagesFromSettings(settings)
  if (settings.daily_lessons) dailyLessons.value = settings.daily_lessons
  if (settings.start_time) startTime.value = settings.start_time
  if (settings.lesson_duration_min) lessonDurationMin.value = settings.lesson_duration_min
  if (settings.gap_between_lessons_min != null) gapBetweenLessonsMin.value = settings.gap_between_lessons_min
  if (settings.breaks) breaks.value = settings.breaks
  if (settings.period_structure?.length) previewSlots.value = settings.period_structure
  if (settings.assign_by_role) assignByRole.value = settings.assign_by_role
  if (settings.subject_requirements?.length) subjectRows.value = settings.subject_requirements
}

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      open()
      hydrateFromSettings(props.timetableSettings)
      initSubjectRows(props.subjects, props.teacherBySubject)
      initTeacherAvailability(props.teachers, props.timetableSettings?.teacher_availability)
      setupSubStep.value = 'subjects'
      rebuildPreview()
      stepError.value = ''
    }
  }
)

defineExpose({ goToStep })

function handleNext() {
  stepError.value = setup.validateStep(currentStep.value) || ''
  if (stepError.value) return
  const err = setup.nextStep()
  if (err) {
    stepError.value = err
    return
  }
  stepError.value = ''
  if (currentStep.value === 7) {
    initSubjectRows(props.subjects, {})
    props.subjects.forEach((s) => {
      const row = subjectRows.value.find((r) => r.subject_id === s.id)
      if (row) return
      subjectRows.value.push({
        subject_id: s.id,
        name: s.name,
        periods_per_week: 2,
        teacher_id: null,
      })
    })
  }
}

async function handleSaveFramework() {
  stepError.value = ''
  emit('save-framework', {
    workingDays: workingDays.value,
    previewSlots: previewSlots.value,
    subjectRows: subjectRows.value,
    getSettingsPayload,
  })
}

const generating = ref(false)

function handleGenerate() {
  stepError.value = setup.validateStep(7) || ''
  if (stepError.value) return
  generating.value = true
  emit('generate', {
    subjectRows: subjectRows.value,
    settings: getSettingsPayload(),
    teacherAvailability: teacherAvailability.value,
  })
}

function updatePreviewRow(index, field, value) {
  const row = previewSlots.value[index]
  if (row) row[field] = value
}
</script>

<template>
  <div v-if="show" class="modal tt-setup-modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, 0.55)">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content" dir="rtl">
        <div class="tt-setup__header d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-1 fw-bold">معالج إعداد الجدول — توليد تلقائي</h5>
            <p class="mb-0 small opacity-75">أكمل الخطوات قبل تعيين المواد وتوليد الجدول النهائي</p>
          </div>
          <button type="button" class="btn-close btn-close-white" @click="emit('close')"></button>
        </div>

        <div class="tt-setup__stepper">
          <button
            v-for="step in SETUP_STEPS"
            :key="step.n"
            type="button"
            class="tt-setup__step-pill"
            :class="{ 'is-active': currentStep === step.n, 'is-done': currentStep > step.n }"
            @click="goToStep(step.n)"
          >
            {{ step.n }}. {{ step.title }}
          </button>
        </div>

        <div class="tt-setup__body">
          <div v-if="stepError" class="alert alert-danger py-2 small">{{ stepError }}</div>

          <!-- Step 1 -->
          <div v-show="currentStep === 1">
            <h6 class="fw-bold mb-2">أيام العمل</h6>
            <p class="text-muted small mb-3">حدد أيام الدوام الرسمية للمدرسة.</p>
            <div class="tt-setup__day-grid">
              <label v-for="day in WEEK_DAYS" :key="day.key" class="tt-setup__day-check mb-0">
                <input
                  type="checkbox"
                  class="form-check-input"
                  :checked="workingDays.includes(day.key)"
                  @change="toggleDay(day.key)"
                />
                <span>{{ day.label }}</span>
              </label>
            </div>
            <div class="mt-3 tt-setup__card">
              <strong>إجمالي أيام العمل المحددة:</strong> {{ selectedDaysCount }} يوم
            </div>
          </div>

          <!-- Step 2 -->
          <div v-show="currentStep === 2">
            <h6 class="fw-bold mb-2">المراحل الدراسية</h6>
            <p class="text-muted small mb-3">يمكنك اختيار أكثر من مرحلة — تُحفظ في إعدادات الجدول كمصفوفة.</p>
            <div class="tt-setup__stage-grid">
              <label
                v-for="stage in EDUCATIONAL_STAGES"
                :key="stage.id"
                class="tt-setup__stage-card mb-0"
              >
                <input
                  type="checkbox"
                  class="form-check-input"
                  :checked="isStageSelected(stage.id)"
                  @change="toggleStage(stage.id)"
                />
                <span>{{ stage.label }}</span>
              </label>
            </div>
            <div v-if="hasCustomStage" class="mt-3">
              <label class="form-label">اسم المرحلة المخصصة</label>
              <input v-model="customStageName" class="form-control" placeholder="مثال: تحفيظ / لغات" />
            </div>
            <div class="mt-3 tt-setup__card">
              <strong>تم اختيار {{ selectedStagesCount }} مراحل دراسية</strong>
              <div v-if="stageLabelsSummary" class="small text-muted mt-1">{{ stageLabelsSummary }}</div>
            </div>
          </div>

          <!-- Step 3 -->
          <div v-show="currentStep === 3">
            <h6 class="fw-bold mb-3">هيكل الحصص</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">عدد الحصص اليومية</label>
                <input v-model.number="dailyLessons" type="number" min="1" max="16" class="form-control" />
              </div>
              <div class="col-md-4">
                <label class="form-label">موعد بداية أول حصة</label>
                <input v-model="startTime" type="time" class="form-control" />
              </div>
              <div class="col-md-4">
                <label class="form-label">مدة الحصة (دقيقة)</label>
                <input v-model.number="lessonDurationMin" type="number" min="20" max="120" class="form-control" />
              </div>
              <div class="col-md-4">
                <label class="form-label">فاصل بين الحصص (دقيقة)</label>
                <input v-model.number="gapBetweenLessonsMin" type="number" min="0" max="30" class="form-control" />
              </div>
            </div>
            <button type="button" class="btn btn-link btn-sm mt-2 p-0" @click="rebuildPreview">
              إعادة حساب الأوقات
            </button>
          </div>

          <!-- Step 4 -->
          <div v-show="currentStep === 4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">الفسح والاستراحات</h6>
              <button type="button" class="btn btn-primary-soft btn-sm" @click="addBreak">
                <i class="bi bi-plus-lg ms-1"></i> إضافة فسحة
              </button>
            </div>
            <p class="text-muted small">يُعاد حساب جميع الحصص التالية تلقائياً بعد كل فسحة.</p>
            <div v-if="!breaks.length" class="text-muted small">لا توجد فسح — يمكنك الإضافة لاحقاً.</div>
            <div v-for="br in breaks" :key="br.id" class="tt-setup__card mb-2">
              <div class="row g-2 align-items-end">
                <div class="col-md-4">
                  <label class="form-label small">الاسم</label>
                  <select v-model="br.name" class="form-select form-select-sm">
                    <option v-for="p in BREAK_PRESETS" :key="p" :value="p">{{ p }}</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small">بعد الحصة رقم</label>
                  <input v-model.number="br.afterPeriod" type="number" min="1" :max="dailyLessons" class="form-control form-control-sm" />
                </div>
                <div class="col-md-3">
                  <label class="form-label small">المدة (دقيقة)</label>
                  <input v-model.number="br.durationMin" type="number" min="5" max="60" class="form-control form-control-sm" />
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-danger-soft btn-sm w-100" @click="removeBreak(br.id)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 5 -->
          <div v-show="currentStep === 5">
            <h6 class="fw-bold mb-2">مراجعة الجدول</h6>
            <ul class="tt-setup__summary-list tt-setup__card small mb-3">
              <li><strong>المراحل:</strong> {{ stageLabelsSummary || '—' }} ({{ selectedStagesCount }})</li>
              <li><strong>أيام العمل:</strong> {{ workingDays.join('، ') }}</li>
            </ul>
            <p class="text-muted small mb-3">يمكنك تعديل الأوقات والعناوين قبل الحفظ.</p>
            <div class="table-responsive">
              <table class="table table-bordered tt-setup__preview-table">
                <thead class="table-light">
                  <tr>
                    <th>الوصف</th>
                    <th>النوع</th>
                    <th>من</th>
                    <th>إلى</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in previewSlots" :key="row.id">
                    <td>
                      <input
                        :value="row.label"
                        class="form-control form-control-sm"
                        @input="updatePreviewRow(idx, 'label', $event.target.value)"
                      />
                    </td>
                    <td>
                      <span class="badge bg-light border text-dark">{{ kindLabels[row.kind] ?? row.kind }}</span>
                    </td>
                    <td>
                      <input
                        :value="row.time_from"
                        type="time"
                        class="form-control form-control-sm"
                        @input="updatePreviewRow(idx, 'time_from', $event.target.value)"
                      />
                    </td>
                    <td>
                      <input
                        :value="row.time_to"
                        type="time"
                        class="form-control form-control-sm"
                        @input="updatePreviewRow(idx, 'time_to', $event.target.value)"
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Step 6 -->
          <div v-show="currentStep === 6">
            <h6 class="fw-bold mb-2">حفظ هيكل الجدول</h6>
            <p class="text-muted small mb-3">
              سيتم حفظ أيام العمل وهيكل الحصص والفسح <strong>بدون تعيين مواد أو معلمين</strong>.
            </p>
            <ul class="tt-setup__summary-list tt-setup__card">
              <li><strong>أيام العمل:</strong> {{ workingDays.join('، ') }}</li>
              <li><strong>المراحل الدراسية:</strong> {{ stageLabelsSummary }} ({{ selectedStagesCount }})</li>
              <li><strong>حصص يومية:</strong> {{ dailyLessons }}</li>
              <li><strong>فترات في الهيكل:</strong> {{ previewSlots.length }}</li>
              <li><strong>بداية اليوم:</strong> {{ startTime }}</li>
            </ul>
            <button
              type="button"
              class="btn btn-primary w-100 mt-3"
              :disabled="processing"
              @click="handleSaveFramework"
            >
              <i class="bi bi-save ms-1"></i>
              حفظ هيكل الجدول
            </button>
          </div>

          <!-- Step 7 -->
          <div v-show="currentStep === 7">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <h6 class="fw-bold mb-0">تعيين المواد والمعلمين</h6>
              <span v-if="hasApprovedDistribution" class="badge tt-wizard__badge-distribution">
                <i class="bi bi-check2-circle ms-1"></i> تم اعتماد توزيع الحصص
              </span>
            </div>
            <ul class="nav nav-pills nav-fill mb-3">
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: setupSubStep === 'subjects' }"
                  @click="setupSubStep = 'subjects'"
                >
                  المتطلبات والمعلمين
                </button>
              </li>
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: setupSubStep === 'availability' }"
                  @click="setupSubStep = 'availability'"
                >
                  توافر المعلمين
                </button>
              </li>
            </ul>

            <div v-show="setupSubStep === 'subjects'">
              <div class="mb-3">
                <label class="form-label small text-muted">التعيين بواسطة</label>
                <div class="d-flex flex-wrap gap-2">
                  <label class="btn btn-sm" :class="assignByRole === 'admin' ? 'btn-primary' : 'btn-outline-secondary'">
                    <input v-model="assignByRole" type="radio" class="d-none" value="admin" />
                    مدير النظام
                  </label>
                  <label class="btn btn-sm" :class="assignByRole === 'teacher' ? 'btn-primary' : 'btn-outline-secondary'">
                    <input v-model="assignByRole" type="radio" class="d-none" value="teacher" />
                    المعلم المسؤول
                  </label>
                  <label class="btn btn-sm" :class="assignByRole === 'hod' ? 'btn-primary' : 'btn-outline-secondary'">
                    <input v-model="assignByRole" type="radio" class="d-none" value="hod" />
                    رئيس القسم
                  </label>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover">
                  <thead>
                    <tr>
                      <th>المادة</th>
                      <th>حصص/أسبوع</th>
                      <th>المعلم</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in subjectRows" :key="row.subject_id">
                      <td>{{ row.name }}</td>
                      <td style="width: 100px">
                        <input v-model.number="row.periods_per_week" type="number" min="0" max="20" class="form-control form-control-sm" />
                      </td>
                      <td>
                        <select v-model="row.teacher_id" class="form-select form-select-sm">
                          <option :value="null">— اختر —</option>
                          <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-show="setupSubStep === 'availability'">
              <p class="text-muted small">حدد أيام عمل كل معلم والحصص غير المتاحة — يُستخدمها محرك التوليد.</p>
              <div v-for="t in teachers" :key="t.id" class="tt-setup__card mb-2">
                <div class="fw-semibold mb-2">{{ t.name }}</div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                  <label
                    v-for="day in workingDays"
                    :key="day"
                    class="btn btn-sm"
                    :class="teacherAvailability[t.id]?.days?.includes(day) ? 'btn-primary-soft' : 'btn-outline-secondary'"
                  >
                    <input
                      type="checkbox"
                      class="d-none"
                      :checked="teacherAvailability[t.id]?.days?.includes(day)"
                      @change="toggleTeacherDay(t.id, day)"
                    />
                    {{ day }}
                  </label>
                </div>
                <div v-for="day in workingDays" :key="`${t.id}-${day}`" class="small mt-2">
                  <div class="fw-semibold text-muted">{{ day }}</div>
                  <div class="d-flex flex-wrap gap-1 mt-1">
                    <button
                      v-for="slot in lessonPreviewSlots"
                      :key="`${t.id}-${day}-${slot.period_number}`"
                      type="button"
                      class="btn btn-sm py-0 px-2"
                      :class="
                        isPeriodBlocked(t.id, day, slot.period_number)
                          ? 'btn-danger-soft'
                          : 'btn-light border'
                      "
                      :title="`${slot.time_from} – ${slot.time_to}`"
                      @click="toggleBlockedPeriod(t.id, day, slot.period_number, slot.time_from, slot.time_to)"
                    >
                      {{ slot.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 8 -->
          <div v-show="currentStep === 8" class="text-center py-3">
            <i class="bi bi-cpu display-4 text-primary mb-3 d-block"></i>
            <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-2">
              <h6 class="fw-bold mb-0">محرك التوليد الذكي</h6>
              <span v-if="hasApprovedDistribution" class="badge tt-wizard__badge-distribution">
                <i class="bi bi-check2-circle ms-1"></i> تم اعتماد توزيع الحصص
              </span>
            </div>
            <p class="text-muted small col-lg-10 mx-auto mb-3">
              يتحقق من المتطلبات، توافر المعلمين، السعة، يوزّع المواد، يعيّن المعلمين، ويكشف التعارضات.
            </p>
            <ol class="text-start small text-muted col-lg-9 mx-auto mb-4">
              <li>التحقق من المتطلبات والسعة</li>
              <li v-if="hasApprovedDistribution">تطبيق توزيع الحصص المعتمد على المعلمين</li>
              <li>توزيع المواد على الأسبوع (بدون تكديس)</li>
              <li>تعيين المعلمين مع قيود التوافر</li>
              <li>تحسين التوزيع وجودة الجدول</li>
            </ol>
            <ul class="tt-setup__summary-list tt-setup__card text-start mb-4">
              <li><strong>المراحل:</strong> {{ stageLabelsSummary }}</li>
              <li>{{ selectedDaysCount }} أيام عمل × {{ lessonPreviewSlots.length }} حصص/يوم</li>
              <li>
                {{
                  subjectRows.reduce((s, r) => s + (Number(r.periods_per_week) || 0), 0)
                }}
                حصة أسبوعية مطلوبة
              </li>
            </ul>
            <button
              type="button"
              class="btn btn-primary btn-lg px-5"
              :disabled="processing || generating"
              @click="handleGenerate"
            >
              <span v-if="processing || generating" class="spinner-border spinner-border-sm ms-2"></span>
              <i v-else class="bi bi-magic ms-2"></i>
              إنشاء الجدول
            </button>
          </div>
        </div>

        <div class="tt-setup__footer d-flex justify-content-between align-items-center">
          <button
            type="button"
            class="btn btn-secondary-soft"
            :disabled="currentStep === 1 || processing"
            @click="prevStep(); stepError = ''"
          >
            السابق
          </button>
          <span class="small text-muted">الخطوة {{ currentStep }} من 8</span>
          <button
            v-if="currentStep < 6"
            type="button"
            class="btn btn-primary"
            :disabled="processing"
            @click="handleNext"
          >
            التالي
          </button>
          <button
            v-else-if="currentStep === 7"
            type="button"
            class="btn btn-primary"
            :disabled="processing"
            @click="handleNext"
          >
            التالي
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

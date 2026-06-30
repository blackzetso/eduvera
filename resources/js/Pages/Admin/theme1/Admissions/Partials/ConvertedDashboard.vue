<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  application: { type: Object, required: true },
  overview: { type: Object, required: true },
  timeline: { type: Array, default: () => [] },
  formatDateTime: { type: Function, required: true },
})

const convertedStudent = computed(() =>
  props.overview?.decision?.converted_student || props.application.converted_student
)

const convertedGuardian = computed(() =>
  props.overview?.decision?.converted_guardian || props.application.converted_guardian
)

const hasStudentEvent = computed(() =>
  props.timeline.some(e => e.type === 'student_created' || e.type === 'conversion')
)

const hasEnrollmentEvent = computed(() =>
  props.timeline.some(e => e.type === 'enrollment_created')
)

const hasGuardianEvent = computed(() =>
  props.timeline.some(e => e.type === 'guardian_match')
)

const targetCategory = computed(() =>
  props.application.target_grade
  || props.application.target_category?.name
  || '—'
)
</script>

<template>
  <div class="admission-adaptive-dashboard admission-adaptive-dashboard--converted mb-4">
    <div class="card admission-adaptive-card border-0 shadow-sm border-success border-opacity-25">
      <div class="card-body p-4 text-center">
        <div class="display-6 text-success mb-2">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <h5 class="text-success fw-bold mb-2">تم التحويل بنجاح</h5>
        <p class="text-muted small mb-4">تم إنشاء ملف الطالب والقيد وربط ولي الأمر</p>

        <div class="row g-3 mb-4 text-start">
          <div class="col-md-4">
            <div class="admission-adaptive-card p-3 h-100">
              <div class="d-flex align-items-center gap-2">
                <i :class="['bi', hasStudentEvent ? 'bi-check-circle-fill text-success' : 'bi-check-lg text-success']"></i>
                <span class="fw-semibold small">تم إنشاء الطالب</span>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="admission-adaptive-card p-3 h-100">
              <div class="d-flex align-items-center gap-2">
                <i :class="['bi', hasEnrollmentEvent ? 'bi-check-circle-fill text-success' : 'bi-journal-check text-success']"></i>
                <span class="fw-semibold small">تم إنشاء القيد</span>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="admission-adaptive-card p-3 h-100">
              <div class="d-flex align-items-center gap-2">
                <i :class="['bi', hasGuardianEvent ? 'bi-check-circle-fill text-success' : 'bi-people text-success']"></i>
                <span class="fw-semibold small">ربط ولي الأمر</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="convertedStudent" class="row g-3 mb-4 text-start small">
          <div class="col-sm-4">
            <div class="text-muted">كود الطالب</div>
            <div class="fw-bold">{{ convertedStudent.student_code || '—' }}</div>
          </div>
          <div class="col-sm-4">
            <div class="text-muted">الفئة الحالية</div>
            <div class="fw-bold">{{ targetCategory }}</div>
          </div>
          <div class="col-sm-4">
            <div class="text-muted">حالة التحويل</div>
            <div class="fw-bold text-success">{{ overview.decision?.conversion_status_label || 'مكتمل' }}</div>
          </div>
          <div v-if="overview.decision?.converted_at" class="col-12 text-muted">
            {{ formatDateTime(overview.decision.converted_at) }}
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-2">
          <Link
            v-if="convertedStudent?.profile_url"
            :href="convertedStudent.profile_url"
            class="btn btn-success btn-lg px-4"
          >
            <i class="bi bi-person-badge me-2"></i>
            فتح ملف الطالب
          </Link>
          <Link
            v-if="convertedGuardian?.profile_url"
            :href="convertedGuardian.profile_url"
            class="btn btn-outline-success btn-lg px-4"
          >
            <i class="bi bi-people me-2"></i>
            فتح ملف العائلة
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

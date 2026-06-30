<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  duplicateAnalysis: { type: Object, default: () => ({}) },
  riskLevel: { type: Object, default: () => ({}) },
})

const students = computed(() => props.duplicateAnalysis.possible_existing_students || [])
const guardians = computed(() => props.duplicateAnalysis.possible_existing_guardians || [])
const families = computed(() => props.duplicateAnalysis.possible_existing_families || [])
const applications = computed(() => props.duplicateAnalysis.possible_duplicate_applications || [])

const hasMatches = computed(() =>
  students.value.length + guardians.value.length + families.value.length + applications.value.length > 0,
)

const severityClass = computed(() =>
  props.riskLevel.level === 'high' ? 'admissions-hero-strip--danger' : 'admissions-hero-strip--warning',
)
</script>

<template>
  <div v-if="hasMatches" class="admissions-hero-strip" :class="severityClass">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <i class="bi bi-shield-exclamation fs-4 text-danger"></i>
      <h6 class="mb-0 fw-bold">تطابقات محتملة — {{ riskLevel.label }}</h6>
    </div>

    <div class="row g-3">
      <div v-if="students.length" class="col-md-6">
        <div class="small fw-semibold text-danger mb-2">طلاب مسجلون</div>
        <div v-for="match in students" :key="`s-${match.id}`" class="admissions-match-row d-flex flex-wrap align-items-center gap-2 mb-2">
          <span class="fw-semibold flex-grow-1">{{ match.name }}</span>
          <span class="badge bg-danger">{{ match.confidence }}%</span>
          <Link v-if="match.profile_url" :href="match.profile_url" class="btn btn-sm btn-outline-danger">فتح الملف</Link>
        </div>
      </div>

      <div v-if="guardians.length" class="col-md-6">
        <div class="small fw-semibold text-warning mb-2">أولياء أمور</div>
        <div v-for="match in guardians" :key="`g-${match.id}`" class="admissions-match-row d-flex flex-wrap align-items-center gap-2 mb-2">
          <span class="fw-semibold flex-grow-1">{{ match.name }}</span>
          <span class="badge bg-warning text-dark">{{ match.confidence }}%</span>
          <Link v-if="match.profile_url" :href="match.profile_url" class="btn btn-sm btn-outline-warning">فتح الملف</Link>
        </div>
      </div>

      <div v-if="families.length" class="col-md-6">
        <div class="small fw-semibold text-warning mb-2">عائلات</div>
        <div v-for="(family, idx) in families" :key="`f-${family.guardian_id}-${idx}`" class="admissions-match-row d-flex flex-wrap align-items-center gap-2 mb-2">
          <span class="fw-semibold flex-grow-1">{{ family.guardian_name }}</span>
          <span class="badge bg-secondary">{{ family.children?.length || 0 }} أبناء</span>
          <Link v-if="family.guardian_profile_url" :href="family.guardian_profile_url" class="btn btn-sm btn-outline-secondary">فتح العائلة</Link>
        </div>
      </div>

      <div v-if="applications.length" class="col-md-6">
        <div class="small fw-semibold text-danger mb-2">طلبات قبول مكررة</div>
        <div v-for="match in applications" :key="`a-${match.application_id}`" class="admissions-match-row d-flex flex-wrap align-items-center gap-2 mb-2">
          <span class="fw-semibold flex-grow-1">{{ match.reference_code }}</span>
          <span class="small text-muted">{{ match.student_name }}</span>
          <Link v-if="match.profile_url" :href="match.profile_url" class="btn btn-sm btn-outline-danger">فتح الطلب</Link>
        </div>
      </div>
    </div>
  </div>
</template>

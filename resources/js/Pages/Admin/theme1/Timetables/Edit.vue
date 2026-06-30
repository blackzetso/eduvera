<script setup>
import TimetableWizard from '@/Components/Timetable/TimetableWizard.vue'

defineProps({
  timetable: Object,
  categories: Array,
  teachers: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  initialStep: { type: Number, default: 1 },
  dailyCoverageSummary: { type: Object, default: () => ({ absent_count: 0 }) },
  teacherAttendanceStatuses: { type: Object, default: () => ({}) },
  assignmentContext: { type: Object, default: null },
  wizardMode: { type: String, default: 'full' },
  departmentNeedsSummary: { type: Array, default: () => [] },
})
</script>

<template>
  <TimetableWizard
    :timetable="timetable"
    :categories="categories"
    :teachers="teachers"
    :subjects="subjects"
    :initial-step="initialStep"
    :daily-coverage-summary="dailyCoverageSummary"
    :teacher-attendance-statuses="teacherAttendanceStatuses"
    :assignment-context="assignmentContext"
    :wizard-mode="wizardMode"
    :department-needs-summary="departmentNeedsSummary"
  />
</template>

<script setup>
import TimetableWizard from '@/Components/Timetable/TimetableWizard.vue'

defineProps({
  timetable: Object,
  categories: Array,
  teachers: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  assignmentContext: { type: Object, required: true },
  wizardMode: { type: String, default: 'teacher_assign' },
  initialStep: { type: Number, default: 6 },
})
</script>

<template>
  <TimetableWizard
    :timetable="timetable"
    :categories="categories"
    :teachers="teachers"
    :subjects="subjects"
    :assignment-context="assignmentContext"
    :wizard-mode="wizardMode"
    :initial-step="initialStep"
  />
</template>

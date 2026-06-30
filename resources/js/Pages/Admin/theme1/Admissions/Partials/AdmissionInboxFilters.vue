<script setup>
import { ref } from 'vue'

const props = defineProps({
  searchQuery: { type: String, required: true },
  selectedStage: { type: String, required: true },
  selectedStatus: { type: String, required: true },
  selectedAcademicYear: { type: String, required: true },
  selectedOfficer: { type: String, required: true },
  filterOptions: { type: Object, default: () => ({}) },
})

const emit = defineEmits([
  'update:searchQuery',
  'update:selectedStage',
  'update:selectedStatus',
  'update:selectedAcademicYear',
  'update:selectedOfficer',
])

const filtersOpen = ref(false)
</script>

<template>
  <div class="card admission-dashboard-card mb-4">
    <div class="card-body p-3">
      <div class="d-flex d-md-none justify-content-between align-items-center mb-0">
        <span class="fw-semibold small">تصفية الطلبات</span>
        <button
          type="button"
          class="btn btn-sm btn-outline-secondary"
          @click="filtersOpen = !filtersOpen"
        >
          <i :class="['bi', filtersOpen ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
        </button>
      </div>

      <div
        class="admission-filters-collapse row g-2"
        :class="[filtersOpen ? 'd-flex' : 'd-none d-md-flex', filtersOpen ? 'mt-3' : 'mt-md-0']"
      >
        <div class="col-12 col-md-3">
          <input
            :value="searchQuery"
            type="search"
            class="form-control"
            placeholder="بحث: مرجع، طالب، ولي أمر..."
            @input="emit('update:searchQuery', $event.target.value)"
          />
        </div>
        <div class="col-6 col-md-2">
          <select
            class="form-select"
            :value="selectedStage"
            @change="emit('update:selectedStage', $event.target.value)"
          >
            <option value="">كل المراحل</option>
            <option v-for="opt in filterOptions.stages" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select
            class="form-select"
            :value="selectedStatus"
            @change="emit('update:selectedStatus', $event.target.value)"
          >
            <option value="">كل الحالات</option>
            <option v-for="opt in filterOptions.statuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select
            class="form-select"
            :value="selectedAcademicYear"
            @change="emit('update:selectedAcademicYear', $event.target.value)"
          >
            <option value="">كل السنوات</option>
            <option v-for="year in filterOptions.academic_years" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select
            class="form-select"
            :value="selectedOfficer"
            @change="emit('update:selectedOfficer', $event.target.value)"
          >
            <option value="">كل المسؤولين</option>
            <option v-for="opt in filterOptions.officers" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</template>

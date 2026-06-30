<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import Sidebar from '@/Pages/Admin/theme1/Settings/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
  priorityConfig: { type: Object, required: true },
})

const form = ref({
  balance_penalty_per_point: props.priorityConfig.balance_penalty_per_point ?? 4,
  week_penalty_per_coverage: props.priorityConfig.week_penalty_per_coverage ?? 5,
  rules: JSON.parse(JSON.stringify(props.priorityConfig.rules ?? {})),
})

const ruleRows = computed(() =>
  Object.entries(form.value.rules).map(([key, rule]) => ({
    key,
    ...rule,
  }))
)

const saving = ref(false)

function submit() {
  saving.value = true
  router.post(route('admin.settings.coverage.update'), form.value, {
    onSuccess: () => toast.success('تم حفظ أولويات التغطية'),
    onError: (errors) => toast.error(Object.values(errors)[0] || 'تعذر الحفظ'),
    onFinish: () => {
      saving.value = false
    },
  })
}
</script>

<template>
  <Head title="أولويات تغطية الغياب" />
  <AppLayout>
    <div class="page-content-wrapper border" dir="rtl">
      <div class="row">
        <div class="col-12 mb-3">
          <h1 class="h3 mb-1">إعدادات تغطية الغياب</h1>
          <p class="text-muted small mb-0">
            تحدد كيف يُرتّب النظام المعلمين البدلاء ورصيد التغطية (+1، +7…) عند فتح مركز التغطية اليومية.
          </p>
        </div>
      </div>

      <div class="row g-4">
        <Sidebar />

        <div class="col-xl-9">
          <div class="card shadow">
            <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
              <h5 class="card-header-title mb-0">
                <i class="bi bi-person-x-fill text-warning ms-1"></i>
                أولويات اقتراح البديل
              </h5>
              <Link :href="route('admin.timetable.edit', { step: 6 })" class="btn btn-sm btn-outline-primary">
                فتح الجدول — مركز التغطية
              </Link>
            </div>

            <div class="card-body">
              <div class="alert alert-info small">
                كل معيار مفعّل يضيف <strong>نقاط وزن</strong> لدرجة المعلم. الأعلى درجة يظهر أولاً في «المتفرغون في الحصة».
                رصيد التغطية يخصم نقاطاً حسب «عقوبة الرصيد» أدناه.
              </div>

              <form @submit.prevent="submit">
                <div class="row g-3 mb-4">
                  <div class="col-md-6">
                    <label class="form-label">عقوبة كل نقطة رصيد (+7 يخصم أكثر من +1)</label>
                    <input
                      v-model.number="form.balance_penalty_per_point"
                      type="number"
                      min="0"
                      max="30"
                      class="form-control"
                    />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">عقوبة كل تغطية إضافية هذا الأسبوع</label>
                    <input
                      v-model.number="form.week_penalty_per_coverage"
                      type="number"
                      min="0"
                      max="30"
                      class="form-control"
                    />
                  </div>
                </div>

                <h6 class="fw-bold mb-3">معايير الأولوية</h6>
                <div class="table-responsive">
                  <table class="table table-bordered align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>مفعّل</th>
                        <th>المعيار</th>
                        <th style="width: 120px">الوزن (نقاط)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in ruleRows" :key="row.key">
                        <td class="text-center">
                          <input v-model="form.rules[row.key].enabled" type="checkbox" class="form-check-input" />
                        </td>
                        <td>{{ form.rules[row.key].label }}</td>
                        <td>
                          <input
                            v-model.number="form.rules[row.key].weight"
                            type="number"
                            min="0"
                            max="300"
                            class="form-control form-control-sm"
                            :disabled="!form.rules[row.key].enabled"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <button type="submit" class="btn btn-primary" :disabled="saving">
                  <span v-if="saving" class="spinner-border spinner-border-sm ms-2"></span>
                  حفظ الأولويات
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  summary: Object,
  logs: Object,
  filters: Object,
  filterOptions: Object,
})

const localFilters = ref({
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  actor_user_id: props.filters?.actor_user_id ?? '',
  action: props.filters?.action ?? '',
  subject_type: props.filters?.subject_type ?? '',
  search: props.filters?.search ?? '',
})

watch(() => props.filters, (f) => {
  localFilters.value = {
    from: f?.from ?? '',
    to: f?.to ?? '',
    actor_user_id: f?.actor_user_id ?? '',
    action: f?.action ?? '',
    subject_type: f?.subject_type ?? '',
    search: f?.search ?? '',
  }
}, { deep: true })

function applyFilters() {
  router.get(route('canteen.audit.index'), { ...localFilters.value }, { preserveState: true })
}

function clearFilters() {
  localFilters.value = {
    from: '', to: '', actor_user_id: '', action: '', subject_type: '', search: '',
  }
  applyFilters()
}
</script>

<template>
  <CanteenLayout>
    <Head title="Audit Log" />

    <h4 class="mb-4">سجل التدقيق</h4>

    <div class="row g-3 mb-4">
      <div class="col-md-4 col-lg">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted mb-1">إجمالي السجلات</h6>
            <h3 class="mb-0">{{ summary?.total_records ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted mb-1">نشاط اليوم</h6>
            <h3 class="mb-0">{{ summary?.today_activities ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted mb-1">عمليات المخزون</h6>
            <h3 class="mb-0">{{ summary?.inventory_actions ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted mb-1">عمليات المبيعات</h6>
            <h3 class="mb-0">{{ summary?.sales_actions ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted mb-1">تغييرات الإعدادات</h6>
            <h3 class="mb-0">{{ summary?.settings_changes ?? 0 }}</h3>
          </div>
        </div>
      </div>
    </div>

    <div class="card border mb-4">
      <div class="card-header bg-transparent"><strong>التصفية</strong></div>
      <div class="card-body row g-2">
        <div class="col-md-3">
          <label class="form-label small">من</label>
          <input v-model="localFilters.from" type="date" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
          <label class="form-label small">إلى</label>
          <input v-model="localFilters.to" type="date" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
          <label class="form-label small">المستخدم</label>
          <select v-model="localFilters.actor_user_id" class="form-select form-select-sm">
            <option value="">الكل</option>
            <option v-for="u in filterOptions?.users ?? []" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">نوع الإجراء</label>
          <select v-model="localFilters.action" class="form-select form-select-sm">
            <option value="">الكل</option>
            <option v-for="a in filterOptions?.actions ?? []" :key="a.value" :value="a.value">{{ a.label }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">نوع الكيان</label>
          <select v-model="localFilters.subject_type" class="form-select form-select-sm">
            <option value="">الكل</option>
            <option v-for="e in filterOptions?.entity_types ?? []" :key="e.value" :value="e.value">{{ e.label }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small">بحث</label>
          <input v-model="localFilters.search" class="form-control form-control-sm" placeholder="بحث في الإجراء أو الوصف...">
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
          <button type="button" class="btn btn-primary btn-sm" @click="applyFilters">تطبيق</button>
          <button type="button" class="btn btn-light btn-sm" @click="clearFilters">مسح</button>
        </div>
      </div>
    </div>

    <div class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>التاريخ والوقت</th>
              <th>المستخدم</th>
              <th>الإجراء</th>
              <th>نوع الكيان</th>
              <th>المرجع</th>
              <th>الوصف</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!(logs?.data?.length)">
              <td colspan="7" class="text-center text-muted py-4">لا توجد سجلات تدقيق</td>
            </tr>
            <tr v-for="log in logs?.data ?? []" :key="log.id">
              <td class="text-nowrap">{{ log.timestamp }}</td>
              <td>{{ log.user }}</td>
              <td><span class="badge bg-light text-dark border">{{ log.action_label }}</span></td>
              <td>{{ log.entity_type }}</td>
              <td>{{ log.entity_reference }}</td>
              <td class="text-muted small">{{ log.description }}</td>
              <td class="text-end">
                <Link :href="route('canteen.audit.show', log.id)" class="btn btn-sm btn-outline-primary">عرض</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="logs?.links?.length > 3" class="card-footer bg-transparent d-flex flex-wrap gap-1 justify-content-center">
        <template v-for="(link, i) in logs.links" :key="i">
          <Link
            v-if="link.url"
            :href="link.url"
            class="btn btn-sm"
            :class="link.active ? 'btn-primary' : 'btn-outline-secondary'"
            v-html="link.label"
          />
          <span v-else class="btn btn-sm btn-outline-secondary disabled" v-html="link.label" />
        </template>
      </div>
    </div>
  </CanteenLayout>
</template>

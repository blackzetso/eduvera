<script setup>
import { computed, ref, watch } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  type: String,
  typeLabel: String,
  report: Object,
  filters: Object,
  filterOptions: Object,
  canExport: Boolean,
})

const labels = {
  sales: 'تقرير المبيعات',
  products: 'مبيعات المنتجات',
  inventory: 'تقرير المخزون',
  students: 'مصروفات الطلاب',
  categories: 'مبيعات التصنيفات',
}

const statusLabels = {
  pending_payment: 'قيد التسوية',
  completed: 'مكتمل',
  voided: 'ملغي',
  failed: 'فاشل',
}

const title = computed(() => labels[props.type] ?? props.typeLabel)

const localFilters = ref({
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  status: props.filters?.status ?? '',
  cashier_user_id: props.filters?.cashier_user_id ?? '',
  category_id: props.filters?.category_id ?? '',
  stock_status: props.filters?.stock_status ?? '',
  student_id_ref: props.filters?.student_id_ref ?? '',
  grade: props.filters?.grade ?? '',
})

watch(() => props.filters, (f) => {
  localFilters.value = {
    from: f?.from ?? '',
    to: f?.to ?? '',
    status: f?.status ?? '',
    cashier_user_id: f?.cashier_user_id ?? '',
    category_id: f?.category_id ?? '',
    stock_status: f?.stock_status ?? '',
    student_id_ref: f?.student_id_ref ?? '',
    grade: f?.grade ?? '',
  }
}, { deep: true })

function applyFilters() {
  router.get(route('canteen.reports.show', props.type), {
    ...localFilters.value,
  }, { preserveState: true })
}

function exportUrl() {
  const params = new URLSearchParams(localFilters.value)
  return `${route('canteen.reports.export', props.type)}?${params.toString()}`
}

function printUrl() {
  const params = new URLSearchParams(localFilters.value)
  return `${route('canteen.reports.print', props.type)}?${params.toString()}`
}

const hasDateRange = computed(() => ['sales', 'products', 'students', 'categories'].includes(props.type))
</script>

<template>
  <CanteenLayout>
    <Head :title="title" />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
      <div>
        <h4 class="mb-0">{{ title }}</h4>
        <p v-if="report?.from && report?.to" class="text-muted small mb-0">{{ report.from }} — {{ report.to }}</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <Link :href="route('canteen.reports.index')" class="btn btn-light btn-sm">رجوع</Link>
        <a :href="printUrl()" target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-printer me-1"></i>طباعة
        </a>
        <a v-if="canExport" :href="exportUrl()" class="btn btn-outline-success btn-sm">
          <i class="bi bi-file-earmark-excel me-1"></i>تصدير Excel
        </a>
      </div>
    </div>

    <!-- Filters -->
    <div class="card border mb-4">
      <div class="card-header bg-transparent"><strong>التصفية</strong></div>
      <div class="card-body row g-2">
        <template v-if="hasDateRange">
          <div class="col-md-3">
            <label class="form-label small">من</label>
            <input v-model="localFilters.from" type="date" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label small">إلى</label>
            <input v-model="localFilters.to" type="date" class="form-control form-control-sm">
          </div>
        </template>

        <template v-if="type === 'sales'">
          <div class="col-md-3">
            <label class="form-label small">الحالة</label>
            <select v-model="localFilters.status" class="form-select form-select-sm">
              <option value="">الكل</option>
              <option v-for="s in filterOptions?.statuses ?? []" :key="s" :value="s">{{ statusLabels[s] ?? s }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small">الكاشير</label>
            <select v-model="localFilters.cashier_user_id" class="form-select form-select-sm">
              <option value="">الكل</option>
              <option v-for="c in filterOptions?.cashiers ?? []" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </template>

        <template v-if="type === 'products'">
          <div class="col-md-3">
            <label class="form-label small">التصنيف</label>
            <select v-model="localFilters.category_id" class="form-select form-select-sm">
              <option value="">الكل</option>
              <option v-for="cat in filterOptions?.categories ?? []" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>
        </template>

        <template v-if="type === 'inventory'">
          <div class="col-md-3">
            <label class="form-label small">التصنيف</label>
            <select v-model="localFilters.category_id" class="form-select form-select-sm">
              <option value="">الكل</option>
              <option v-for="cat in filterOptions?.categories ?? []" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small">حالة المخزون</label>
            <select v-model="localFilters.stock_status" class="form-select form-select-sm">
              <option value="">الكل</option>
              <option value="low">منخفض</option>
              <option value="out">نفد</option>
              <option value="ok">متوفر</option>
            </select>
          </div>
        </template>

        <template v-if="type === 'students'">
          <div class="col-md-3">
            <label class="form-label small">رقم الطالب</label>
            <input v-model="localFilters.student_id_ref" class="form-control form-control-sm" placeholder="STU-001">
          </div>
          <div class="col-md-3">
            <label class="form-label small">الصف</label>
            <select v-model="localFilters.grade" class="form-select form-select-sm">
              <option value="">الكل</option>
              <option v-for="g in filterOptions?.grades ?? []" :key="g" :value="g">{{ g }}</option>
            </select>
          </div>
        </template>

        <div class="col-auto d-flex align-items-end">
          <button type="button" class="btn btn-primary btn-sm" @click="applyFilters">تطبيق</button>
        </div>
      </div>
    </div>

    <!-- Summary cards: Sales -->
    <div v-if="type === 'sales' && report?.summary" class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted">إجمالي المبيعات</h6>
            <h3 class="mb-0">{{ report.summary.total_sales }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted">إجمالي الإيرادات</h6>
            <h3 class="mb-0">{{ report.summary.total_revenue }} EGP</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted">متوسط قيمة البيع</h6>
            <h3 class="mb-0">{{ report.summary.average_sale_value }} EGP</h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary: Products -->
    <div v-if="type === 'products' && report?.summary" class="row g-3 mb-4">
      <div class="col-lg-6">
        <div class="card border h-100">
          <div class="card-header bg-transparent"><strong>الأكثر مبيعاً</strong></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>المنتج</th><th>الكمية</th></tr></thead>
              <tbody>
                <tr v-for="(row, i) in report.summary.top_selling" :key="i">
                  <td>{{ row.product }}</td>
                  <td>{{ row.quantity_sold }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card border h-100">
          <div class="card-header bg-transparent"><strong>الأقل مبيعاً</strong></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>المنتج</th><th>الكمية</th></tr></thead>
              <tbody>
                <tr v-for="(row, i) in report.summary.lowest_selling" :key="i">
                  <td>{{ row.product }}</td>
                  <td>{{ row.quantity_sold }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary: Inventory -->
    <div v-if="type === 'inventory' && report?.summary" class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border"><div class="card-body">
          <h6 class="text-muted">إجمالي المنتجات</h6>
          <h3 class="mb-0">{{ report.summary.total_products }}</h3>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card border"><div class="card-body">
          <h6 class="text-muted">مخزون منخفض</h6>
          <h3 class="mb-0 text-warning">{{ report.summary.low_stock_count }}</h3>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card border"><div class="card-body">
          <h6 class="text-muted">نفد المخزون</h6>
          <h3 class="mb-0 text-danger">{{ report.summary.out_of_stock_count }}</h3>
        </div></div>
      </div>
    </div>

    <!-- Data table -->
    <div class="card border">
      <div class="table-responsive">
        <!-- Sales -->
        <table v-if="type === 'sales'" class="table table-hover mb-0">
          <thead>
            <tr>
              <th>التاريخ</th><th>رقم العملية</th><th>الطالب</th><th>الكاشير</th>
              <th>طريقة الدفع</th><th>الإجمالي</th><th>الحالة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in report?.rows ?? []" :key="i">
              <td>{{ row.date }}</td>
              <td>{{ row.sale_number }}</td>
              <td>{{ row.student }}</td>
              <td>{{ row.cashier }}</td>
              <td>{{ row.payment_method }}</td>
              <td>{{ row.total }} EGP</td>
              <td><span class="badge bg-light text-dark">{{ statusLabels[row.status] ?? row.status }}</span></td>
            </tr>
            <tr v-if="!(report?.rows?.length)"><td colspan="7" class="text-center text-muted">لا توجد بيانات</td></tr>
          </tbody>
        </table>

        <!-- Products -->
        <table v-else-if="type === 'products'" class="table table-hover mb-0">
          <thead>
            <tr><th>المنتج</th><th>الكمية المباعة</th><th>الإيراد</th><th>التكلفة</th><th>الربح</th></tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in report?.rows ?? []" :key="i">
              <td>{{ row.product }}</td>
              <td>{{ row.quantity_sold }}</td>
              <td>{{ row.revenue }} EGP</td>
              <td>{{ row.cost }} EGP</td>
              <td>{{ row.profit }} EGP</td>
            </tr>
            <tr v-if="!(report?.rows?.length)"><td colspan="5" class="text-center text-muted">لا توجد بيانات</td></tr>
          </tbody>
        </table>

        <!-- Inventory -->
        <table v-else-if="type === 'inventory'" class="table table-hover mb-0">
          <thead>
            <tr>
              <th>المنتج</th><th>المخزون الحالي</th><th>الحد الأدنى</th>
              <th>منخفض</th><th>نفد</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in report?.rows ?? []" :key="i">
              <td>{{ row.product }}</td>
              <td>{{ row.current_stock }}</td>
              <td>{{ row.minimum_stock }}</td>
              <td><span v-if="row.is_low_stock" class="badge bg-warning text-dark">نعم</span><span v-else class="text-muted">لا</span></td>
              <td><span v-if="row.is_out_of_stock" class="badge bg-danger">نعم</span><span v-else class="text-muted">لا</span></td>
            </tr>
            <tr v-if="!(report?.rows?.length)"><td colspan="5" class="text-center text-muted">لا توجد بيانات</td></tr>
          </tbody>
        </table>

        <!-- Students -->
        <table v-else-if="type === 'students'" class="table table-hover mb-0">
          <thead>
            <tr><th>الطالب</th><th>الصف</th><th>عدد المشتريات</th><th>إجمالي المصروف</th><th>متوسط الشراء</th></tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in report?.rows ?? []" :key="i">
              <td>{{ row.student }}</td>
              <td>{{ row.grade ?? '—' }}</td>
              <td>{{ row.total_purchases }}</td>
              <td>{{ row.total_spent }} EGP</td>
              <td>{{ row.average_spend }} EGP</td>
            </tr>
            <tr v-if="!(report?.rows?.length)"><td colspan="5" class="text-center text-muted">لا توجد بيانات</td></tr>
          </tbody>
        </table>

        <!-- Categories -->
        <table v-else-if="type === 'categories'" class="table table-hover mb-0">
          <thead>
            <tr><th>التصنيف</th><th>الكمية المباعة</th><th>الإيراد</th><th>نسبة المبيعات</th></tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in report?.rows ?? []" :key="i">
              <td>{{ row.category }}</td>
              <td>{{ row.quantity_sold }}</td>
              <td>{{ row.revenue }} EGP</td>
              <td>{{ row.percentage_of_total }}%</td>
            </tr>
            <tr v-if="!(report?.rows?.length)"><td colspan="4" class="text-center text-muted">لا توجد بيانات</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </CanteenLayout>
</template>

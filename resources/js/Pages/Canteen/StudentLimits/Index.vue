<script setup>
import { computed, ref } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  profiles: Object,
  rules: Array,
  products: Array,
  categories: Array,
  blockSources: Array,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')
const activeTab = ref(props.filters?.tab ?? 'limits')
const showProfileForm = ref(false)
const expandedStudent = ref(null)
const assignRuleId = ref('')
const blockedStudentRef = ref('')
const blockProductId = ref('')
const blockCategoryId = ref('')
const blockReason = ref('')
const blockNotes = ref('')
const blockSource = ref('parent_request')
const restrictionType = ref('permanent')
const durationDays = ref(30)
const durationPreset = ref('30')
const durationPresets = [7, 30, 60, 90]
const showUnblockModal = ref(false)
const unblockTarget = ref(null)
const unblockId = ref(null)
const unblockBusy = ref(false)
const successMessage = ref('')

const profileForm = useForm({
  student_id_ref: '',
  student_name: '',
  grade: '',
  class_name: '',
  daily_spending_limit: '',
})

const ruleTypeLabels = {
  block_category: 'حظر تصنيف',
  block_product: 'حظر منتج',
  block_tag: 'حظر وسم',
  require_tag: 'يتطلب وسم',
  max_qty_per_day: 'حد يومي',
}

const severityLabels = { block: 'حظر', warn: 'تحذير' }

const blockSourceLabels = {
  parent_request: 'طلب ولي الأمر',
  admin: 'إداري',
}

function blockPayload() {
  const payload = {
    block_source: blockSource.value,
    restriction_type: restrictionType.value,
    reason: blockReason.value || null,
    notes: blockNotes.value || null,
  }
  if (restrictionType.value === 'temporary') {
    payload.duration_days = Number(durationDays.value) || 30
  }
  return payload
}

function onDurationPresetChange() {
  if (durationPreset.value === 'custom') return
  durationDays.value = Number(durationPreset.value)
}

function formatDate(value) {
  return value || '—'
}

function applyFilters() {
  router.get(route('canteen.student-limits.index'), {
    search: search.value,
    tab: activeTab.value,
  }, { preserveState: true })
}

function switchTab(tab) {
  activeTab.value = tab
  applyFilters()
}

function saveProfile() {
  profileForm.post(route('canteen.student-limits.profiles.store'), {
    onSuccess: () => { showProfileForm.value = false; profileForm.reset() },
  })
}

function updateLimit(profile) {
  router.put(route('canteen.student-limits.profiles.update', profile.id), {
    daily_spending_limit: profile.daily_spending_limit,
    is_active: profile.is_active,
  })
}

function toggleRestrictions(ref) {
  expandedStudent.value = expandedStudent.value === ref ? null : ref
  assignRuleId.value = ''
}

function assignRule(studentRef) {
  if (!assignRuleId.value) return
  router.post(route('canteen.student-limits.restrictions.assign'), {
    student_id_ref: studentRef,
    rule_id: assignRuleId.value,
  }, {
    onSuccess: () => { assignRuleId.value = '' },
  })
}

function removeRule(assignmentId) {
  if (!confirm('إزالة القاعدة من الطالب؟')) return
  router.delete(route('canteen.student-limits.restrictions.destroy', assignmentId))
}

function selectedBlockedProfile() {
  return (props.profiles?.data ?? []).find((p) => p.student_id_ref === blockedStudentRef.value)
}

function addBlockedProduct() {
  if (!blockedStudentRef.value || !blockProductId.value) return
  router.post(route('canteen.student-limits.blocked-products.store'), {
    student_id_ref: blockedStudentRef.value,
    product_id: blockProductId.value,
    ...blockPayload(),
  }, {
    onSuccess: () => {
      blockProductId.value = ''
      blockReason.value = ''
      blockNotes.value = ''
    },
  })
}

function openUnblockModal(type, blockId) {
  unblockTarget.value = type
  unblockId.value = blockId
  showUnblockModal.value = true
}

function closeUnblockModal() {
  if (unblockBusy.value) return
  showUnblockModal.value = false
  unblockTarget.value = null
  unblockId.value = null
}

function confirmUnblock() {
  if (!unblockId.value || !unblockTarget.value) return

  const url = unblockTarget.value === 'product'
    ? route('canteen.student-limits.blocked-products.destroy', unblockId.value)
    : route('canteen.student-limits.blocked-categories.destroy', unblockId.value)

  unblockBusy.value = true
  router.delete(url, {
    preserveScroll: true,
    onSuccess: () => {
      successMessage.value = 'تمت إزالة الحظر بنجاح.'
      showUnblockModal.value = false
      unblockTarget.value = null
      unblockId.value = null
      setTimeout(() => { successMessage.value = '' }, 4000)
    },
    onFinish: () => { unblockBusy.value = false },
  })
}

const unblockModalMessage = computed(() => (
  unblockTarget.value === 'category'
    ? 'هل تريد إزالة الحظر عن هذا التصنيف للطالب؟'
    : 'هل تريد إزالة الحظر عن هذا المنتج للطالب؟'
))

function addBlockedCategory() {
  if (!blockedStudentRef.value || !blockCategoryId.value) return
  router.post(route('canteen.student-limits.blocked-categories.store'), {
    student_id_ref: blockedStudentRef.value,
    category_id: blockCategoryId.value,
    ...blockPayload(),
  }, {
    onSuccess: () => {
      blockCategoryId.value = ''
      blockReason.value = ''
      blockNotes.value = ''
    },
  })
}


function productLabel(product) {
  const name = product.name_ar || product.name
  const cat = product.category?.name_ar || product.category?.name
  return cat ? `${name} (${cat})` : name
}
</script>

<template>
  <CanteenLayout>
    <Head title="Student Limits" />

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">حدود الطلاب والقيود</h4>
        <p class="text-muted small mb-0">إدارة الحدود اليومية، القواعد، وحظر المنتجات بطلب ولي الأمر</p>
      </div>
      <button class="btn btn-primary" @click="showProfileForm = true">إضافة طالب</button>
    </div>

    <div v-if="showProfileForm" class="card border mb-4">
      <div class="card-body row g-3">
        <div class="col-md-3"><input v-model="profileForm.student_id_ref" class="form-control" placeholder="رقم الطالب"></div>
        <div class="col-md-3"><input v-model="profileForm.student_name" class="form-control" placeholder="الاسم"></div>
        <div class="col-md-2"><input v-model="profileForm.grade" class="form-control" placeholder="الصف"></div>
        <div class="col-md-2"><input v-model="profileForm.class_name" class="form-control" placeholder="الفصل"></div>
        <div class="col-md-2"><input v-model="profileForm.daily_spending_limit" type="number" class="form-control" placeholder="الحد اليومي"></div>
        <div class="col-12">
          <button class="btn btn-primary me-2" @click="saveProfile">حفظ</button>
          <button class="btn btn-light" @click="showProfileForm = false">إلغاء</button>
        </div>
      </div>
    </div>

    <ul class="nav nav-tabs mb-4">
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: activeTab === 'limits' }" @click="switchTab('limits')">
          الحدود
        </button>
      </li>
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: activeTab === 'rules' }" @click="switchTab('rules')">
          القواعد
        </button>
      </li>
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: activeTab === 'blocked' }" @click="switchTab('blocked')">
          المنتجات المحظورة
        </button>
      </li>
    </ul>

    <div class="card border mb-4">
      <div class="card-body">
        <input v-model="search" class="form-control" placeholder="بحث بالاسم أو رقم الطالب..." @keyup.enter="applyFilters">
      </div>
    </div>

    <!-- Limits Tab -->
    <div v-if="activeTab === 'limits'" class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>الطالب</th>
              <th>الحد اليومي</th>
              <th>المصروف اليوم</th>
              <th>المتبقي</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in profiles?.data ?? []" :key="p.id">
              <td>{{ p.student_name }}<br><small class="text-muted">{{ p.student_id_ref }}</small></td>
              <td><input v-model="p.daily_spending_limit" type="number" class="form-control form-control-sm" style="max-width:120px"></td>
              <td>{{ p.spent_today }}</td>
              <td>{{ p.remaining_today }}</td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" @click="updateLimit(p)">حفظ</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Rules Tab -->
    <div v-if="activeTab === 'rules'" class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>الطالب</th>
              <th>القواعد النشطة</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="p in profiles?.data ?? []" :key="p.id">
              <tr>
                <td>{{ p.student_name }}<br><small class="text-muted">{{ p.student_id_ref }}</small></td>
                <td>
                  <span class="badge bg-secondary">{{ p.restrictions?.length ?? 0 }} قاعدة</span>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary" @click="toggleRestrictions(p.student_id_ref)">
                    {{ expandedStudent === p.student_id_ref ? 'إخفاء' : 'إدارة القواعد' }}
                  </button>
                </td>
              </tr>
              <tr v-if="expandedStudent === p.student_id_ref">
                <td colspan="3" class="bg-light">
                  <div class="p-2">
                    <div v-if="p.restrictions?.length" class="table-responsive mb-3">
                      <table class="table table-sm mb-0">
                        <thead>
                          <tr>
                            <th>القاعدة</th>
                            <th>النوع</th>
                            <th>الحدة</th>
                            <th>الحالة</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="r in p.restrictions" :key="r.id">
                            <td>{{ r.rule_name }}</td>
                            <td>{{ ruleTypeLabels[r.rule_type] ?? r.rule_type }}</td>
                            <td>
                              <span :class="['badge', r.severity === 'warn' ? 'bg-warning text-dark' : 'bg-danger']">
                                {{ severityLabels[r.severity] ?? r.severity }}
                              </span>
                            </td>
                            <td>
                              <span :class="['badge', r.is_active ? 'bg-success' : 'bg-secondary']">
                                {{ r.is_active ? 'نشط' : 'معطل' }}
                              </span>
                            </td>
                            <td class="text-end">
                              <button class="btn btn-sm btn-outline-danger" @click="removeRule(r.id)">إزالة</button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <p v-else class="text-muted small mb-3">لا توجد قواعد مُعيَّنة لهذا الطالب.</p>

                    <div class="row g-2 align-items-end">
                      <div class="col-md-6">
                        <label class="form-label small">إضافة قاعدة</label>
                        <select v-model="assignRuleId" class="form-select form-select-sm">
                          <option value="">اختر قاعدة</option>
                          <option v-for="rule in rules" :key="rule.id" :value="rule.id">
                            {{ rule.name }} ({{ severityLabels[rule.severity] }})
                          </option>
                        </select>
                      </div>
                      <div class="col-auto">
                        <button class="btn btn-sm btn-primary" :disabled="!assignRuleId" @click="assignRule(p.student_id_ref)">تعيين</button>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Blocked Products Tab -->
    <div v-if="activeTab === 'blocked'">
      <div v-if="successMessage" class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ successMessage }}
        <button type="button" class="btn-close" @click="successMessage = ''"></button>
      </div>
      <div class="card border mb-4">
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">اختر الطالب</label>
              <select v-model="blockedStudentRef" class="form-select">
                <option value="">— اختر طالباً —</option>
                <option v-for="p in profiles?.data ?? []" :key="p.id" :value="p.student_id_ref">
                  {{ p.student_name }} ({{ p.student_id_ref }})
                </option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">مصدر الحظر</label>
              <select v-model="blockSource" class="form-select">
                <option v-for="s in blockSources" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">السبب (اختياري)</label>
              <input v-model="blockReason" class="form-control" placeholder="مثال: طلب ولي الأمر">
            </div>
            <div class="col-md-2">
              <label class="form-label">ملاحظات</label>
              <input v-model="blockNotes" class="form-control" placeholder="ملاحظات">
            </div>
            <div class="col-12">
              <label class="form-label d-block mb-2">نوع القيد</label>
              <div class="d-flex flex-wrap gap-3 align-items-center">
                <label class="form-check">
                  <input v-model="restrictionType" type="radio" class="form-check-input" value="permanent">
                  <span class="form-check-label">دائم</span>
                </label>
                <label class="form-check">
                  <input v-model="restrictionType" type="radio" class="form-check-input" value="temporary">
                  <span class="form-check-label">مؤقت</span>
                </label>
              </div>
            </div>
            <div v-if="restrictionType === 'temporary'" class="col-md-4">
              <label class="form-label">المدة (أيام)</label>
              <select v-model="durationPreset" class="form-select" @change="onDurationPresetChange">
                <option v-for="days in durationPresets" :key="days" :value="String(days)">{{ days }} يوم</option>
                <option value="custom">مخصص</option>
              </select>
            </div>
            <div v-if="restrictionType === 'temporary' && durationPreset === 'custom'" class="col-md-3">
              <label class="form-label">عدد الأيام</label>
              <input v-model.number="durationDays" type="number" min="1" max="365" class="form-control">
            </div>
          </div>
        </div>
      </div>

      <template v-if="blockedStudentRef && selectedBlockedProfile()">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card border h-100">
              <div class="card-header bg-white fw-semibold">المنتجات المحظورة</div>
              <div class="card-body">
                <div class="row g-2 mb-3">
                  <div class="col">
                    <select v-model="blockProductId" class="form-select form-select-sm">
                      <option value="">اختر منتجاً للحظر</option>
                      <option v-for="product in products" :key="product.id" :value="product.id">
                        {{ productLabel(product) }}
                      </option>
                    </select>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-sm btn-danger" :disabled="!blockProductId" @click="addBlockedProduct">حظر منتج</button>
                  </div>
                </div>

                <div v-if="selectedBlockedProfile().blocked_products?.length" class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>المنتج</th>
                        <th>المصدر</th>
                        <th>النوع</th>
                        <th>تاريخ البداية</th>
                        <th>تاريخ الانتهاء</th>
                        <th>المتبقي</th>
                        <th>السبب</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="b in selectedBlockedProfile().blocked_products" :key="b.id">
                        <td>{{ b.product_name }}<br><small class="text-muted">{{ b.category || '—' }}</small></td>
                        <td>{{ blockSourceLabels[b.block_source] ?? b.block_source }}</td>
                        <td><span :class="['badge', b.badge_class]">{{ b.badge_label }}</span></td>
                        <td>{{ formatDate(b.starts_at) }}</td>
                        <td>{{ b.restriction_type === 'permanent' ? 'دائم' : formatDate(b.expires_at) }}</td>
                        <td>{{ b.remaining_label }}</td>
                        <td>{{ b.reason || '—' }}</td>
                        <td class="text-end">
                          <button
                            v-if="b.is_effective"
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            @click="openUnblockModal('product', b.id)"
                          >
                            <i class="bi bi-unlock me-1"></i>إزالة الحظر
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-else class="text-muted small mb-0">لا توجد منتجات محظورة لهذا الطالب.</p>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card border h-100">
              <div class="card-header bg-white fw-semibold">التصنيفات المحظورة</div>
              <div class="card-body">
                <div class="row g-2 mb-3">
                  <div class="col">
                    <select v-model="blockCategoryId" class="form-select form-select-sm">
                      <option value="">اختر تصنيفاً للحظر</option>
                      <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.name_ar || cat.name }}
                      </option>
                    </select>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-sm btn-danger" :disabled="!blockCategoryId" @click="addBlockedCategory">حظر تصنيف</button>
                  </div>
                </div>

                <div v-if="selectedBlockedProfile().blocked_categories?.length" class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>التصنيف</th>
                        <th>المصدر</th>
                        <th>النوع</th>
                        <th>تاريخ البداية</th>
                        <th>تاريخ الانتهاء</th>
                        <th>المتبقي</th>
                        <th>السبب</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="b in selectedBlockedProfile().blocked_categories" :key="b.id">
                        <td>{{ b.category_name }}</td>
                        <td>{{ blockSourceLabels[b.block_source] ?? b.block_source }}</td>
                        <td><span :class="['badge', b.badge_class]">{{ b.badge_label }}</span></td>
                        <td>{{ formatDate(b.starts_at) }}</td>
                        <td>{{ b.restriction_type === 'permanent' ? 'دائم' : formatDate(b.expires_at) }}</td>
                        <td>{{ b.remaining_label }}</td>
                        <td>{{ b.reason || '—' }}</td>
                        <td class="text-end">
                          <button
                            v-if="b.is_effective"
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            @click="openUnblockModal('category', b.id)"
                          >
                            <i class="bi bi-unlock me-1"></i>إزالة الحظر
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-else class="text-muted small mb-0">لا توجد تصنيفات محظورة لهذا الطالب.</p>
              </div>
            </div>
          </div>
        </div>
      </template>

      <div v-else class="card border">
        <div class="card-body text-center text-muted py-5">
          <i class="bi bi-shield-x display-6 d-block mb-2"></i>
          اختر طالباً لإدارة المنتجات والتصنيفات المحظورة بطلب ولي الأمر
        </div>
      </div>
    </div>

    <div
      v-if="showUnblockModal"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      style="background: rgba(0,0,0,.45);"
      @click.self="closeUnblockModal"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">إزالة الحظر</h5>
            <button type="button" class="btn-close" :disabled="unblockBusy" @click="closeUnblockModal"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0">{{ unblockModalMessage }}</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" :disabled="unblockBusy" @click="closeUnblockModal">إلغاء</button>
            <button type="button" class="btn btn-outline-danger" :disabled="unblockBusy" @click="confirmUnblock">
              <span v-if="unblockBusy" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-unlock me-1"></i>
              تأكيد إزالة الحظر
            </button>
          </div>
        </div>
      </div>
    </div>
  </CanteenLayout>
</template>

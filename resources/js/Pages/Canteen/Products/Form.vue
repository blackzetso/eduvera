<script setup>
import { computed, ref, watch } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  product: Object,
  categories: Object,
  can_manage_inventory: Boolean,
  can_view_inventory: Boolean,
})

const tagSuggestions = ['soda', 'chocolate', 'chips', 'healthy']
const tagInput = ref('')

const categoryList = computed(() => props.categories?.data ?? props.categories ?? [])
const productData = computed(() => props.product?.data ?? props.product ?? null)
const isEditing = computed(() => !!productData.value?.id)

const onHand = ref(productData.value?.on_hand ?? '0')

watch(() => productData.value?.on_hand, (value) => {
  if (value !== undefined && value !== null) {
    onHand.value = String(value)
  }
})

const unitOptions = [
  { value: 'piece', label: 'قطعة' },
  { value: 'pack', label: 'عبوة' },
  { value: 'serving', label: 'وجبة' },
]

const inventoryTypeOptions = [
  { value: 'purchase', label: 'استلام / شراء' },
  { value: 'adjustment', label: 'تعديل (+/-)' },
  { value: 'damage', label: 'تالف / خسارة' },
  { value: 'return', label: 'مرتجع' },
]

const form = useForm({
  category_id: productData.value?.category_id ?? '',
  sku: productData.value?.sku ?? '',
  barcode: productData.value?.barcode ?? '',
  name: productData.value?.name ?? '',
  name_ar: productData.value?.name_ar ?? '',
  description: productData.value?.description ?? '',
  unit: productData.value?.unit ?? 'piece',
  selling_price: productData.value?.selling_price ?? '',
  cost_price: productData.value?.cost_price ?? '',
  is_active: productData.value?.is_active ?? true,
  is_restricted_default: productData.value?.is_restricted_default ?? false,
  restriction_tags: [...(productData.value?.restriction_tags ?? [])],
  initial_stock: '',
})

const inventoryForm = useForm({
  product_id: productData.value?.id ?? '',
  type: 'purchase',
  quantity_delta: '',
  notes: '',
})

const errorList = computed(() => Object.entries(form.errors).map(([field, msg]) => ({ field, msg })))

const stockBadgeClass = computed(() => {
  const qty = parseFloat(onHand.value)
  if (qty <= 0) return 'bg-danger'
  if (qty <= 5) return 'bg-warning text-dark'
  return 'bg-success'
})

function fieldError(field) {
  return form.errors[field]
}

function inventoryFieldError(field) {
  return inventoryForm.errors[field]
}

function addTag(tag) {
  const value = (tag || tagInput.value).trim().toLowerCase()
  if (!value || form.restriction_tags.includes(value)) return
  form.restriction_tags.push(value)
  tagInput.value = ''
}

function removeTag(tag) {
  form.restriction_tags = form.restriction_tags.filter((t) => t !== tag)
}

function normalizeBeforeSubmit() {
  if (!form.name?.trim() && form.name_ar?.trim()) {
    form.name = form.name_ar.trim()
  }
  if (!form.unit || !['piece', 'pack', 'serving'].includes(form.unit)) {
    form.unit = 'piece'
  }
}

function submit() {
  normalizeBeforeSubmit()
  if (isEditing.value) {
    form.put(route('canteen.products.update', productData.value.id))
  } else {
    form.post(route('canteen.products.store'))
  }
}

function submitInventory() {
  if (!productData.value?.id) return

  inventoryForm.product_id = productData.value.id
  inventoryForm.post(route('canteen.inventory.adjust'), {
    preserveScroll: true,
    onSuccess: () => {
      inventoryForm.quantity_delta = ''
      inventoryForm.notes = ''
      router.reload({ only: ['product'] })
    },
  })
}
</script>

<template>
  <CanteenLayout>
    <Head :title="isEditing ? 'Edit Product' : 'New Product'" />

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">{{ isEditing ? 'تعديل منتج' : 'منتج جديد' }}</h4>
      <Link :href="route('canteen.products.index')" class="btn btn-light">رجوع</Link>
    </div>

    <div v-if="!categoryList.length" class="alert alert-warning">
      لا توجد تصنيفات نشطة.
      <Link :href="route('canteen.categories.index')" class="alert-link">أضف تصنيفاً أولاً</Link>
    </div>

    <div v-if="errorList.length" class="alert alert-danger">
      <div class="fw-semibold mb-2">تعذر حفظ المنتج — راجع الحقول التالية:</div>
      <ul class="mb-0 small">
        <li v-for="e in errorList" :key="e.field">{{ e.msg }}</li>
      </ul>
    </div>

    <form class="card border mb-4" @submit.prevent="submit">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">التصنيف <span class="text-danger">*</span></label>
            <select
              v-model="form.category_id"
              class="form-select"
              :class="{ 'is-invalid': fieldError('category_id') }"
              required
            >
              <option value="">اختر تصنيف</option>
              <option v-for="c in categoryList" :key="c.id" :value="c.id">
                {{ c.name_ar || c.name }}
              </option>
            </select>
            <div v-if="fieldError('category_id')" class="invalid-feedback d-block">{{ fieldError('category_id') }}</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">SKU <span class="text-danger">*</span></label>
            <input v-model="form.sku" class="form-control" :class="{ 'is-invalid': fieldError('sku') }" required>
            <div v-if="fieldError('sku')" class="invalid-feedback d-block">{{ fieldError('sku') }}</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Barcode</label>
            <input v-model="form.barcode" class="form-control" :class="{ 'is-invalid': fieldError('barcode') }">
            <div v-if="fieldError('barcode')" class="invalid-feedback d-block">{{ fieldError('barcode') }}</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">الاسم <span class="text-danger">*</span></label>
            <input
              v-model="form.name"
              class="form-control"
              :class="{ 'is-invalid': fieldError('name') }"
              placeholder="يُنسخ تلقائياً من الاسم العربي إن تُرك فارغاً"
            >
            <div v-if="fieldError('name')" class="invalid-feedback d-block">{{ fieldError('name') }}</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">الاسم (عربي)</label>
            <input v-model="form.name_ar" class="form-control" :class="{ 'is-invalid': fieldError('name_ar') }">
            <div v-if="fieldError('name_ar')" class="invalid-feedback d-block">{{ fieldError('name_ar') }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">سعر البيع <span class="text-danger">*</span></label>
            <input
              v-model="form.selling_price"
              type="number"
              step="0.01"
              min="0"
              class="form-control"
              :class="{ 'is-invalid': fieldError('selling_price') }"
              required
            >
            <div v-if="fieldError('selling_price')" class="invalid-feedback d-block">{{ fieldError('selling_price') }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">سعر التكلفة</label>
            <input
              v-model="form.cost_price"
              type="number"
              step="0.01"
              min="0"
              class="form-control"
              :class="{ 'is-invalid': fieldError('cost_price') }"
            >
            <div v-if="fieldError('cost_price')" class="invalid-feedback d-block">{{ fieldError('cost_price') }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">الوحدة <span class="text-danger">*</span></label>
            <select v-model="form.unit" class="form-select" :class="{ 'is-invalid': fieldError('unit') }" required>
              <option v-for="u in unitOptions" :key="u.value" :value="u.value">{{ u.label }}</option>
            </select>
            <div v-if="fieldError('unit')" class="invalid-feedback d-block">{{ fieldError('unit') }}</div>
          </div>
          <div class="col-12">
            <label class="form-label">الوصف</label>
            <textarea v-model="form.description" class="form-control" rows="2"></textarea>
          </div>

          <div class="col-12">
            <div class="card border bg-light">
              <div class="card-body">
                <h6 class="mb-3">قيود المنتج</h6>
                <div class="form-check mb-3">
                  <input v-model="form.is_restricted_default" class="form-check-input" type="checkbox" id="restrictedDefault">
                  <label class="form-check-label" for="restrictedDefault">
                    مقيد افتراضياً
                    <small class="text-muted d-block">يُعامل المنتج كمقيد ويطابق قواعد الوسم المقيد الافتراضي</small>
                  </label>
                </div>
                <label class="form-label">وسوم القيود</label>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  <span v-for="tag in form.restriction_tags" :key="tag" class="badge bg-warning text-dark">
                    {{ tag }}
                    <button type="button" class="btn-close btn-close-sm ms-1" @click="removeTag(tag)"></button>
                  </span>
                </div>
                <div class="input-group mb-2">
                  <input v-model="tagInput" class="form-control" placeholder="أضف وسم (مثل soda)" @keyup.enter.prevent="addTag()">
                  <button type="button" class="btn btn-outline-secondary" @click="addTag()">إضافة</button>
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <button
                    v-for="s in tagSuggestions"
                    :key="s"
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    @click="addTag(s)"
                  >{{ s }}</button>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="form-check">
              <input v-model="form.is_active" class="form-check-input" type="checkbox" id="prodActive">
              <label class="form-check-label" for="prodActive">نشط</label>
            </div>
          </div>
        </div>
      </div>

      <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
        <small class="text-muted"><span class="text-danger">*</span> حقول مطلوبة</small>
        <button type="submit" class="btn btn-primary btn-lg px-4" :disabled="form.processing || !categoryList.length">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
          حفظ المنتج
        </button>
      </div>
    </form>

    <div v-if="can_manage_inventory || can_view_inventory" class="card border">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>المخزون</h5>
        <div v-if="isEditing" class="d-flex align-items-center gap-2">
          <span class="text-muted small">المتوفر حالياً:</span>
          <span class="badge fs-6" :class="stockBadgeClass">{{ onHand }}</span>
          <Link
            v-if="productData?.id"
            :href="route('canteen.inventory.ledger', productData.id)"
            class="btn btn-sm btn-outline-secondary"
          >
            سجل الحركات
          </Link>
        </div>
      </div>
      <div class="card-body">
        <template v-if="!isEditing && can_manage_inventory">
          <p class="text-muted small mb-3">يمكنك تحديد الرصيد الافتتاحي عند إنشاء المنتج لأول مرة.</p>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">الرصيد الافتتاحي</label>
              <input
                v-model="form.initial_stock"
                type="number"
                min="0"
                step="1"
                class="form-control"
                :class="{ 'is-invalid': fieldError('initial_stock') }"
                placeholder="0"
              >
              <div v-if="fieldError('initial_stock')" class="invalid-feedback d-block">{{ fieldError('initial_stock') }}</div>
            </div>
          </div>
        </template>

        <template v-else-if="isEditing && can_manage_inventory">
          <p class="text-muted small mb-3">
            أضف كمية موجبة للاستلام أو سالبة للخصم. مثال: <code>+50</code> استلام، <code>-3</code> تالف.
          </p>
          <div v-if="Object.keys(inventoryForm.errors).length" class="alert alert-danger py-2">
            <ul class="mb-0 small">
              <li v-for="(msg, field) in inventoryForm.errors" :key="field">{{ msg }}</li>
            </ul>
          </div>
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">نوع الحركة</label>
              <select v-model="inventoryForm.type" class="form-select">
                <option v-for="t in inventoryTypeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">الكمية (+/-)</label>
              <input
                v-model.number="inventoryForm.quantity_delta"
                type="number"
                step="1"
                class="form-control"
                :class="{ 'is-invalid': inventoryFieldError('quantity_delta') }"
                placeholder="مثال: 20 أو -2"
              >
            </div>
            <div class="col-md-4">
              <label class="form-label">ملاحظات</label>
              <input v-model="inventoryForm.notes" class="form-control" placeholder="اختياري">
            </div>
            <div class="col-md-2">
              <button
                type="button"
                class="btn btn-success w-100"
                :disabled="inventoryForm.processing || !inventoryForm.quantity_delta"
                @click="submitInventory"
              >
                <span v-if="inventoryForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                تطبيق
              </button>
            </div>
          </div>
        </template>

        <p v-else-if="isEditing && can_view_inventory" class="text-muted mb-0">
          المتوفر: <strong>{{ onHand }}</strong> — ليس لديك صلاحية تعديل المخزون.
        </p>

        <p v-else-if="!can_manage_inventory" class="text-muted mb-0 small">
          ليس لديك صلاحية إدارة المخزون.
        </p>
      </div>
    </div>
  </CanteenLayout>
</template>

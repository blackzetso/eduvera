<script setup>
import { computed } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  staff: Array,
  rules: Array,
  categories: Array,
  products: Array,
  settings: Object,
  tagSuggestions: Array,
})

const settingsForm = useForm({
  default_daily_limit: props.settings?.default_daily_limit ?? '',
  low_stock_threshold: props.settings?.low_stock_threshold ?? 10,
})

const staffForm = useForm({
  user_id: '',
  role: 'cashier',
})

const ruleForm = useForm({
  code: '',
  name: '',
  rule_type: 'block_tag',
  severity: 'block',
  config_tags: '',
  config_category_slugs: [],
  config_product_ids: [],
  config_max: '',
})

const ruleTypeLabels = {
  block_category: 'حظر تصنيف',
  block_product: 'حظر منتج',
  block_tag: 'حظر وسم',
  require_tag: 'يتطلب وسم',
  max_qty_per_day: 'حد يومي',
}

const severityLabels = { block: 'حظر', warn: 'تحذير' }

const needsTags = computed(() => ['block_tag', 'require_tag', 'max_qty_per_day'].includes(ruleForm.rule_type))
const needsCategories = computed(() => ruleForm.rule_type === 'block_category')
const needsProducts = computed(() => ruleForm.rule_type === 'block_product')

function saveSettings() {
  settingsForm.put(route('canteen.settings.update'))
}

function saveStaff() {
  staffForm.post(route('canteen.settings.staff.store'), {
    onSuccess: () => staffForm.reset(),
  })
}

function buildConfig() {
  const config = {}
  if (needsTags.value) {
    config.tags = ruleForm.config_tags.split(',').map((t) => t.trim()).filter(Boolean)
    if (ruleForm.rule_type === 'max_qty_per_day') {
      config.max = Number(ruleForm.config_max) || 0
    }
  }
  if (needsCategories.value) {
    config.category_slugs = ruleForm.config_category_slugs
  }
  if (needsProducts.value) {
    config.product_ids = ruleForm.config_product_ids
  }
  return config
}

function formatRuleConfig(config) {
  if (!config) return '—'
  const parts = []
  if (config.tags?.length) parts.push(`وسوم: ${config.tags.join(', ')}`)
  if (config.category_slugs?.length) parts.push(`تصنيفات: ${config.category_slugs.join(', ')}`)
  if (config.product_ids?.length) parts.push(`منتجات: ${config.product_ids.length}`)
  if (config.max) parts.push(`حد: ${config.max}`)
  return parts.join(' | ') || '—'
}

function saveRule() {
  ruleForm.transform((data) => ({
    code: data.code,
    name: data.name,
    rule_type: data.rule_type,
    severity: data.severity,
    config: buildConfig(),
  })).post(route('canteen.settings.rules.store'), {
    onSuccess: () => ruleForm.reset(),
  })
}
</script>

<template>
  <CanteenLayout>
    <Head title="Settings" />

    <h4 class="mb-4">إعدادات الكافتيريا</h4>

    <div class="card border mb-4">
      <div class="card-header bg-transparent"><strong>الإعدادات العامة</strong></div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label">الحد اليومي الافتراضي</label>
          <input v-model="settingsForm.default_daily_limit" type="number" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">عتبة المخزون المنخفض</label>
          <input v-model="settingsForm.low_stock_threshold" type="number" class="form-control">
        </div>
        <div class="col-12">
          <button class="btn btn-primary" @click="saveSettings">حفظ الإعدادات</button>
        </div>
      </div>
    </div>

    <div class="card border mb-4">
      <div class="card-header bg-transparent"><strong>الموظفون</strong></div>
      <div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-md-4"><input v-model="staffForm.user_id" type="number" class="form-control" placeholder="User ID"></div>
          <div class="col-md-4">
            <select v-model="staffForm.role" class="form-select">
              <option value="manager">مدير</option>
              <option value="cashier">كاشير</option>
            </select>
          </div>
          <div class="col-auto"><button class="btn btn-outline-primary" @click="saveStaff">إضافة</button></div>
        </div>
        <ul class="list-group">
          <li v-for="s in staff" :key="s.id" class="list-group-item d-flex justify-content-between">
            <span>{{ s.user?.name ?? s.user_id }} — {{ s.role }}</span>
            <span :class="['badge', s.is_active ? 'bg-success' : 'bg-secondary']">{{ s.is_active ? 'نشط' : 'معطل' }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="card border">
      <div class="card-header bg-transparent"><strong>قواعد القيود</strong></div>
      <div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-md-2"><input v-model="ruleForm.code" class="form-control" placeholder="Code"></div>
          <div class="col-md-3"><input v-model="ruleForm.name" class="form-control" placeholder="الاسم (مثل No Soda)"></div>
          <div class="col-md-2">
            <select v-model="ruleForm.rule_type" class="form-select">
              <option value="block_tag">حظر وسم</option>
              <option value="require_tag">يتطلب وسم</option>
              <option value="block_category">حظر تصنيف</option>
              <option value="block_product">حظر منتج</option>
              <option value="max_qty_per_day">حد يومي</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="ruleForm.severity" class="form-select">
              <option value="block">حظر — يمنع الشراء</option>
              <option value="warn">تحذير — يسمح مع تنبيه</option>
            </select>
          </div>
          <div class="col-md-3" v-if="needsTags">
            <input v-model="ruleForm.config_tags" class="form-control" placeholder="وسوم: soda,chocolate">
            <small class="text-muted">اقتراحات: {{ (tagSuggestions ?? []).join(', ') }}</small>
          </div>
          <div class="col-md-3" v-if="needsCategories">
            <select v-model="ruleForm.config_category_slugs" class="form-select" multiple>
              <option v-for="c in categories" :key="c.id" :value="c.slug">{{ c.name }}</option>
            </select>
          </div>
          <div class="col-md-3" v-if="needsProducts">
            <select v-model="ruleForm.config_product_ids" class="form-select" multiple>
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div class="col-md-2" v-if="ruleForm.rule_type === 'max_qty_per_day'">
            <input v-model="ruleForm.config_max" type="number" class="form-control" placeholder="الحد الأقصى">
          </div>
          <div class="col-auto d-flex align-items-start">
            <button class="btn btn-outline-primary" @click="saveRule">إضافة قاعدة</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>القاعدة</th>
                <th>النوع</th>
                <th>الحدة</th>
                <th>الإعداد</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rules" :key="r.id">
                <td>{{ r.name }}<br><small class="text-muted">{{ r.code }}</small></td>
                <td>{{ ruleTypeLabels[r.rule_type] ?? r.rule_type }}</td>
                <td>
                  <span :class="['badge', r.severity === 'warn' ? 'bg-warning text-dark' : 'bg-danger']">
                    {{ severityLabels[r.severity] ?? r.severity }}
                  </span>
                </td>
                <td class="small text-muted">{{ formatRuleConfig(r.config) }}</td>
                <td>
                  <span :class="['badge', r.is_active ? 'bg-success' : 'bg-secondary']">
                    {{ r.is_active ? 'نشط' : 'معطل' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </CanteenLayout>
</template>

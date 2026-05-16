<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  parents:    { type: Array, default: () => [] },
})

// ── 4-level cascading ────────────────────────────────────────────────────────
const selectedTopId = ref(null)
const selectedMidId = ref(null)
const selectedSectionId = ref(null)

const parentSearch = ref('')

const filteredParents = computed(() => {
  if (!parentSearch.value) return props.parents
  const q = parentSearch.value.toLowerCase()
  return props.parents.filter(p =>
    p.name?.toLowerCase().includes(q) ||
    p.email?.toLowerCase().includes(q) ||
    p.national_id?.toLowerCase().includes(q)
  )
})

const yearCategories = computed(() => {
  if (!selectedTopId.value) return []
  return props.categories.find(c => c.id === selectedTopId.value)?.children ?? []
})

const sectionCategories = computed(() => {
  if (!selectedMidId.value) return []
  return yearCategories.value.find(c => c.id === selectedMidId.value)?.children ?? []
})

const subSectionCategories = computed(() => {
  if (!selectedSectionId.value) return []
  return sectionCategories.value.find(c => c.id === selectedSectionId.value)?.children ?? []
})

function onTopChange() {
  selectedMidId.value = null
  selectedSectionId.value = null
  form.category_id = ''
}
function onMidChange() {
  selectedSectionId.value = null
  form.category_id = ''
}
function onSectionChange() {
  form.category_id = ''
}
// ─────────────────────────────────────────────────────────────────────────────

const form = useForm({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  category_id: '',
  guardian_ids: [],
})

function toggleParent(id) {
  const idx = form.guardian_ids.indexOf(id)
  if (idx === -1) form.guardian_ids.push(id)
  else form.guardian_ids.splice(idx, 1)
}

function isParentSelected(id) {
  return form.guardian_ids.includes(id)
}

function submit() {
  form.post(route('admin.students.store'))
}
</script>

<template>
  <Head title="إضافة طالب جديد" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4>إضافة طالب جديد</h4>
          <Link :href="route('admin.students.index')" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> العودة
          </Link>
        </div>

        <div class="card">
          <div class="card-body">
            <form @submit.prevent="submit">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">الاسم <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.name }"
                    v-model="form.name"
                    required
                  >
                  <div v-if="form.errors.name" class="invalid-feedback">
                    {{ form.errors.name }}
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                  <input
                    type="email"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.email }"
                    v-model="form.email"
                    required
                  >
                  <div v-if="form.errors.email" class="invalid-feedback">
                    {{ form.errors.email }}
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">رقم الهاتف</label>
                  <input
                    type="tel"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.phone }"
                    v-model="form.phone"
                  >
                  <div v-if="form.errors.phone" class="invalid-feedback">
                    {{ form.errors.phone }}
                  </div>
                </div>

                <div class="col-md-3" v-if="categories.length > 0">
                  <label class="form-label">الشعبة <span class="text-danger">*</span></label>
                  <select class="form-select" v-model="selectedTopId" @change="onTopChange">
                    <option :value="null">— عربي / إنجليزي —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

                <div class="col-md-3" v-if="selectedTopId">
                  <label class="form-label">المرحلة الدراسية <span class="text-danger">*</span></label>
                  <select class="form-select" v-model="selectedMidId" @change="onMidChange">
                    <option :value="null">— اختر المرحلة —</option>
                    <option v-for="y in yearCategories" :key="y.id" :value="y.id">{{ y.name }}</option>
                  </select>
                </div>

                <div class="col-md-3" v-if="selectedMidId && sectionCategories.length > 0">
                  <label class="form-label">الفصل الدراسي <span class="text-danger">*</span></label>
                  <select class="form-select" v-model="selectedSectionId" @change="onSectionChange">
                    <option :value="null">— اختر الفصل الدراسي —</option>
                    <option v-for="s in sectionCategories" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>

                <div class="col-md-3" v-if="selectedSectionId && subSectionCategories.length > 0">
                  <label class="form-label">الفصل <span class="text-danger">*</span></label>
                  <select
                    class="form-select"
                    :class="{ 'is-invalid': form.errors.category_id }"
                    v-model="form.category_id"
                    required
                  >
                    <option value="">— اختر القسم الفرعي —</option>
                    <option v-for="sub in subSectionCategories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                  </select>
                  <div v-if="form.errors.category_id" class="invalid-feedback">
                    {{ form.errors.category_id }}
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                  <input
                    type="password"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.password }"
                    v-model="form.password"
                    required
                  >
                  <div v-if="form.errors.password" class="invalid-feedback">
                    {{ form.errors.password }}
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                  <input
                    type="password"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.password_confirmation }"
                    v-model="form.password_confirmation"
                    required
                  >
                  <div v-if="form.errors.password_confirmation" class="invalid-feedback">
                    {{ form.errors.password_confirmation }}
                  </div>
                </div>
              </div>

              <!-- Parent Picker -->
              <div class="mt-4">
                <div class="card border-0 bg-light rounded-3">
                  <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 rounded-top-3">
                    <i class="bi bi-person-heart text-primary fs-5"></i>
                    <span class="fw-semibold">ربط بولي الأمر</span>
                    <span v-if="form.guardian_ids.length > 0" class="badge bg-primary ms-auto">
                      {{ form.guardian_ids.length }} مختار
                    </span>
                  </div>
                  <div class="card-body">
                    <p class="text-muted small mb-3">اختر ولي الأمر أو أكثر المرتبطين بهذا الطالب. (اختياري)</p>

                    <div class="input-group mb-3">
                      <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                      <input
                        type="text"
                        class="form-control border-start-0"
                        placeholder="ابحث بالاسم أو البريد أو الرقم القومي..."
                        v-model="parentSearch"
                      >
                    </div>

                    <div class="parents-list" style="max-height: 240px; overflow-y: auto;">
                      <div v-if="filteredParents.length === 0" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                        لا توجد نتائج مطابقة
                      </div>
                      <div
                        v-for="parent in filteredParents"
                        :key="parent.id"
                        class="parent-item d-flex align-items-center gap-3 p-2 rounded-2 mb-1"
                        :class="isParentSelected(parent.id) ? 'selected' : ''"
                        @click="toggleParent(parent.id)"
                      >
                        <div
                          class="parent-check flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle"
                          :class="isParentSelected(parent.id) ? 'bg-primary text-white' : 'bg-white border'"
                          style="width:28px; height:28px;"
                        >
                          <i v-if="isParentSelected(parent.id)" class="bi bi-check2 fw-bold" style="font-size:14px;"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                          <div class="fw-semibold text-dark lh-sm">{{ parent.name }}</div>
                          <div class="text-muted small">{{ parent.email }}</div>
                        </div>
                        <span v-if="parent.national_id" class="badge bg-secondary bg-opacity-75 flex-shrink-0">{{ parent.national_id }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('admin.students.index')" class="btn btn-secondary">
                  إلغاء
                </Link>
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="form.processing"
                >
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                  حفظ
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.form-control.is-invalid,
.form-select.is-invalid {
  border-color: #dc3545;
}

.invalid-feedback {
  color: #dc3545;
  font-size: 0.875rem;
  display: block;
  margin-top: 0.25rem;
}

.parent-item {
  cursor: pointer;
  transition: background 0.15s;
}
.parent-item:hover {
  background: #f0f4ff;
}
.parent-item.selected {
  background: #e8f0fe;
}
.parents-list {
  scrollbar-width: thin;
  scrollbar-color: #c1c9d6 transparent;
}
.parents-list::-webkit-scrollbar {
  width: 5px;
}
.parents-list::-webkit-scrollbar-thumb {
  background: #c1c9d6;
  border-radius: 4px;
}
</style>

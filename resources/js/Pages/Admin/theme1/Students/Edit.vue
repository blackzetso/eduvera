<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  student:    Object,
  categories: { type: Array, default: () => [] },
  parents:    { type: Array, default: () => [] },
  relationshipTypeOptions: { type: Array, default: () => [] },
})

function mapGuardianLink(guardian) {
  return {
    guardian_id: guardian.id,
    relationship_type: guardian.pivot?.relationship_type || 'guardian',
    is_primary: !!guardian.pivot?.is_primary,
    is_emergency_contact: !!guardian.pivot?.is_emergency_contact,
    is_pickup_authorized: guardian.pivot?.is_pickup_authorized !== false,
    is_financial_responsible: !!guardian.pivot?.is_financial_responsible,
  }
}

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
  form.category_id = selectedTopId.value || ''
}
function onMidChange() {
  selectedSectionId.value = null
  form.category_id = selectedMidId.value || ''
}
function onSectionChange() {
  form.category_id = selectedSectionId.value || ''
}
// ─────────────────────────────────────────────────────────────────────────────

const form = useForm({
  name:               props.student.name,
  first_name:         props.student.first_name || '',
  father_name:        props.student.father_name || '',
  grandfather_name:   props.student.grandfather_name || '',
  national_id:        props.student.national_id || '',
  date_of_birth:      props.student.date_of_birth || '',
  gender:             props.student.gender || '',
  enrollment_date:    props.student.enrollment_date || '',
  email:              props.student.email,
  phone:              props.student.phone || '',
  category_id:        props.student.category_id || '',
  guardian_links:     (props.student.guardians || []).map(mapGuardianLink),
})

function guardianLinkFor(id) {
  return form.guardian_links.find(l => l.guardian_id === id)
}

function toggleParent(id) {
  const idx = form.guardian_links.findIndex(l => l.guardian_id === id)
  if (idx === -1) {
    form.guardian_links.push({
      guardian_id: id,
      relationship_type: 'guardian',
      is_primary: form.guardian_links.length === 0,
      is_emergency_contact: false,
      is_pickup_authorized: true,
      is_financial_responsible: false,
    })
  } else {
    form.guardian_links.splice(idx, 1)
  }
}

function isParentSelected(id) {
  return form.guardian_links.some(l => l.guardian_id === id)
}

function setPrimaryGuardian(id) {
  form.guardian_links.forEach((link) => {
    link.is_primary = link.guardian_id === id
  })
}

const selectedGuardianDetails = computed(() => {
  return form.guardian_links
    .map((link) => {
      const parent = props.parents.find(p => p.id === link.guardian_id)
      return parent ? { ...link, name: parent.name } : null
    })
    .filter(Boolean)
})

function initCategorySelectionsFromCurrent() {
  const currentId = Number(form.category_id)
  if (!currentId) return

  for (const top of props.categories) {
    if (Number(top.id) === currentId) {
      selectedTopId.value = Number(top.id)
      return
    }

    for (const year of (top.children ?? [])) {
      if (Number(year.id) === currentId) {
        selectedTopId.value = Number(top.id)
        selectedMidId.value = Number(year.id)
        return
      }

      for (const section of (year.children ?? [])) {
        if (Number(section.id) === currentId) {
          selectedTopId.value = Number(top.id)
          selectedMidId.value = Number(year.id)
          selectedSectionId.value = Number(section.id)
          return
        }

        for (const subSection of (section.children ?? [])) {
          if (Number(subSection.id) === currentId) {
            selectedTopId.value = Number(top.id)
            selectedMidId.value = Number(year.id)
            selectedSectionId.value = Number(section.id)
            return
          }
        }
      }
    }
  }
}

initCategorySelectionsFromCurrent()

function submit() {
  form.put(route('admin.students.update', props.student.id))
}
</script>

<template>
  <Head :title="`تعديل - ${student.name}`" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
          <h4 class="mb-0">تعديل بيانات الطالب</h4>
          <div class="d-flex gap-2">
            <Link :href="route('admin.students.show', student.id)" class="btn btn-outline-primary">
              <i class="bi bi-person-badge"></i> ملف الطالب
            </Link>
            <Link :href="route('admin.students.index')" class="btn btn-secondary">
              <i class="bi bi-arrow-left"></i> القائمة
            </Link>
          </div>
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

                <div class="col-md-6">
                  <label class="form-label">رقم النظام</label>
                  <input type="text" class="form-control" :value="student.id" disabled>
                  <div class="form-text">يُستخدم في بحث الكافتيريا (مثل: {{ student.id }})</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">كود الطالب</label>
                  <input type="text" class="form-control" :value="student.student_code || '—'" disabled>
                </div>

                <div class="col-12">
                  <hr class="my-1">
                  <h6 class="text-muted mb-0">البيانات الشخصية التفصيلية</h6>
                </div>

                <div class="col-md-4">
                  <label class="form-label">الاسم الأول</label>
                  <input type="text" class="form-control" v-model="form.first_name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">اسم الأب</label>
                  <input type="text" class="form-control" v-model="form.father_name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">اسم الجد</label>
                  <input type="text" class="form-control" v-model="form.grandfather_name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">الرقم القومي</label>
                  <input type="text" class="form-control" v-model="form.national_id">
                </div>
                <div class="col-md-4">
                  <label class="form-label">تاريخ الميلاد</label>
                  <input type="date" class="form-control" v-model="form.date_of_birth">
                </div>
                <div class="col-md-4">
                  <label class="form-label">النوع</label>
                  <select class="form-select" v-model="form.gender">
                    <option value="">—</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">تاريخ القيد</label>
                  <input type="date" class="form-control" v-model="form.enrollment_date">
                </div>
                <div v-if="student.user_type === 'student'" class="col-md-3">
                  <label class="form-label">الشعبة</label>
                  <select class="form-select" v-model="selectedTopId" @change="onTopChange">
                    <option :value="null">— عربي / إنجليزي —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

                <div v-if="student.user_type === 'student' && selectedTopId" class="col-md-3">
                  <label class="form-label">المرحلة الدراسية</label>
                  <select class="form-select" v-model="selectedMidId" @change="onMidChange">
                    <option :value="null">— اختر المرحلة —</option>
                    <option v-for="y in yearCategories" :key="y.id" :value="y.id">{{ y.name }}</option>
                  </select>
                </div>

                <div v-if="student.user_type === 'student' && selectedMidId && sectionCategories.length > 0" class="col-md-3">
                  <label class="form-label">الفصل الدراسي</label>
                  <select class="form-select" v-model="selectedSectionId" @change="onSectionChange">
                    <option :value="null">— اختر الفصل الدراسي —</option>
                    <option v-for="s in sectionCategories" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>

                <div v-if="student.user_type === 'student' && selectedSectionId && subSectionCategories.length > 0" class="col-md-3">
                  <label class="form-label">الفصل</label>
                  <select
                    class="form-select"
                    :class="{ 'is-invalid': form.errors.category_id }"
                    v-model="form.category_id"
                  >
                    <option value="">— اختر القسم الفرعي —</option>
                    <option v-for="sub in subSectionCategories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                  </select>
                  <div v-if="form.errors.category_id" class="invalid-feedback">
                    {{ form.errors.category_id }}
                  </div>
                </div>

                <div class="col-md-12">
                  <div class="alert alert-info mb-0">
                    <strong>ملاحظة:</strong> تغيير الحالة والترقية والانسحاب تتم من <Link :href="route('admin.students.show', student.id)">ملف الطالب</Link> — إجراءات سريعة.
                  </div>
                </div>
              </div>

              <!-- Parent Picker -->
              <div class="mt-4">
                <div class="card border-0 bg-light rounded-3">
                  <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 rounded-top-3">
                    <i class="bi bi-person-heart text-primary fs-5"></i>
                    <span class="fw-semibold">ربط بولي الأمر</span>
                    <span v-if="form.guardian_links.length > 0" class="badge bg-primary ms-auto">
                      {{ form.guardian_links.length }} مختار
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

                    <div v-if="selectedGuardianDetails.length" class="mt-4 border-top pt-3">
                      <h6 class="mb-3">بيانات العلاقة لأولياء الأمور المختارين</h6>
                      <div
                        v-for="link in selectedGuardianDetails"
                        :key="link.guardian_id"
                        class="card border mb-2"
                      >
                        <div class="card-body py-3">
                          <div class="fw-semibold mb-2">{{ link.name }}</div>
                          <div class="row g-2">
                            <div class="col-md-4">
                              <label class="form-label small">نوع العلاقة</label>
                              <select
                                class="form-select form-select-sm"
                                v-model="guardianLinkFor(link.guardian_id).relationship_type"
                              >
                                <option
                                  v-for="type in relationshipTypeOptions"
                                  :key="type.value"
                                  :value="type.value"
                                >
                                  {{ type.label }}
                                </option>
                              </select>
                            </div>
                            <div class="col-md-8 d-flex flex-wrap gap-3 align-items-end">
                              <label class="form-check small mb-0">
                                <input
                                  type="radio"
                                  class="form-check-input"
                                  name="primary_guardian"
                                  :checked="link.is_primary"
                                  @change="setPrimaryGuardian(link.guardian_id)"
                                >
                                ولي أمر أساسي
                              </label>
                              <label class="form-check small mb-0">
                                <input type="checkbox" class="form-check-input" v-model="guardianLinkFor(link.guardian_id).is_emergency_contact">
                                جهة طوارئ
                              </label>
                              <label class="form-check small mb-0">
                                <input type="checkbox" class="form-check-input" v-model="guardianLinkFor(link.guardian_id).is_pickup_authorized">
                                مخوّل بالاستلام
                              </label>
                              <label class="form-check small mb-0">
                                <input type="checkbox" class="form-check-input" v-model="guardianLinkFor(link.guardian_id).is_financial_responsible">
                                مسؤول مالي
                              </label>
                            </div>
                          </div>
                        </div>
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

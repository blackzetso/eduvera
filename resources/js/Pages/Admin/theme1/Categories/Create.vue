<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, router } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import CategoryOptions from './CategoryOptions.vue'

const props = defineProps({
  categories: Array
})

// كل group: { parent_id, names: ['', ...] }
const groups = ref([{ parent_id: null, names: [''] }])
const processing = ref(false)

function addNameInGroup(gIndex) {
  groups.value[gIndex].names.push('')
}

function removeNameInGroup(gIndex, nIndex) {
  if (groups.value[gIndex].names.length > 1) {
    groups.value[gIndex].names.splice(nIndex, 1)
  }
}

function addGroup() {
  groups.value.push({ parent_id: null, names: [''] })
}

function removeGroup(gIndex) {
  if (groups.value.length > 1) {
    groups.value.splice(gIndex, 1)
  }
}

function saveForm() {
  const categories = []
  for (const group of groups.value) {
    for (const name of group.names) {
      if (!name.trim()) {
        Swal.fire('تنبيه', 'يرجى ملء جميع حقول اسم القسم', 'warning')
        return
      }
      categories.push({ name, parent_id: group.parent_id })
    }
  }

  processing.value = true
  router.post(route('admin.categories.store'), { categories }, {
    onSuccess: () => {
      Swal.fire('تم الحفظ!', 'تم إنشاء الأقسام بنجاح.', 'success')
    },
    onError: () => {
      Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحفظ.', 'error')
    },
    onFinish: () => {
      processing.value = false
    }
  })
}
</script>

<template>
  <Head title="Add Category" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <h4>إضافة قسم جديد</h4>
        <Link :href="route('admin.categories.index')">
          <i class="fas fa-arrow-left"></i> رجوع
        </Link>
        <hr />

        <div class="row g-4">

          <!-- Groups -->
          <div
            v-for="(group, gIndex) in groups"
            :key="gIndex"
            class="col-12 p-3 border rounded"
            style="background: #f9f9f9;"
          >
            <!-- Group header -->
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label mb-0 fw-semibold">أسماء الأقسام</label>
              <button
                v-if="groups.length > 1"
                type="button"
                class="btn btn-sm btn-danger-soft btn-round"
                @click="removeGroup(gIndex)"
                title="حذف المجموعة"
              >
                <i class="bi bi-trash"></i>
              </button>
            </div>

            <!-- Name fields -->
            <div
              v-for="(name, nIndex) in group.names"
              :key="nIndex"
              class="input-group mb-2"
            >
              <input
                class="form-control"
                v-model="group.names[nIndex]"
                type="text"
                placeholder="اكتب اسم القسم"
              />
              <button
                v-if="group.names.length > 1"
                type="button"
                class="btn btn-outline-danger"
                @click="removeNameInGroup(gIndex, nIndex)"
                title="حذف"
              >
                <i class="bi bi-trash"></i>
              </button>
            </div>

            <!-- Add name button — inside this group only -->
            <div class="mb-3">
              <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                @click="addNameInGroup(gIndex)"
              >
                <i class="bi bi-plus-lg me-1"></i> إضافة اسم آخر
              </button>
            </div>

            <!-- Parent selector — shared for all names in this group -->
            <div class="mt-3">
              <label class="form-label">القسم الرئيسي (اختياري)</label>
              <select v-model="group.parent_id" class="form-select">
                <option :value="null">قسم رئيسي</option>
                <CategoryOptions :categories="props.categories" />
              </select>
            </div>
          </div>

          <!-- Add new group button -->
          <div class="col-12 d-flex justify-content-end">
            <button
              type="button"
              class="btn btn-outline-secondary"
              @click="addGroup"
            >
              <i class="bi bi-plus-lg me-1"></i> إضافة مجموعة جديدة
            </button>
          </div>

          <!-- Save -->
          <div class="d-flex justify-content-end">
            <button
              type="button"
              class="btn btn-primary mb-0"
              :disabled="processing"
              @click="saveForm"
            >
              <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
              حفظ الأقسام
            </button>
          </div>

        </div>
      </div>
    </div>
  </AppLayout>
</template>

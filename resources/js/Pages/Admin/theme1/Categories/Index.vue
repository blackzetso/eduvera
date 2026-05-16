<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const page = usePage()

const props = defineProps({
  categories: Object,
  filters: Object
})

// ✅ حذف الكل
function confirmDeleteAll() {
  Swal.fire({
    title: 'حذف جميع الأقسام؟',
    text: 'سيتم حذف جميع الأقسام وأقسامها الفرعية نهائياً ولا يمكن التراجع!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف الكل',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.categories.destroy-all'), {
        onSuccess: () => Swal.fire('تم الحذف!', 'تم حذف جميع الأقسام بنجاح.', 'success'),
        onError:   () => Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف.', 'error')
      })
    }
  })
}

// ✅ حذف قسم
function confirmDelete(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: "لن تتمكن من التراجع عن هذا الإجراء!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.categories.destroy', id), {
        onSuccess: () => {
          Swal.fire('تم الحذف!', 'تم حذف القسم بنجاح.', 'success')
        },
        onError: () => {
          Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف.', 'error')
        }
      })
    }
  })
}

// ✅ تفعيل/تعطيل
function toggleStatus(id) {
  router.patch(route('admin.categories.status', id), {}, {
    onSuccess: () => {
      toast.success("تم تعديل حالة القسم", {
        position: "top-right",
        autoClose: 3000,
      })
    },
    onError: () => {
      toast.error("حدثت مشكلة أثناء التحديث", {
        position: "top-right",
        autoClose: 3000,
      })
    }
  })
}

// ✅ البحث
const search = ref(props.filters?.search ?? '')
watch(search, (value) => {
  if (value) {
    router.get(route('admin.categories.search', value), {}, {
      preserveState: true,
      replace: true,
    })
  } else {
    router.get(route('admin.categories.index'), {}, {
      preserveState: true,
      replace: true,
    })
  }
})

// ✅ نسخ (Duplicate)
const duplicateModal = ref(false)
const duplicateLoading = ref(false)
const duplicateSubmitting = ref(false)
const duplicateOriginalId = ref(null)
const duplicateName = ref('')
const duplicateSubcategories = ref([])

async function openDuplicateModal(id) {
  duplicateLoading.value = true
  duplicateModal.value = true
  duplicateOriginalId.value = id
  duplicateName.value = ''
  duplicateSubcategories.value = []

  try {
    const res = await fetch(route('admin.categories.duplicate-info', id))
    const data = await res.json()
    duplicateName.value = data.name
    duplicateSubcategories.value = (data.children || []).map(c => ({ name: c.name }))
  } catch (e) {
    toast.error('حدث خطأ أثناء تحميل بيانات القسم', { position: 'top-right', autoClose: 3000 })
    duplicateModal.value = false
  } finally {
    duplicateLoading.value = false
  }
}

function closeDuplicateModal() {
  duplicateModal.value = false
}

function addSubcategory() {
  duplicateSubcategories.value.push({ name: '' })
}

function removeSubcategory(index) {
  duplicateSubcategories.value.splice(index, 1)
}

function submitDuplicate() {
  if (!duplicateName.value.trim()) {
    toast.error('يرجى إدخال اسم القسم', { position: 'top-right', autoClose: 3000 })
    return
  }
  duplicateSubmitting.value = true
  router.post(route('admin.categories.duplicate', duplicateOriginalId.value), {
    name: duplicateName.value,
    subcategories: duplicateSubcategories.value,
  }, {
    onSuccess: () => {
      duplicateModal.value = false
      toast.success('تم تكرار القسم بنجاح', { position: 'top-right', autoClose: 3000 })
    },
    onError: () => {
      toast.error('حدثت مشكلة أثناء التكرار', { position: 'top-right', autoClose: 3000 })
    },
    onFinish: () => {
      duplicateSubmitting.value = false
    }
  })
}
</script>

<template>
  <Head title="Categories" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <!-- Title & Actions -->
      <div class="row mb-3">
        <div class="col-3">
          <h1 class="h3 mb-0">Categories</h1>
        </div>
        <div class="col-7">
          <input
            class="form-control"
            v-model="search"
            name="search"
            placeholder="ابحث عن قسم..."
          />
        </div>
        <div class="col-2 text-center d-flex gap-2 justify-content-center">
          <Link :href="route('admin.categories.create')" class="btn btn-success-soft btn-round">
            <i class="bi bi-plus"></i>
          </Link>
          <button class="btn btn-danger-soft btn-round" @click="confirmDeleteAll" title="حذف الكل">
            <i class="bi bi-trash3-fill"></i>
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="card card-body bg-transparent pb-0 border mb-4">
        <div class="table-responsive border-0">
          <table class="table table-dark-gray align-middle p-4 mb-0 table-hover">
            <thead>
              <tr class="text-center">
                <th>#</th>
                <th>Category Name</th>
                <th>Parent Category</th>
                <th>Enable/Disable</th>
                <th>Action</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(category, index) in props.categories.data"
                :key="category.id"
                class="text-center"
              >
                <td>{{ index + 1 }}</td>
                <td>
                  <h6 class="mb-0">{{ category.name }}</h6>
                </td>
                <td>
                  <span v-if="category.parent">{{ category.parent.name }}</span>
                  <span v-else class="text-muted">قسم رئيسي</span>
                </td>
                <td>
                  <div class="form-check form-switch d-flex justify-content-center">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="category.status === 'enable'"
                      @change="toggleStatus(category.id)"
                    />
                  </div>
                </td>
                <td>
                  <Link
                    :href="route('admin.categories.edit', category.id)"
                    class="btn btn-success-soft btn-round me-1"
                  >
                    <i class="bi bi-pencil-square"></i>
                  </Link>
                  <button
                    class="btn btn-info-soft btn-round me-1"
                    @click="openDuplicateModal(category.parent ? category.parent.id : category.id)"
                    title="تكرار القسم"
                  >
                    <i class="bi bi-copy"></i>
                  </button>
                  <button
                    class="btn btn-danger-soft btn-round"
                    @click="confirmDelete(category.id)"
                  >
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>

              <!-- لما مفيش بيانات -->
              <tr v-if="!props.categories.data.length" class="text-center">
                <td colspan="5" class="text-center py-4">
                  <i class="bi bi-inbox text-muted fs-4 d-block mb-2"></i>
                  <span class="text-muted">لا توجد بيانات</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="card-footer bg-transparent pt-0">
          <div class="d-sm-flex justify-content-sm-between align-items-sm-center">
            <p class="mb-0 text-center text-sm-start">
              Showing {{ props.categories.from }} to {{ props.categories.to }} of
              {{ props.categories.total }} entries
            </p>
            <nav class="d-flex justify-content-center mb-0" aria-label="navigation">
              <ul
                class="pagination pagination-sm pagination-primary-soft d-inline-block d-md-flex rounded mb-0"
              >
                <li
                  v-for="(link, key) in props.categories.links"
                  :key="key"
                  class="page-item mb-0"
                  :class="{ active: link.active, disabled: !link.url }"
                >
                  <Link v-if="link.url" class="page-link" :href="link.url">
                    <template v-if="link.label.includes('Previous')">
                      <i class="bi bi-chevron-left"></i>
                    </template>
                    <template v-else-if="link.label.includes('Next')">
                      <i class="bi bi-chevron-right"></i>
                    </template>
                    <template v-else>
                      <span v-html="link.label"></span>
                    </template>
                  </Link>

                  <span v-else class="page-link">
                    <template v-if="link.label.includes('Previous')">
                      <i class="bi bi-chevron-left"></i>
                    </template>
                    <template v-else-if="link.label.includes('Next')">
                      <i class="bi bi-chevron-right"></i>
                    </template>
                    <template v-else>
                      <span v-html="link.label"></span>
                    </template>
                  </span>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- ✅ Duplicate Modal -->
    <Teleport to="body">
      <div v-if="duplicateModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5);" @click.self="closeDuplicateModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" dir="rtl">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-copy me-2"></i>تكرار القسم</h5>
              <button type="button" class="btn-close ms-0 me-auto" @click="closeDuplicateModal"></button>
            </div>

            <div class="modal-body">
              <!-- Loading -->
              <div v-if="duplicateLoading" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">جاري التحميل...</p>
              </div>

              <template v-else>
                <!-- اسم القسم الرئيسي -->
                <div class="mb-4">
                  <label class="form-label fw-semibold">اسم القسم الرئيسي</label>
                  <input
                    v-model="duplicateName"
                    type="text"
                    class="form-control"
                    placeholder="اسم القسم..."
                  />
                </div>

                <!-- الأقسام الفرعية -->
                <div v-if="duplicateSubcategories.length > 0">
                  <label class="form-label fw-semibold">الأقسام الفرعية</label>
                  <div
                    v-for="(sub, index) in duplicateSubcategories"
                    :key="index"
                    class="input-group mb-2"
                  >
                    <input
                      v-model="sub.name"
                      type="text"
                      class="form-control"
                      :placeholder="'القسم الفرعي ' + (index + 1)"
                    />
                    <button
                      type="button"
                      class="btn btn-outline-danger"
                      @click="removeSubcategory(index)"
                      title="حذف"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>

                <div v-else class="text-muted mb-3">
                  <i class="bi bi-info-circle me-1"></i> لا توجد أقسام فرعية في هذا القسم.
                </div>

                <!-- إضافة قسم فرعي جديد -->
                <button type="button" class="btn btn-outline-secondary btn-sm mt-1" @click="addSubcategory">
                  <i class="bi bi-plus-circle me-1"></i> إضافة قسم فرعي
                </button>
              </template>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeDuplicateModal">إلغاء</button>
              <button
                type="button"
                class="btn btn-primary"
                :disabled="duplicateSubmitting || duplicateLoading"
                @click="submitDuplicate"
              >
                <span v-if="duplicateSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-copy me-1"></i>
                تكرار
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

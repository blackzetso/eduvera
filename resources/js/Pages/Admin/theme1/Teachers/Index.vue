<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'

const props = defineProps({
  teachers: Object,
  filters: Object
})

// ✅ حذف مدرس
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
      router.delete(route('admin.teachers.destroy', id), {
        onSuccess: () => {
          Swal.fire('تم الحذف!', 'تم حذف المدرس بنجاح.', 'success')
        },
        onError: () => {
          Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف.', 'error')
        }
      })
    }
  })
}

// ✅ البحث
const search = ref(props.filters?.search ?? '')
watch(search, (value) => {
  router.get(route('admin.teachers.index'), { search: value }, {
    preserveState: true,
    replace: true,
  })
})
</script>

<template>
  <Head title="المدرسين" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <!-- Title & Actions -->
      <div class="row mb-3">
        <div class="col-3">
          <h1 class="h3 mb-0">المدرسين</h1>
        </div>
        <div class="col-7">
          <input
            class="form-control"
            v-model="search"
            name="search"
            placeholder="ابحث عن مدرس..."
          />
        </div>
        <div class="col-2 text-center">
          <div class="d-flex justify-content-center gap-2">
            <Link :href="route('admin.teachers.bulk-data')" class="btn btn-info-soft btn-round" title="Bulk Data">
              <i class="bi bi-upload"></i>
            </Link>
            <Link :href="route('admin.teachers.create')" class="btn btn-success-soft btn-round" title="إضافة معلم">
              <i class="bi bi-plus"></i>
            </Link>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="card card-body bg-transparent pb-0 border mb-4">
        <div class="table-responsive border-0">
          <table class="table table-dark-gray align-middle p-4 mb-0 table-hover">
            <thead>
              <tr class="text-center">
                <th>#</th>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>الهاتف</th>
                <th>المواد</th>
                <th>الإجراءات</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(teacher, index) in props.teachers.data"
                :key="teacher.id"
                class="text-center"
              >
                <td>{{ index + 1 }}</td>
                <td>
                  <h6 class="mb-0">{{ teacher.name }}</h6>
                </td>
                <td>{{ teacher.email }}</td>
                <td>{{ teacher.phone || '-' }}</td>
                <td>
                  <div v-if="teacher.subjects && teacher.subjects.length > 0">
                    <span
                      v-for="(subject, idx) in teacher.subjects"
                      :key="subject.id"
                      class="badge bg-info me-1"
                    >
                      {{ subject.name }}
                    </span>
                  </div>
                  <span v-else class="text-muted">لا يوجد</span>
                </td>
                <td>
                  <Link
                    :href="route('admin.teachers.edit', teacher.id)"
                    class="btn btn-success-soft btn-round me-1"
                    title="تعديل"
                  >
                    <i class="bi bi-pencil-square"></i>
                  </Link>
                  <button
                    class="btn btn-danger-soft btn-round"
                    @click="confirmDelete(teacher.id)"
                    title="حذف"
                  >
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>

              <!-- لما مفيش بيانات -->
              <tr v-if="!props.teachers.data.length" class="text-center">
                <td colspan="6" class="text-center py-4">
                  <i class="bi bi-person-x text-muted fs-4 d-block mb-2"></i>
                  <span class="text-muted">لا يوجد مدرسين</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="card-footer bg-transparent pt-0">
          <div class="d-sm-flex justify-content-sm-between align-items-sm-center">
            <p class="mb-0 text-center text-sm-start">
              Showing {{ props.teachers.from }} to {{ props.teachers.to }} of
              {{ props.teachers.total }} entries
            </p>
            <nav class="d-flex justify-content-center mb-0" aria-label="navigation">
              <ul class="pagination pagination-sm pagination-primary-soft d-inline-block d-md-flex rounded mb-0">
                <li
                  v-for="(link, key) in props.teachers.links"
                  :key="key"
                  class="page-item mb-0"
                  :class="{ active: link.active, disabled: !link.url }"
                >
                  <Link v-if="link.url" class="page-link" :href="link.url">
                    <span v-html="link.label"></span>
                  </Link>
                  <span v-else class="page-link" v-html="link.label"></span>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

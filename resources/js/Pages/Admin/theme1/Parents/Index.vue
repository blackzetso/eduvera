<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
  parents: Array,
  filters: Object,
})

const search = ref(props.filters?.search || '')

const filteredParents = computed(() => {
  if (!search.value) return props.parents || []
  const q = search.value.toLowerCase()
  return (props.parents || []).filter(p =>
    p.name?.toLowerCase().includes(q) ||
    p.email?.toLowerCase().includes(q) ||
    p.phone?.toLowerCase().includes(q) ||
    p.national_id?.toLowerCase().includes(q)
  )
})

function formatDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('ar-EG')
}

function deleteParent(id, name) {
  Swal.fire({
    title: 'حذف ولي الأمر',
    text: `هل أنت متأكد من حذف ولي الأمر "${name}"؟`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.parents.destroy', id), {
        onSuccess: () => toast.success('تم حذف ولي الأمر بنجاح'),
      })
    }
  })
}
</script>

<template>
  <Head title="أولياء الأمور" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4>أولياء الأمور</h4>
          <div class="d-flex gap-2">
            <Link :href="route('admin.parents.bulk-data')" class="btn btn-outline-success">
              <i class="bi bi-file-earmark-spreadsheet me-1"></i> استيراد CSV
            </Link>
            <Link :href="route('admin.parents.create')" class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> إضافة جديد
            </Link>
          </div>
        </div>

        <!-- Search -->
        <div class="card mb-4">
          <div class="card-body">
            <input
              type="text"
              class="form-control"
              placeholder="البحث بالاسم أو البريد أو الهاتف أو الرقم القومي..."
              v-model="search"
            >
          </div>
        </div>

        <!-- Table -->
        <div class="card">
          <div class="card-header">
            <h6 class="mb-0">
              إجمالي أولياء الأمور: <span class="badge bg-primary">{{ filteredParents.length }}</span>
            </h6>
          </div>
          <div class="card-body">
            <div v-if="filteredParents.length > 0" class="table-responsive">
              <table class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>الاسم</th>
                    <th>البريد الإلكتروني</th>
                    <th>رقم الهاتف</th>
                    <th>الوظيفة</th>
                    <th>الرقم القومي</th>
                    <th>الطلاب المرتبطون</th>
                    <th>تاريخ الإضافة</th>
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="parent in filteredParents" :key="parent.id">
                    <td><strong>{{ parent.name }}</strong></td>
                    <td>{{ parent.email }}</td>
                    <td>{{ parent.phone || '-' }}</td>
                    <td>{{ parent.job_title || '-' }}</td>
                    <td>{{ parent.national_id || '-' }}</td>
                    <td>
                      <span
                        v-if="parent.students && parent.students.length > 0"
                        class="d-flex flex-wrap gap-1"
                      >
                        <span
                          v-for="s in parent.students"
                          :key="s.id"
                          class="badge bg-info text-dark"
                        >{{ s.name }}</span>
                      </span>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td>{{ formatDate(parent.created_at) }}</td>
                    <td>
                      <Link
                        :href="route('admin.parents.edit', parent.id)"
                        class="btn btn-sm btn-warning me-2"
                      >
                        <i class="bi bi-pencil"></i>
                      </Link>
                      <button
                        class="btn btn-sm btn-danger"
                        @click="deleteParent(parent.id, parent.name)"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="alert alert-info mb-0">
              لا توجد نتائج مطابقة
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

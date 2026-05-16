<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
  assignments: Array,
  teacher: Object,
  timetable: Object
})

function formatTime(time) {
  return time ? time.substring(0, 5) : ''
}

function removeAssignment(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: "سيتم إزالة تعيين المدرس من هذه الحصة",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.timetable.assignments.remove', id), {
        onSuccess: () => {
          toast.success('تم إزالة التعيين بنجاح')
          router.reload()
        }
      })
    }
  })
}
</script>

<template>
  <Head :title="`جدول ${teacher?.name}`" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4>جدول المدرس: {{ teacher?.name }}</h4>
          </div>
          <Link :href="route('admin.timetable.show')" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> العودة للجدول
          </Link>
        </div>

        <div class="card">
          <div class="card-body">
            <div v-if="assignments && assignments.length > 0">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>اليوم</th>
                      <th>الوقت</th>
                      <th>المرحلة</th>
                      <th>المادة</th>
                      <th>النوع</th>
                      <th>تاريخ التعيين</th>
                      <th>الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="assignment in assignments" :key="assignment.id">
                      <td>{{ assignment.period?.day?.day_name }}</td>
                      <td>
                        {{ formatTime(assignment.period?.time_from) }} - 
                        {{ formatTime(assignment.period?.time_to) }}
                      </td>
                      <td>{{ assignment.period?.category?.name }}</td>
                      <td>{{ assignment.subject?.name }}</td>
                      <td>
                        <span :class="assignment.type === 'backup' ? 'badge bg-warning' : 'badge bg-success'">
                          {{ assignment.type === 'backup' ? 'احتياطية' : 'أساسية' }}
                        </span>
                      </td>
                      <td>{{ new Date(assignment.created_at).toLocaleDateString('ar-EG') }}</td>
                      <td>
                        <button 
                          class="btn btn-sm btn-danger"
                          @click="removeAssignment(assignment.id)"
                        >
                          <i class="bi bi-trash"></i> إزالة
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="alert alert-info">
              لا توجد حصص معينة لهذا المدرس
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.table th {
  background-color: #e9ecef;
  font-weight: bold;
}
</style>

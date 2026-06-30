<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
    lessons: Object,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')

watch(search, (val) => {
    router.get(route('teacher.lessons.index'), { search: val }, { preserveState: true, replace: true })
})

function confirmDelete(id) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'لن تتمكن من التراجع عن هذا الإجراء!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('teacher.lessons.destroy', id), {
                onSuccess: () => toast.success('تم حذف الدرس بنجاح'),
                onError:   () => toast.error('حدثت مشكلة أثناء الحذف'),
            })
        }
    })
}
</script>

<template>
    <Head title="دروسي" />
    <AppLayout>
        <div class="page-content-wrapper border">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>دروسي</h3>
                <Link :href="route('teacher.lessons.create')" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> إنشاء درس جديد
                </Link>
            </div>

            <!-- Search -->
            <div class="mb-3">
                <input v-model="search" type="text" class="form-control w-50" placeholder="بحث باسم الدرس...">
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>اسم الدرس</th>
                                    <th>الفصل</th>
                                    <th>تاريخ النشر</th>
                                    <th>تاريخ الانتهاء</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="lesson in lessons.data" :key="lesson.id">
                                    <td>{{ lesson.id }}</td>
                                    <td class="fw-semibold">{{ lesson.name }}</td>
                                    <td class="text-muted small">
                                        <span v-if="lesson.classes && lesson.classes.length">
                                            {{ lesson.classes.map(c => c.name).join('، ') }}
                                        </span>
                                        <span v-else-if="lesson.category">{{ lesson.category.name }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="small">{{ lesson.publish_date ?? '—' }}</td>
                                    <td class="small">{{ lesson.expire_date ?? '—' }}</td>
                                    <td>
                                        <span class="badge" :class="lesson.status === 'enable' ? 'bg-success' : 'bg-secondary'">
                                            {{ lesson.status === 'enable' ? 'مفعّل' : 'معطّل' }}
                                        </span>
                                    </td>
                                    <td>
                                        <Link :href="route('teacher.lessons.edit', lesson.id)" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="bi bi-pencil"></i>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="lessons.data.length === 0">
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
                                        لا توجد دروس بعد
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="lessons.last_page > 1" class="d-flex justify-content-center mt-3 gap-1">
                <button
                    v-for="page in lessons.last_page" :key="page"
                    class="btn btn-sm"
                    :class="page === lessons.current_page ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="router.get(route('teacher.lessons.index'), { page, search })"
                >{{ page }}</button>
            </div>

        </div>
    </AppLayout>
</template>

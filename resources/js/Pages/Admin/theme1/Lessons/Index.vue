<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'


const props = defineProps({
  lessons: Object,
  filters: Object,
  stats: Object
})

// ✅ حذف الدرس
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
      router.delete(route('admin.lessons.destroy', id), {
        onSuccess: () => {
          Swal.fire('تم الحذف!', 'تم حذف الدرس بنجاح.', 'success')
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
  router.patch(route('admin.lessons.status', id), {}, {
    onSuccess: () => {
      toast.success("تم تعديل حالة الدرس", {
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
    router.get(route('admin.lessons.search', value), {}, {
      preserveState: true,
      replace: true,
    })
  } else {
    router.get(route('admin.lessons.index'), {}, {
      preserveState: true,
      replace: true,
    })
  }
})
</script>

<template>
  <Head title="Lessons" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <!-- Title & Actions -->
      <!-- Title -->
		<div class="row mb-3">
			<div class="col-12 d-sm-flex justify-content-between align-items-center">
				<h1 class="h3 mb-2 mb-sm-0">Lessons</h1>
				<Link :href="route('admin.lessons.create')" class="btn btn-sm btn-primary mb-0">
                    Create lesson
                    <i class="bi bi-plus"></i>
                </Link>
			</div>
		</div>


      <!-- Course boxes START -->
    <div class="row g-4 mb-4">
        <!-- Course item -->
        <div class="col-sm-6 col-lg-4">
            <div class="text-center p-4 bg-primary bg-opacity-10 border border-primary rounded-3">
                <h6>Total Lessons</h6>
                <h2 class="mb-0 fs-1 text-primary">{{ stats.total }}</h2>
            </div>
        </div>

        <!-- Course item -->
        <div class="col-sm-6 col-lg-4">
            <div class="text-center p-4 bg-success bg-opacity-10 border border-success rounded-3">
                <h6>Activated Lessons</h6>
                <h2 class="mb-0 fs-1 text-success">{{ stats.activated  }}</h2>
            </div>
        </div>

        <!-- Course item -->
        <div class="col-sm-6 col-lg-4">
            <div class="text-center p-4  bg-warning bg-opacity-15 border border-warning rounded-3">
                <h6>disabled Lessons</h6>
                <h2 class="mb-0 fs-1 text-warning">{{ stats.disabled  }}</h2>
            </div>
        </div>
    </div>
    <!-- Course boxes END -->

    <!-- Card START -->
		<div class="card bg-transparent border">

			<!-- Card header START -->
			<div class="card-header bg-light border-bottom">
				<!-- Search and select START -->
				<div class="row g-3 align-items-center justify-content-between">
					<!-- Search bar -->
					<div class="col-md-8">
						<form class="rounded position-relative">
							<input class="form-control bg-body" v-model="search" type="search" placeholder="Search" aria-label="Search">
							<button class="bg-transparent p-2 position-absolute top-50 end-0 translate-middle-y border-0 text-primary-hover text-reset" type="submit">
								<i class="fas fa-search fs-6 "></i>
							</button>
						</form>
					</div>

					<!-- Select option -->
					<div class="col-md-3">
						<!-- Short by filter -->
						<form>
							<select class="form-select js-choice border-0 z-index-9" aria-label=".form-select-sm">
								<option value="">Sort by</option>
								<option>Newest</option>
								<option>Oldest</option>
								<option>Accepted</option>
								<option>Rejected</option>
							</select>
						</form>
					</div>
				</div>
				<!-- Search and select END -->
			</div>
			<!-- Card header END -->

			<!-- Card body START -->
			<div class="card-body">
				<!-- Course table START -->
                <div class="table-responsive border-0">
                <table class="table table-dark-gray align-middle p-4 mb-0 table-hover">
                    <thead>
                    <tr class="text-center">
                        <th>#</th>
                        <th>Lesson Name</th>
                        <th>Teacher Name</th>
                        <th>Category</th>
                        <th>Total Enrollments</th>
                        <th>Enable/Disable</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr  v-for="(lesson, index) in props.lessons.data" :key="lesson.id" class="text-center" >
                        <td>{{ index + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center position-relative">
                                <!-- Image -->
                                <div class="w-60px">
                                    <img :src="`/storage/${lesson.image}`" alt="">
                                </div>
                                <!-- Title -->
                                <h6 class="table-responsive-title mb-0 ms-2">
                                    <a href="javascript:void(0)" class="stretched-link">{{ lesson.name }}</a>
                                </h6>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center mb-3">
                                <!-- Avatar -->
                                <div class="avatar avatar-xs flex-shrink-0">
                                    <img class="avatar-img rounded-circle" src="/front/theme1/images/avatar/09.jpg" alt="avatar">
                                </div>
                                <!-- Info -->
                                <div class="ms-2">
                                    <h6 class="mb-0 fw-light">Mohamed Elshafie</h6>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span v-if="lesson.category">{{ lesson.category.name }}</span>
                            <span v-else class="text-muted">بدون قسم</span>
                        </td>
                        <td> <span class="badge text-bg-primary">365</span> </td>
                        <td>
                        <div class="form-check form-switch d-flex justify-content-center">
                            <input
                            class="form-check-input"
                            type="checkbox"
                            :checked="lesson.status === 'enable'"
                            @change="toggleStatus(lesson.id)"
                            />
                        </div>
                        </td>
                        <td>
                        <Link
                            :href="route('admin.lessons.edit', lesson.id)"
                            class="btn btn-success-soft btn-round me-1"
                        >
                            <i class="bi bi-pencil-square"></i>
                        </Link>
                        <button
                            class="btn btn-danger-soft btn-round"
                            @click="confirmDelete(lesson.id)"
                        >
                            <i class="bi bi-trash"></i>
                        </button>
                        </td>
                    </tr>

                    <!-- لما مفيش بيانات -->
                    <tr v-if="!props.lessons.data.length" class="text-center">
                        <td colspan="7" class="text-center py-4">
                        <i class="bi bi-inbox text-muted fs-4 d-block mb-2"></i>
                        <span class="text-muted">لا توجد دروس</span>
                        </td>
                    </tr>
                    </tbody>
                </table>
                </div>

                <!-- Pagination -->
                <div class="card-footer bg-transparent pt-0">
                <div class="d-sm-flex justify-content-sm-between align-items-sm-center">
                    <p class="mb-0 text-center text-sm-start">
                    Showing {{ props.lessons.from }} to {{ props.lessons.to }} of
                    {{ props.lessons.total }} entries
                    </p>
                    <nav class="d-flex justify-content-center mb-0" aria-label="navigation">
                    <ul
                        class="pagination pagination-sm pagination-primary-soft d-inline-block d-md-flex rounded mb-0"
                    >
                        <li
                        v-for="(link, key) in props.lessons.links"
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
    </div>
  </AppLayout>
</template>

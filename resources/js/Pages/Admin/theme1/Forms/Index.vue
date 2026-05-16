<script setup>
    import { onMounted, ref, watch } from 'vue'
    import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
    import { Head } from '@inertiajs/vue3'
    import { Link } from '@inertiajs/vue3'
    import { router } from '@inertiajs/vue3'
    import { route } from 'ziggy-js'
    import { usePage } from '@inertiajs/vue3'
    import Swal from 'sweetalert2'
    import { toast } from 'vue3-toastify'

    const page = usePage()

    const props = defineProps({
        forms: Object,
        filters: Object
    })

    //Start Confirm Delete
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
            router.delete(route('admin.forms.destroy', id), {
                onSuccess: () => {
                    //console.log('delete successfully');
                Swal.fire(
                    'تم الحذف!',
                    'تم حذف النموذج بنجاح.',
                    'success'
                )
                },
                onError: () => {
                Swal.fire(
                    'خطأ!',
                    'حدثت مشكلة أثناء الحذف.',
                    'error'
                )
                }
            })
            }
        })
    }
    //End Confirm Delete
    //Start Toggle Status
    function toggleStatus(id) {
        router.patch(route('admin.forms.status', id), {}, {
            onSuccess: () => {
                toast.success("تم تعديل حالة النموذج", {
                    position: "top-right",
                    autoClose: 3000,
                });
            },
            onError: () => {
                toast.error("حدثت مشكلة أثناء التحديث", {
                    position: "top-right",
                    autoClose: 3000,
                });
            }
        })
    }

    //End Toggle Status
    //Start Search
    const search = ref(props.filters?.search ?? '')
    watch(search, (value) => {
        if (value) {
            router.get(route('admin.forms.search', value), {}, {
                preserveState: true,
                replace: true,
            })
        } else {
            router.get(route('admin.forms.index'), {}, {
                preserveState: true,
                replace: true,
            })
        }
    })
    //End Search
</script>
<template>
    <Head title="Dashboard"/>
    <AppLayout>
        <div class="page-content-wrapper border">

		<!-- Title -->
		<div class="row mb-3">
			<div class="col-3">
				<h1 class="h3 mb-0">Form builder</h1>
			</div>
            <div class="col-7" >
                <input class="form-control"  v-model="search" name="search"  placeholder="search ..." >
            </div>
            <div class="col-2  text-center" >
                <Link :href="route('admin.forms.create')" class="btn btn-success-soft btn-round " >
                    <i class="fbi bi-plus" ></i>
                </Link>
            </div>
		</div>

		<!-- All review table START -->
		<div class="card card-body bg-transparent pb-0 border mb-4">

			<!-- Table START -->
			<div class="table-responsive border-0">
				<table class="table table-dark-gray align-middle p-4 mb-0 table-hover">
					<!-- Table head -->
					<thead>
						<tr class="text-center">
							<th scope="col" class="border-0 rounded-start">#</th>
							<th scope="col" class="border-0"> Form Name</th>
							<th scope="col" class="border-0">Enable/Disable</th>
							<th scope="col" class="border-0 rounded-end">Action</th>
						</tr>
					</thead>

					<!-- Table body START -->
					<tbody>

						<!-- Table row -->

                        <tr v-for="(form, index) in forms.data" :key="form.id" class="text-center">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <h6 class="table-responsive-title mb-0">
                                <a href="#">{{ form.name }}</a>
                                </h6>
                            </td>
                            <td>
                                <div class="form-check form-switch d-flex justify-content-center">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    :checked="form.status === 'enable'"
                                    @change="toggleStatus(form.id)" />
                                </div>
                            </td>
                            <td>
                                <Link :href="route('admin.forms.edit',form.id)" class="btn btn-success-soft btn-round me-1">
                                    <i class="bi bi-pencil-square"></i>
                                </Link>
                                <button @click="confirmDelete(form.id)" class="btn btn-danger-soft btn-round me-1">
                                <i class="bi bi-trash"></i>
                                </button>
                                <Link :href="route('web.form',form.id)" target="_blank" class="btn btn-sm btn-info-soft">View</Link>
                            </td>
                        </tr>

                        <tr v-if="!forms.data.length" class="text-center">
                            <td colspan="4" class="text-center py-4">
                                <i class="bi bi-inbox text-muted fs-4 d-block mb-2"></i>
                                <span class="text-muted">لا توجد بيانات</span>
                            </td>
                        </tr>
					</tbody>
					<!-- Table body END -->
				</table>
			</div>
			<!-- Table END -->
            <!-- Card footer START -->
<div class="card-footer bg-transparent pt-0">
  <!-- Pagination START -->
  <div class="d-sm-flex justify-content-sm-between align-items-sm-center">
    <!-- Content -->
    <p class="mb-0 text-center text-sm-start">
      Showing {{ forms.from }} to {{ forms.to }} of {{ forms.total }} entries
    </p>
    <!-- Pagination -->
    <nav class="d-flex justify-content-center mb-0" aria-label="navigation">
      <ul class="pagination pagination-sm pagination-primary-soft d-inline-block d-md-flex rounded mb-0">
        <li
          v-for="(link, key) in forms.links"
          :key="key"
          class="page-item mb-0"
          :class="{ active: link.active, disabled: !link.url }"
        >
          <Link
            v-if="link.url"
            class="page-link"
            :href="link.url"
          >
            <!-- استبدال Previous / Next -->
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
  <!-- Pagination END -->
</div>
<!-- Card footer END -->


		</div>
		<!-- All review table END -->

	</div>
    </AppLayout>
</template>

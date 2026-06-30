<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'

const props = defineProps({ items: Array, categories: Array })

const form = useForm({
  category: props.categories?.[0] ?? 'Campus Life',
  alt: '',
  is_featured: false,
  is_active: true,
  sort_order: (props.items?.length || 0) + 1,
  image: null,
})

function add() {
  form.post(route('admin.website.gallery.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => form.reset('alt', 'image'),
  })
}

function update(item) {
  router.put(route('admin.website.gallery.update', item.id), {
    category: item.category,
    alt: item.alt,
    is_featured: item.is_featured,
    is_active: item.is_active,
    sort_order: item.sort_order,
  }, { preserveScroll: true })
}

function remove(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.gallery.destroy', id))
}
</script>

<template>
  <Head title="المعرض" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-2">معرض الصور</h1>
          <p class="text-muted small mb-3">صور قسم <strong>Life at School Talent</strong> على الصفحة الرئيسية.</p>

          <div class="table-responsive eduvera-table-wrap">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>معاينة</th>
                <th>التصنيف</th>
                <th>Alt / الوصف</th>
                <th>مميز</th>
                <th>نشط</th>
                <th>ترتيب</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td>
                  <img :src="item.src" alt="" style="height:48px;width:64px;object-fit:cover" class="rounded" />
                </td>
                <td>
                  <select v-model="item.category" class="form-select form-select-sm" @change="update(item)">
                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                  </select>
                </td>
                <td><input v-model="item.alt" class="form-control form-control-sm" @change="update(item)" /></td>
                <td><input v-model="item.is_featured" type="checkbox" @change="update(item)" /></td>
                <td><input v-model="item.is_active" type="checkbox" @change="update(item)" /></td>
                <td style="width:5rem">
                  <input v-model.number="item.sort_order" type="number" min="0" class="form-control form-control-sm" @change="update(item)" />
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-danger-soft" @click="remove(item.id)">حذف</button>
                </td>
              </tr>
              <tr v-if="!items?.length">
                <td colspan="7" class="text-muted text-center">لا توجد صور بعد.</td>
              </tr>
            </tbody>
          </table>
          </div>

          <div class="card card-body">
            <h2 class="h6 mb-3">رفع صورة جديدة</h2>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">التصنيف</label>
                <select v-model="form.category" class="form-select">
                  <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">ترتيب العرض</label>
                <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
              </div>
              <div class="col-md-4 d-flex align-items-end gap-3">
                <div class="form-check">
                  <input v-model="form.is_featured" type="checkbox" class="form-check-input" id="feat" />
                  <label for="feat" class="form-check-label">مميز</label>
                </div>
                <div class="form-check">
                  <input v-model="form.is_active" type="checkbox" class="form-check-input" id="active" />
                  <label for="active" class="form-check-label">نشط</label>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Alt / الوصف</label>
                <input v-model="form.alt" class="form-control" placeholder="Campus courtyard" />
              </div>
              <div class="col-12">
                <WebsiteImageUploadField
                  spec-key="gallery_image"
                  label="صورة المعرض"
                  @update:model-value="form.image = $event"
                />
              </div>
            </div>
            <button type="button" class="btn btn-primary mt-3" :disabled="form.processing || !form.image" @click="add">رفع</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

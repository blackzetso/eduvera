<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  page: Object,
  sections: Array,
  library: Array,
  customSubtypes: Object,
  revisions: Array,
})

const localSections = ref([...(props.sections || [])])

watch(() => props.sections, (v) => {
  localSections.value = [...(v || [])]
}, { deep: true })
const dragIndex = ref(null)
const showAddModal = ref(false)

const addForm = useForm({
  block_type: 'hero',
  admin_name: '',
  custom_subtype: 'text_block',
})

const statusForm = useForm({ status: props.page?.status ?? 'draft' })

const statusLabels = { draft: 'مسودة', published: 'منشور', archived: 'مؤرشف' }

function submitReorder() {
  router.put(route('admin.website.landing-builder.reorder'), {
    order: localSections.value.map((s) => s.uuid),
  }, { preserveScroll: true })
}

function onDragStart(i) {
  dragIndex.value = i
}

function onDragOver(e, i) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) return
  const list = [...localSections.value]
  const [moved] = list.splice(dragIndex.value, 1)
  list.splice(i, 0, moved)
  localSections.value = list
  dragIndex.value = i
}

function onDragEnd() {
  dragIndex.value = null
  submitReorder()
}

function toggleField(section, field) {
  router.put(route('admin.website.landing-builder.sections.update', section.id), {
    [field]: !section[field],
  }, { preserveScroll: true })
}

function addSection() {
  addForm.post(route('admin.website.landing-builder.sections.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showAddModal.value = false
      addForm.reset()
    },
  })
}

function duplicateSection(section) {
  router.post(route('admin.website.landing-builder.sections.duplicate', section.id))
}

function deleteSection(section) {
  if (!confirm(`Remove "${section.admin_name}"?`)) return
  router.delete(route('admin.website.landing-builder.sections.destroy', section.id))
}

function publish() {
  router.post(route('admin.website.landing-builder.publish'), {}, { preserveScroll: true })
}

function saveRevision() {
  router.post(route('admin.website.landing-builder.revisions.store'), { note: 'Manual snapshot' }, { preserveScroll: true })
}

function restoreRevision(id) {
  if (!confirm('Restore this revision? Current layout will be replaced.')) return
  router.post(route('admin.website.landing-builder.revisions.restore', id))
}

function updateStatus() {
  statusForm.put(route('admin.website.landing-builder.status'), { preserveScroll: true })
}

const libraryByCategory = computed(() => {
  const groups = {}
  for (const item of props.library || []) {
    const cat = item.category || 'other'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push(item)
  }
  return groups
})
</script>

<template>
  <Head title="منشئ الصفحة الرئيسية" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
              <h1 class="h4 mb-1">منشئ الصفحة الرئيسية</h1>
              <p class="text-muted small mb-0">اسحب الأقسام لإعادة الترتيب · انسخ أي قسم · جدولة الظهور</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <Link :href="route('admin.website.landing-builder.preview')" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i>معاينة الموقع
              </Link>
              <button type="button" class="btn btn-outline-primary btn-sm" @click="saveRevision">حفظ نسخة</button>
              <button type="button" class="btn btn-primary btn-sm" @click="publish">نشر</button>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
              <div>
                <span class="text-muted small">حالة الصفحة</span>
                <div class="fw-bold">{{ statusLabels[page?.status] || page?.status }}</div>
              </div>
              <select v-model="statusForm.status" class="form-select form-select-sm" style="width: 10rem" @change="updateStatus">
                <option value="draft">مسودة</option>
                <option value="published">منشور</option>
                <option value="archived">مؤرشف</option>
              </select>
              <span v-if="page?.published_at" class="text-muted small">آخر نشر: {{ page.published_at }}</span>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">الأقسام ({{ localSections.length }})</h2>
            <button type="button" class="btn btn-sm btn-success" @click="showAddModal = true">
              <i class="bi bi-plus-lg me-1"></i>إضافة قسم
            </button>
          </div>

          <div class="list-group mb-4">
            <div
              v-for="(section, i) in localSections"
              :key="section.uuid"
              class="list-group-item"
              draggable="true"
              @dragstart="onDragStart(i)"
              @dragover="onDragOver($event, i)"
              @dragend="onDragEnd"
            >
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted cursor-grab" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                <div class="flex-grow-1" style="min-width: 10rem;">
                  <div class="fw-semibold">{{ section.admin_name }}</div>
                  <div class="small text-muted">
                    {{ section.library_label || section.block_type }}
                    <span v-if="section.block_type === 'custom'"> · {{ section.content?.subtype }}</span>
                  </div>
                </div>
                <div class="form-check form-switch mb-0" title="Enabled">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :checked="section.is_enabled"
                    @change="toggleField(section, 'is_enabled')"
                  />
                </div>
                <div class="form-check form-switch mb-0" title="Visible">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :checked="section.is_visible"
                    @change="toggleField(section, 'is_visible')"
                  />
                </div>
                <div class="btn-group btn-group-sm flex-wrap">
                  <Link :href="route('admin.website.landing-builder.edit', section.id)" class="btn btn-outline-primary">إعدادات</Link>
                  <button type="button" class="btn btn-outline-secondary" @click="duplicateSection(section)">نسخ</button>
                  <button type="button" class="btn btn-outline-danger" @click="deleteSection(section)">حذف</button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="revisions?.length" class="card">
            <div class="card-header"><strong>سجل النسخ</strong></div>
            <ul class="list-group list-group-flush">
              <li v-for="rev in revisions" :key="rev.id" class="list-group-item d-flex justify-content-between align-items-center">
                <span>v{{ rev.version }} — {{ rev.note || rev.status }} <span class="text-muted small">{{ rev.created_at }}</span></span>
                <button type="button" class="btn btn-sm btn-outline-warning" @click="restoreRevision(rev.id)">استعادة</button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showAddModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,.4)">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">إضافة قسم من المكتبة</h5>
            <button type="button" class="btn-close" @click="showAddModal = false"></button>
          </div>
          <form @submit.prevent="addSection">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">الاسم الداخلي (للمسؤول)</label>
                <input v-model="addForm.admin_name" type="text" class="form-control" placeholder="مثال: Testimonials (Parents)" />
              </div>
              <div v-for="(items, cat) in libraryByCategory" :key="cat" class="mb-3">
                <div class="small text-muted text-uppercase mb-2">{{ cat }}</div>
                <div class="d-flex flex-wrap gap-2">
                  <button
                    v-for="item in items"
                    :key="item.key"
                    type="button"
                    class="btn btn-sm"
                    :class="addForm.block_type === item.key ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="addForm.block_type = item.key"
                  >
                    {{ item.label }}
                  </button>
                </div>
              </div>
              <div v-if="addForm.block_type === 'custom'" class="mb-3">
                <label class="form-label">نوع القسم المخصص</label>
                <select v-model="addForm.custom_subtype" class="form-select">
                  <option v-for="(label, key) in customSubtypes" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showAddModal = false">إلغاء</button>
              <button type="submit" class="btn btn-primary" :disabled="addForm.processing">إضافة</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

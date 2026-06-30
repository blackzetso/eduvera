<script setup>
import { ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  studentId: { type: Number, required: true },
  guardians: { type: Array, default: () => [] },
  relationshipTypeOptions: { type: Array, default: () => [] },
})

const editing = ref(false)

const form = useForm({
  guardian_links: [],
})

function initLinks() {
  form.guardian_links = props.guardians.map(g => ({
    guardian_id: g.id,
    relationship_type: g.relationship_type || 'guardian',
    is_primary: !!g.is_primary,
    is_emergency_contact: !!g.is_emergency_contact,
    is_pickup_authorized: g.is_pickup_authorized !== false,
    is_financial_responsible: !!g.is_financial_responsible,
    name: g.name,
  }))
}

watch(() => props.guardians, initLinks, { immediate: true })

function setPrimary(id) {
  form.guardian_links.forEach((link) => {
    link.is_primary = link.guardian_id === id
  })
}

function save() {
  form.post(route('admin.students.lifecycle.guardians', props.studentId), {
    preserveScroll: true,
    onSuccess: () => { editing.value = false },
  })
}
</script>

<template>
  <div class="card border mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
      <h6 class="mb-0">إدارة علاقات أولياء الأمور</h6>
      <div class="d-flex gap-2">
        <Link :href="route('admin.parents.index')" class="btn btn-sm btn-outline-primary">إدارة أولياء الأمور</Link>
        <button v-if="guardians.length && !editing" type="button" class="btn btn-sm btn-warning" @click="editing = true">تعديل الأدوار</button>
      </div>
    </div>
    <div class="card-body">
      <p v-if="!guardians.length" class="text-muted mb-0">لا يوجد أولياء أمور مرتبطون. أضف الربط من <Link :href="route('admin.students.edit', studentId)">تعديل الطالب</Link>.</p>

      <div v-else-if="!editing" class="eduvera-table-wrap">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>الاسم</th>
              <th>العلاقة / الأدوار</th>
              <th>الهاتف</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in guardians" :key="g.id">
              <td><Link :href="route('admin.parents.edit', g.id)" class="fw-semibold">{{ g.name }}</Link></td>
              <td>
                <span v-for="label in g.role_labels" :key="label" class="badge bg-light text-dark me-1">{{ label }}</span>
              </td>
              <td>{{ g.phone || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="vstack gap-3">
        <div v-for="link in form.guardian_links" :key="link.guardian_id" class="card border">
          <div class="card-body py-3">
            <div class="fw-semibold mb-2">{{ link.name }}</div>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small">نوع العلاقة</label>
                <select v-model="link.relationship_type" class="form-select form-select-sm">
                  <option v-for="t in relationshipTypeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </div>
              <div class="col-md-8 d-flex flex-wrap gap-3">
                <label class="form-check small mb-0">
                  <input type="radio" class="form-check-input" name="fam_primary" :checked="link.is_primary" @change="setPrimary(link.guardian_id)">
                  ولي أمر أساسي
                </label>
                <label class="form-check small mb-0">
                  <input v-model="link.is_emergency_contact" type="checkbox" class="form-check-input"> جهة طوارئ
                </label>
                <label class="form-check small mb-0">
                  <input v-model="link.is_pickup_authorized" type="checkbox" class="form-check-input"> مخوّل بالاستلام
                </label>
                <label class="form-check small mb-0">
                  <input v-model="link.is_financial_responsible" type="checkbox" class="form-check-input"> مسؤول مالي
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-primary btn-sm" :disabled="form.processing" @click="save">حفظ</button>
          <button type="button" class="btn btn-secondary btn-sm" @click="editing = false; initLinks()">إلغاء</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { useTranslations } from '@/composables/translations'
const { t } = useTranslations()

const form = useForm({
  name: '',
  code: null
})

function saveForm() {
  form.post(route('admin.language.store'), {
    onSuccess: () => {
      Swal.fire('تم الحفظ!', 'تم إنشاء اللغة.', 'success')
    },
    onError: () => {
      Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحفظ.', 'error')
    }
  })
}
</script>

<template>
  <Head :title="t('add_new_language')" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <h4> {{ t('add_new_language') }} </h4>
        <Link :href="route('admin.language.index')">
          <i class="fas fa-arrow-left"></i> {{ t('back') }}
        </Link>
        <hr />

        <div class="row g-4">
          <!-- اسم القسم -->
          <div class="col-12">
            <label class="form-label"> {{ t('language_name') }} </label>
            <input class="form-control" v-model="form.name" type="text" :placeholder="t('type_language_name')" />
            <div v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</div>
          </div>
          <div class="col-12">
            <label class="form-label">{{ t('language_code') }}</label>
            <input class="form-control" v-model="form.code" type="text" :placeholder="t('exalmple_ar_en_fr')" />
            <div v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</div>
          </div>

          <!-- زر الحفظ -->
          <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-primary mb-0" :disabled="form.processing" @click="saveForm" >
              {{ t('save') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

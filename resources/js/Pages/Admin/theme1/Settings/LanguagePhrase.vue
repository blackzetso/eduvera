<script setup>
import { reactive } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import Sidebar from '@/Pages/Admin/theme1/Settings/Partials/Sidebar.vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'
import { useTranslations } from '@/composables/translations'

const { t } = useTranslations()

const props = defineProps({
  language: Object,
  words: Object
})

// نسخة قابلة للتعديل من اللغة
const form = reactive({
  ...props.language
})

// ✅ تحديث اللغة
function updateLanguage() {
  router.put(route('admin.language.update', form.id), {
    type: 'language',
    code : form.code,
    name: form.name
  }, {
    onSuccess: () => {
      toast.success('تم حفظ اللغة بنجاح ✅', {
        autoClose: 3000,
        position: 'top-right'
      })
    },
    onError: () => {
      toast.error('حدثت مشكلة أثناء الحفظ ❌', {
        autoClose: 3000,
        position: 'top-right'
      })
    }
  })
}

// ✅ تحديث كلمة
function saveWord(word) {
  router.put(route('admin.language.update', word.language_id), {
    type: 'word',
    word_id: word.id,
    word: word.word
  }, {
    onSuccess: () => {
      toast.success('تم حفظ الكلمة بنجاح ✅', {
        autoClose: 3000,
        position: 'top-right'
      })
    },
    onError: () => {
      toast.error('حدثت مشكلة أثناء الحفظ ❌', {
        autoClose: 3000,
        position: 'top-right'
      })
    }
  })
}
</script>

<template>
  <Head title="Lessons" />
  <AppLayout>
    <div class="page-content-wrapper border">

      <!-- Title -->
      <div class="row">
        <div class="col-12 mb-3">
          <h1 class="h3 mb-2 mb-sm-0">{{ t('language') }}</h1>
        </div>
      </div>

      <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-xl-3">
          <Sidebar />
        </div>

        <!-- Main -->
        <div class="col-xl-9">
          <div class="tab-content">

            <!-- تعديل اللغة -->
            <form class="row mb-3" @submit.prevent="updateLanguage">
              <div class="col-4">
                <input class="form-control" v-model="form.name" name="language" />
              </div>
              <div class="col-4">
                <input class="form-control" v-model="form.code" name="code" />
              </div>
              <div class="col-4 text-center">
                <button type="submit" class="btn btn-success-soft w-100 ">
                  {{ t('save') }}
                </button>
              </div>
            </form>
            <hr class="mb-4  mt-4" >
            <!-- الكلمات -->
            <div class="row">
              <div class="col-3" v-for="word in props.words" :key="word.id">
                <form @submit.prevent="saveWord(word)">
                  <input type="text" class="form-control" v-model="word.word" />
                  <button type="submit" class="btn btn-success mt-3 w-100">{{ t('save') }}</button>
                </form>
                <hr>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

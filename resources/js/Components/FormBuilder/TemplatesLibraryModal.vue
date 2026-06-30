<script setup>
defineProps({
  show: Boolean,
  templates: Array,
})

const emit = defineEmits(['close', 'select'])
</script>

<template>
  <div v-if="show" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content" dir="rtl">
        <div class="modal-header">
          <h5 class="modal-title">مكتبة القوالب</h5>
          <button type="button" class="btn-close" @click="emit('close')"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div v-for="tpl in templates" :key="tpl.key" class="col-md-6">
              <div class="card h-100 border shadow-sm">
                <div class="card-body">
                  <h6 class="card-title fw-bold">{{ tpl.name_ar }}</h6>
                  <p class="card-text small text-muted mb-2">{{ tpl.name_en }}</p>
                  <p v-if="tpl.description_ar" class="small mb-3">{{ tpl.description_ar }}</p>
                  <button type="button" class="btn btn-primary btn-sm" @click="emit('select', tpl.key)">
                    إنشاء من القالب
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div v-if="!templates?.length" class="text-center text-muted py-4">
            لا توجد قوالب — شغّل <code>php artisan db:seed --class=FormTemplateSeeder</code>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

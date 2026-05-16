<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Pages/Student/Theme1/Layout/App.vue'

const props = defineProps({
  form: Object
})
</script>

<template>
  <Head :title="form.name" />
  <AppLayout>
    <section>
      <div class="container">
        <div class="row g-5 justify-content-between">
          <div class="col-md-8 mx-auto">
            <!-- Title -->
            <h2 class="mb-3">{{ form.name }}</h2>
            <form class="row g-3">
              <div v-for="(input, index) in form.inputs" :key="index" class="col-12">
                <div class="row g-xl-0 align-items-center">
                  <div class="col-lg-4">
                    <h6 class="mb-lg-0">{{ input.name }} <span v-if="input.required" class="text-danger">*</span></h6>
                  </div>
                  <div class="col-lg-8">

                    <!-- Text -->
                    <input v-if="input.type === 'text'" type="text" class="form-control" />

                    <!-- Textarea -->
                    <textarea v-if="input.type === 'textarea'" class="form-control" rows="3"></textarea>

                    <!-- Number -->
                    <input v-if="input.type === 'number'" type="number" class="form-control" />

                    <!-- Email -->
                    <input v-if="input.type === 'email'" type="email" class="form-control" />

                    <!-- Date -->
                    <input v-if="input.type === 'date'" type="date" class="form-control" />

                    <!-- File -->
                    <input v-if="input.type === 'file'" type="file" class="form-control" />

                    <!-- Select -->
                    <select v-if="input.type === 'select'" class="form-select">
                      <option v-for="(opt, i) in input.options" :key="i">{{ opt.value }}</option>
                    </select>

                    <!-- Checkbox -->
                    <div v-if="input.type === 'checkbox'">
                      <div v-for="(opt, i) in input.options" :key="i" class="form-check">
                        <input type="checkbox" class="form-check-input" :id="'check'+index+i" />
                        <label class="form-check-label" :for="'check'+index+i">{{ opt.value }}</label>
                      </div>
                    </div>

                    <!-- Radio -->
                    <div v-if="input.type === 'radio'">
                      <div v-for="(opt, i) in input.options" :key="i" class="form-check">
                        <input type="radio" class="form-check-input" :name="'radio_'+index" :id="'radio'+index+i" />
                        <label class="form-check-label" :for="'radio'+index+i">{{ opt.value }}</label>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
              <div class="col-12 text-sm-end">
                <button class="btn btn-primary mb-0">Submit</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'

const form = useForm({ name: '', inputs: [] })

const form_inputs = reactive({
  name: '',
  type: 'text',
  required: false,
  options: []
})

const inputsList = reactive([])

function addOption() { form_inputs.options.push({ value: '' }) }
function removeOption(index) { form_inputs.options.splice(index, 1) }

function addInputToList() {
  if (!form_inputs.name) {
    Swal.fire('تنبيه', 'برجاء إدخال اسم الحقل', 'warning')
    return
  }
  inputsList.push(JSON.parse(JSON.stringify(form_inputs)))
  form_inputs.name = ''
  form_inputs.type = 'text'
  form_inputs.required = false
  form_inputs.options = []
}

function saveForm() {
  form.inputs = inputsList
  form.post(route('admin.forms.store'), {
    onSuccess: () => { Swal.fire('تم الحفظ!', 'تم إنشاء النموذج مع الحقول.', 'success') },
    onError: () => { Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحفظ.', 'error') }
  })
}
</script>

<template>
  <Head title="Add Form" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <h4>Add New Form</h4>
        <Link :href="route('admin.forms.index')"><i class="fas fa-arrow-left"></i> Back</Link>
        <hr />
        <div class="row g-4">
          <div class="col-12">
            <label class="form-label">Form title</label>
            <input class="form-control" v-model="form.name" type="text" placeholder="Enter form title" />
            <div v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</div>
          </div>
          <hr />
          <div class="col-12">
            <div class="row">
              <!-- العمود الشمال -->
              <div class="col-6" style="border-right:2px dashed rgb(222 222 222)">
                <h5>إضافة حقل جديد</h5>
                <div class="form-group mb-3">
                  <label>Input name</label>
                  <input type="text" v-model="form_inputs.name" class="form-control" placeholder="type input name" />
                </div>
                <div class="form-group mb-3">
                  <label>Input type</label>
                  <select v-model="form_inputs.type" class="form-control">
                    <option value="text">Text</option>
                    <option value="textarea">Text Area</option>
                    <option value="number">Number</option>
                    <option value="email">Email</option>
                    <option value="date">Date</option>
                    <option value="file">File Upload</option>
                    <option value="select">Select</option>
                    <option value="checkbox">Checkbox</option>
                    <option value="radio">Radio</option>
                  </select>
                </div>
                <div class="form-group mb-3">
                  <label>Required</label>
                  <div class="form-check form-switch"><input class="form-check-input" v-model="form_inputs.required" type="checkbox" /></div>
                </div>
                <div v-if="['select','checkbox','radio'].includes(form_inputs.type)" class="form-group mb-3">
                  <div v-for="(option, index) in form_inputs.options" :key="index" class="d-flex mb-2">
                    <input type="text" v-model="option.value" class="form-control me-2" placeholder="Option text" />
                    <button type="button" class="btn btn-danger" @click="removeOption(index)">حذف</button>
                  </div>
                  <button type="button" class="btn btn-primary" @click="addOption">+ أضف خيار</button>
                </div>
                <button class="btn btn-success mt-3" @click="addInputToList">Add Input</button>
              </div>
              <!-- العمود اليمين -->
              <div class="col-6 text-center">
                <h5>الحقول المضافة</h5>
                <form>
                  <div v-for="(input, index) in inputsList" :key="index" class="mb-3">
                    <label class="form-label">{{ input.name }} <span v-if="input.required" class="text-danger">*</span></label>
                    <input v-if="input.type === 'text'" type="text" class="form-control" />
                    <textarea v-if="input.type === 'textarea'" class="form-control" rows="3"></textarea>
                    <input v-if="input.type === 'number'" type="number" class="form-control" />
                    <input v-if="input.type === 'email'" type="email" class="form-control" />
                    <input v-if="input.type === 'date'" type="date" class="form-control" />
                    <input v-if="input.type === 'file'" type="file" class="form-control" />
                    <select v-if="input.type === 'select'" class="form-control"><option v-for="(opt, i) in input.options" :key="i">{{ opt.value }}</option></select>
                    <div v-if="input.type === 'checkbox'"><div v-for="(opt, i) in input.options" :key="i" class="form-check"><input type="checkbox" class="form-check-input" /><label class="form-check-label">{{ opt.value }}</label></div></div>
                    <div v-if="input.type === 'radio'"><div v-for="(opt, i) in input.options" :key="i" class="form-check"><input type="radio" class="form-check-input" :name="'radio_'+index" /><label class="form-check-label">{{ opt.value }}</label></div></div>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-primary mb-0" :disabled="form.processing" @click="saveForm">Save Form</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

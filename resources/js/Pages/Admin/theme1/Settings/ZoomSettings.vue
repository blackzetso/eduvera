<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import Sidebar from '@/Pages/Admin/theme1/Settings/Partials/Sidebar.vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
    settings: Object,
})

const form = ref({
    zoom_account_id:    props.settings.zoom_account_id || '',
    zoom_client_id:     props.settings.zoom_client_id || '',
    zoom_client_secret: '',
    zoom_host_email:    props.settings.zoom_host_email || '',
})

const saving     = ref(false)
const showSecret = ref(false)

function submit() {
    saving.value = true
    router.post(route('admin.settings.zoom.update'), form.value, {
        onSuccess: () => {
            toast.success('تم حفظ إعدادات Zoom بنجاح')
            form.value.zoom_client_secret = ''
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            toast.error(firstError || 'حدث خطأ أثناء الحفظ')
        },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head title="إعدادات Zoom" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <div class="row">
                <div class="col-12 mb-3">
                    <h1 class="h3 mb-2 mb-sm-0">إعدادات النظام</h1>
                </div>
            </div>

            <div class="row g-4">
                <!-- Sidebar -->
                <div class="col-xl-3">
                    <Sidebar />
                </div>

                <!-- Content -->
                <div class="col-xl-9">
                    <div class="card shadow">
                        <div class="card-header border-bottom d-flex align-items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#2D8CFF">
                                <path d="M24 12c0 6.627-5.373 12-12 12S0 18.627 0 12 5.373 0 12 0s12 5.373 12 12zm-6.465-3.197H8.465C7.656 8.803 7 9.459 7 10.268v4.464c0 .809.656 1.465 1.465 1.465h9.07c.809 0 1.465-.656 1.465-1.465v-4.464c0-.809-.656-1.465-1.465-1.465zm-1.272 4.893l-2.13-1.457v.989H8.8v-2.456h5.333v.989l2.13-1.457v3.392z"/>
                            </svg>
                            <h5 class="card-header-title mb-0">إعدادات Zoom</h5>
                        </div>

                        <div class="card-body">
                            <!-- Info Alert -->
                            <div class="alert alert-info d-flex gap-2 mb-4" role="alert">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>متطلبات التكامل:</strong>
                                    <ul class="mb-0 mt-1 ps-3">
                                        <li>إنشاء <strong>Server-to-Server OAuth App</strong> في <a href="https://marketplace.zoom.us/develop/create" target="_blank">Zoom App Marketplace</a></li>
                                        <li>تفعيل صلاحيات: <code>meeting:write:admin</code> و <code>report:read:admin</code></li>
                                        <li>حساب Zoom <strong>Pro أو Business</strong> لمزامنة تقارير الحضور</li>
                                    </ul>
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="row g-4">
                                <!-- Account ID -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Account ID <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.zoom_account_id"
                                        type="text"
                                        class="form-control font-monospace"
                                        placeholder="xxxxxxxxxxxxxxxxxx"
                                        required
                                    />
                                    <div class="form-text">من صفحة الـ App في Zoom Marketplace</div>
                                </div>

                                <!-- Client ID -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Client ID <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.zoom_client_id"
                                        type="text"
                                        class="form-control font-monospace"
                                        placeholder="xxxxxxxxxxxxxxxxxx"
                                        required
                                    />
                                    <div class="form-text">من صفحة الـ App في Zoom Marketplace</div>
                                </div>

                                <!-- Client Secret -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Client Secret</label>
                                    <div class="input-group">
                                        <input
                                            v-model="form.zoom_client_secret"
                                            :type="showSecret ? 'text' : 'password'"
                                            class="form-control font-monospace"
                                            :placeholder="settings.zoom_client_secret_masked || 'أدخل السر الجديد لتغييره'"
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="showSecret = !showSecret"
                                        >
                                            <i :class="showSecret ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <span v-if="settings.zoom_client_secret_masked">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            يوجد سر محفوظ. اتركه فارغاً للإبقاء على القيمة الحالية.
                                        </span>
                                        <span v-else>Client Secret من Zoom App Marketplace</span>
                                    </div>
                                </div>

                                <!-- Host Email -->
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">
                                        Host Email <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.zoom_host_email"
                                        type="email"
                                        class="form-control"
                                        placeholder="host@yourorganization.com"
                                        required
                                    />
                                    <div class="form-text">بريد حساب Zoom الذي ستُنشأ الاجتماعات تحته (يجب أن يكون Pro/Business)</div>
                                </div>

                                <!-- Status indicator -->
                                <div class="col-md-4 d-flex align-items-end">
                                    <div
                                        class="p-3 rounded-3 w-100"
                                        :class="(settings.zoom_account_id && settings.zoom_client_id && settings.zoom_host_email) ? 'bg-success bg-opacity-10 border border-success' : 'bg-warning bg-opacity-10 border border-warning'"
                                    >
                                        <div class="d-flex align-items-center gap-2">
                                            <i
                                                class="fs-5"
                                                :class="(settings.zoom_account_id && settings.zoom_client_id && settings.zoom_host_email) ? 'bi bi-check-circle-fill text-success' : 'bi bi-exclamation-circle-fill text-warning'"
                                            ></i>
                                            <span class="small fw-semibold">
                                                {{ (settings.zoom_account_id && settings.zoom_client_id && settings.zoom_host_email) ? 'Zoom مُعدَّد' : 'يحتاج إعداد' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary" :disabled="saving">
                                        <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                                        <i v-else class="bi bi-save me-2"></i>
                                        حفظ الإعدادات
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

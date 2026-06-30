<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import Sidebar from '@/Pages/Admin/theme1/Settings/Partials/Sidebar.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
    settings: Object,
})

const form = ref({
    teams_tenant_id: props.settings.teams_tenant_id || '',
    teams_client_id: props.settings.teams_client_id || '',
    teams_client_secret: '',
    teams_service_account_email: props.settings.teams_service_account_email || '',
})

const saving = ref(false)
const showSecret = ref(false)

const page = usePage()

function submit() {
    saving.value = true
    router.post(route('admin.settings.teams.update'), form.value, {
        onSuccess: () => {
            toast.success('تم حفظ إعدادات Microsoft Teams بنجاح')
            form.value.teams_client_secret = ''
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            toast.error(firstError || 'حدث خطأ أثناء الحفظ')
        },
        onFinish: () => {
            saving.value = false
        },
    })
}
</script>

<template>
    <Head title="إعدادات Microsoft Teams" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <div class="row">
                <div class="col-12 mb-3">
                    <h1 class="h3 mb-2 mb-sm-0">إعدادات النظام</h1>
                </div>
            </div>

            <div class="row g-4">
                <!-- Sidebar -->
                <Sidebar />

                <!-- Content -->
                <div class="col-xl-9">
                    <div class="card shadow">
                        <div class="card-header border-bottom d-flex align-items-center gap-2">
                            <i class="bi bi-microsoft-teams fs-4 text-primary"></i>
                            <h5 class="card-header-title mb-0">إعدادات Microsoft Teams</h5>
                        </div>

                        <div class="card-body">
                            <!-- Info Alert -->
                            <div class="alert alert-info d-flex gap-2 mb-4" role="alert">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>متطلبات التكامل:</strong>
                                    <ul class="mb-0 mt-1 ps-3">
                                        <li>تسجيل تطبيق في <a href="https://portal.azure.com" target="_blank">Azure Active Directory</a></li>
                                        <li>تفعيل صلاحيات التطبيق: <code>OnlineMeetings.ReadWrite.All</code> و <code>OnlineMeetingArtifact.Read.All</code></li>
                                        <li>حساب Microsoft 365 (Service Account) لإنشاء الاجتماعات تحته</li>
                                    </ul>
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="row g-4">
                                <!-- Tenant ID -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Tenant ID <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.teams_tenant_id"
                                        type="text"
                                        class="form-control font-monospace"
                                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                        required
                                    />
                                    <div class="form-text">معرف المستأجر (Directory ID) من Azure AD</div>
                                </div>

                                <!-- Client ID -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Client ID (Application ID) <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.teams_client_id"
                                        type="text"
                                        class="form-control font-monospace"
                                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                        required
                                    />
                                    <div class="form-text">معرف التطبيق من Azure AD App Registration</div>
                                </div>

                                <!-- Client Secret -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Client Secret</label>
                                    <div class="input-group">
                                        <input
                                            v-model="form.teams_client_secret"
                                            :type="showSecret ? 'text' : 'password'"
                                            class="form-control font-monospace"
                                            :placeholder="settings.teams_client_secret_masked || 'أدخل السر الجديد لتغييره'"
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
                                        <span v-if="settings.teams_client_secret_masked">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            يوجد سر محفوظ. اتركه فارغاً للإبقاء على القيمة الحالية.
                                        </span>
                                        <span v-else>مفتاح سري للتطبيق من Azure AD App Registration</span>
                                    </div>
                                </div>

                                <!-- Service Account Email -->
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">
                                        Service Account Email <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.teams_service_account_email"
                                        type="email"
                                        class="form-control"
                                        placeholder="admin@yourorg.onmicrosoft.com"
                                        required
                                    />
                                    <div class="form-text">بريد الحساب الذي ستُنشأ الاجتماعات تحته (يجب أن يكون في نفس الـ Tenant)</div>
                                </div>

                                <!-- Status indicator -->
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="p-3 rounded-3 w-100" :class="(settings.teams_tenant_id && settings.teams_client_id && settings.teams_service_account_email) ? 'bg-success bg-opacity-10 border border-success' : 'bg-warning bg-opacity-10 border border-warning'">
                                        <div class="d-flex align-items-center gap-2">
                                            <i
                                                class="fs-5"
                                                :class="(settings.teams_tenant_id && settings.teams_client_id && settings.teams_service_account_email) ? 'bi bi-check-circle-fill text-success' : 'bi bi-exclamation-circle-fill text-warning'"
                                            ></i>
                                            <span class="small fw-semibold">
                                                {{ (settings.teams_tenant_id && settings.teams_client_id && settings.teams_service_account_email) ? 'Teams مُعدَّد' : 'يحتاج إعداد' }}
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

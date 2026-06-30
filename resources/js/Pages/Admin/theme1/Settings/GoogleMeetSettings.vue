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
    google_meet_client_email:      props.settings.google_meet_client_email      || '',
    google_meet_private_key:       '',
    google_meet_impersonate_email: props.settings.google_meet_impersonate_email || '',
})

const saving      = ref(false)
const showKey     = ref(false)

const isConfigured = props.settings.google_meet_client_email
    && props.settings.google_meet_private_key_masked
    && props.settings.google_meet_impersonate_email

function submit() {
    saving.value = true
    router.post(route('admin.settings.google-meet.update'), form.value, {
        onSuccess: () => {
            toast.success('تم حفظ إعدادات Google Meet بنجاح')
            form.value.google_meet_private_key = ''
        },
        onError: (errors) => {
            toast.error(Object.values(errors)[0] || 'حدث خطأ أثناء الحفظ')
        },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head title="إعدادات Google Meet" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <div class="row">
                <div class="col-12 mb-3">
                    <h1 class="h3 mb-2 mb-sm-0">إعدادات النظام</h1>
                </div>
            </div>

            <div class="row g-4">
                <Sidebar />

                <div class="col-xl-9">
                    <div class="card shadow">
                        <div class="card-header border-bottom d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;background:#1A73E8;">
                                <i class="bi bi-camera-video text-white small"></i>
                            </div>
                            <h5 class="card-header-title mb-0">إعدادات Google Meet</h5>
                        </div>

                        <div class="card-body">
                            <!-- Info Alert -->
                            <div class="alert alert-info d-flex gap-2 mb-4">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>متطلبات التكامل:</strong>
                                    <ol class="mb-0 mt-1 ps-3">
                                        <li>
                                            إنشاء <strong>مشروع</strong> في
                                            <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a>
                                        </li>
                                        <li>تفعيل <strong>Google Meet API</strong> في المشروع</li>
                                        <li>
                                            إنشاء <strong>Service Account</strong> وتحميل ملف JSON المفاتيح
                                        </li>
                                        <li>
                                            في <a href="https://admin.google.com" target="_blank">Google Workspace Admin</a>:
                                            تفعيل <strong>Domain-wide Delegation</strong> للـ Service Account
                                            بالنطاق:
                                            <code>https://www.googleapis.com/auth/meetings.space.created</code>
                                        </li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Warning: Workspace required -->
                            <div class="alert alert-warning d-flex gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>ملاحظة هامة:</strong>
                                    Google Meet API يتطلب <strong>Google Workspace</strong> (G Suite) ولا يعمل مع حسابات Gmail العادية.
                                    إذا كنت تستخدم Gmail عادي، اختر Zoom أو LiveKit بدلاً من ذلك.
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="row g-4">
                                <!-- Service Account Email -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        Service Account Email <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.google_meet_client_email"
                                        type="email"
                                        class="form-control font-monospace"
                                        placeholder="my-service-account@my-project.iam.gserviceaccount.com"
                                        required
                                    />
                                    <div class="form-text">بريد Service Account من Google Cloud Console</div>
                                </div>

                                <!-- Private Key -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Private Key (RSA)</label>
                                    <div class="position-relative">
                                        <textarea
                                            v-model="form.google_meet_private_key"
                                            class="form-control font-monospace"
                                            :type="showKey ? 'text' : 'password'"
                                            rows="5"
                                            :placeholder="settings.google_meet_private_key_masked || '-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBg...\n-----END PRIVATE KEY-----'"
                                            style="font-size:11px; resize:vertical;"
                                        ></textarea>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-1"
                                            @click="showKey = !showKey"
                                        >
                                            <i :class="showKey ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <span v-if="settings.google_meet_private_key_masked">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            يوجد مفتاح محفوظ. اتركه فارغاً للإبقاء على القيمة الحالية.
                                        </span>
                                        <span v-else>
                                            الصق المفتاح الخاص كاملاً بما فيه
                                            <code>-----BEGIN PRIVATE KEY-----</code> و
                                            <code>-----END PRIVATE KEY-----</code>
                                        </span>
                                    </div>
                                </div>

                                <!-- Impersonate Email -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        حساب Google Workspace للانتحال <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.google_meet_impersonate_email"
                                        type="email"
                                        class="form-control"
                                        placeholder="admin@your-domain.com"
                                        required
                                    />
                                    <div class="form-text">
                                        بريد حساب Google Workspace موجود في نطاقك (سيُنشأ الاجتماع باسمه).
                                        يجب أن يكون ضمن النطاق المُعدَّل في Domain-wide Delegation.
                                    </div>
                                </div>

                                <!-- Status indicator -->
                                <div class="col-12">
                                    <div
                                        class="p-3 rounded-3"
                                        :class="isConfigured ? 'bg-success bg-opacity-10 border border-success' : 'bg-warning bg-opacity-10 border border-warning'"
                                    >
                                        <div class="d-flex align-items-center gap-2">
                                            <i
                                                class="fs-5"
                                                :class="isConfigured ? 'bi bi-check-circle-fill text-success' : 'bi bi-exclamation-circle-fill text-warning'"
                                            ></i>
                                            <div>
                                                <span class="fw-semibold">
                                                    {{ isConfigured ? 'Google Meet مُعدَّد' : 'Google Meet يحتاج إعداد' }}
                                                </span>
                                                <div v-if="isConfigured" class="small text-muted">
                                                    {{ settings.google_meet_client_email }}
                                                    &nbsp;→&nbsp;
                                                    {{ settings.google_meet_impersonate_email }}
                                                </div>
                                            </div>
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

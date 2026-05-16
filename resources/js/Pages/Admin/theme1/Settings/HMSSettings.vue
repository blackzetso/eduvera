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
    hms_app_access_key: props.settings.hms_app_access_key || '',
    hms_app_secret:     '',
    hms_template_id:    props.settings.hms_template_id || '',
})

const saving     = ref(false)
const showSecret = ref(false)

const isConfigured = props.settings.hms_app_access_key
    && props.settings.hms_app_secret_masked
    && props.settings.hms_template_id

function submit() {
    saving.value = true
    router.post(route('admin.settings.hms.update'), form.value, {
        onSuccess: () => {
            toast.success('تم حفظ إعدادات 100ms بنجاح')
            form.value.hms_app_secret = ''
        },
        onError: (errors) => {
            toast.error(Object.values(errors)[0] || 'حدث خطأ أثناء الحفظ')
        },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head title="إعدادات 100ms" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <div class="row">
                <div class="col-12 mb-3">
                    <h1 class="h3 mb-2 mb-sm-0">إعدادات النظام</h1>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-3">
                    <Sidebar />
                </div>

                <div class="col-xl-9">
                    <div class="card shadow">
                        <div class="card-header border-bottom d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:28px;height:28px;background:#2563EB;font-size:11px;">
                                100
                            </div>
                            <h5 class="card-header-title mb-0">إعدادات 100ms (غرفة بث مدمجة)</h5>
                        </div>

                        <div class="card-body">
                            <!-- Info Alert -->
                            <div class="alert alert-info d-flex gap-2 mb-4">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>خطوات الإعداد:</strong>
                                    <ol class="mb-0 mt-1 ps-3">
                                        <li>سجّل في <a href="https://dashboard.100ms.live" target="_blank">dashboard.100ms.live</a> (مجاناً)</li>
                                        <li>أنشئ <strong>Template</strong> جديد اسمه <code>eduvera-classroom</code></li>
                                        <li>
                                            أضف دورين في Template:
                                            <code>teacher</code> (صلاحيات كاملة)
                                            و <code>student</code> (مشاهدة + audio)
                                        </li>
                                        <li>من <strong>Developer</strong> → خذ App Access Key + App Secret</li>
                                        <li>من <strong>Templates</strong> → انسخ Template ID</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Roles config hint -->
                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <div class="p-3 rounded-3 border" style="background:#f0f7ff;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="bi bi-person-video3 text-primary fs-5"></i>
                                            <span class="fw-semibold">Role: teacher</span>
                                        </div>
                                        <ul class="mb-0 small text-muted ps-3">
                                            <li>نشر audio + video</li>
                                            <li>مشاركة الشاشة</li>
                                            <li>كتم الطلاب</li>
                                            <li>إنهاء الغرفة</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-3 rounded-3 border" style="background:#f0fff4;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="bi bi-people text-success fs-5"></i>
                                            <span class="fw-semibold">Role: student</span>
                                        </div>
                                        <ul class="mb-0 small text-muted ps-3">
                                            <li>نشر audio فقط</li>
                                            <li>مشاهدة الفيديو</li>
                                            <li>رفع اليد</li>
                                            <li>لا يمكنه كتم الآخرين</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="row g-4">
                                <!-- App Access Key -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        App Access Key <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.hms_app_access_key"
                                        type="text"
                                        class="form-control font-monospace"
                                        placeholder="xxxxxxxxxxxxxxxxxxxxxxxx"
                                        required
                                    />
                                    <div class="form-text">من Developer → App Access Key</div>
                                </div>

                                <!-- Template ID -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Template ID <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.hms_template_id"
                                        type="text"
                                        class="form-control font-monospace"
                                        placeholder="xxxxxxxxxxxxxxxxxxxxxxxx"
                                        required
                                    />
                                    <div class="form-text">من Templates → انسخ ID الـ template</div>
                                </div>

                                <!-- App Secret -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">App Secret</label>
                                    <div class="input-group">
                                        <input
                                            v-model="form.hms_app_secret"
                                            :type="showSecret ? 'text' : 'password'"
                                            class="form-control font-monospace"
                                            :placeholder="settings.hms_app_secret_masked || 'أدخل App Secret'"
                                        />
                                        <button type="button" class="btn btn-outline-secondary" @click="showSecret = !showSecret">
                                            <i :class="showSecret ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <span v-if="settings.hms_app_secret_masked">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            سر محفوظ. اتركه فارغاً للإبقاء على القيمة الحالية.
                                        </span>
                                        <span v-else>من Developer → App Secret</span>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="col-12">
                                    <div class="p-3 rounded-3"
                                        :class="isConfigured ? 'bg-success bg-opacity-10 border border-success' : 'bg-warning bg-opacity-10 border border-warning'">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fs-5" :class="isConfigured ? 'bi bi-check-circle-fill text-success' : 'bi bi-exclamation-circle-fill text-warning'"></i>
                                            <div>
                                                <span class="fw-semibold">{{ isConfigured ? '100ms مُعدَّد ويعمل' : '100ms يحتاج إعداد' }}</span>
                                                <div v-if="isConfigured" class="small text-muted">
                                                    Template: {{ settings.hms_template_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
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

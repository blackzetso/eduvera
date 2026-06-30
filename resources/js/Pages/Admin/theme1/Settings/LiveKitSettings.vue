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
    livekit_server_url: props.settings.livekit_server_url || '',
    livekit_api_key:    props.settings.livekit_api_key || '',
    livekit_api_secret: '',
})

const saving     = ref(false)
const showSecret = ref(false)

const isConfigured = props.settings.livekit_server_url
    && props.settings.livekit_api_key
    && props.settings.livekit_api_secret_masked

function submit() {
    saving.value = true
    router.post(route('admin.settings.livekit.update'), form.value, {
        onSuccess: () => {
            toast.success('تم حفظ إعدادات LiveKit بنجاح')
            form.value.livekit_api_secret = ''
        },
        onError: (errors) => {
            toast.error(Object.values(errors)[0] || 'حدث خطأ أثناء الحفظ')
        },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head title="إعدادات LiveKit" />
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
                            <!-- LiveKit icon (green circle) -->
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;background:#1EAA53;">
                                <i class="bi bi-broadcast-pin text-white small"></i>
                            </div>
                            <h5 class="card-header-title mb-0">إعدادات LiveKit</h5>
                        </div>

                        <div class="card-body">
                            <!-- Info Alert -->
                            <div class="alert alert-info d-flex gap-2 mb-4">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>متطلبات التكامل:</strong>
                                    <ul class="mb-0 mt-1 ps-3">
                                        <li>
                                            <a href="https://cloud.livekit.io" target="_blank">LiveKit Cloud</a>
                                            (مجاني للبدء) أو سيرفر LiveKit مستضاف ذاتياً
                                        </li>
                                        <li>API Key و API Secret من لوحة التحكم</li>
                                        <li>المشاركون يجتمعون عبر واجهة <a href="https://meet.livekit.io" target="_blank">meet.livekit.io</a> أو تطبيق مخصص</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- LiveKit Cloud vs Self-hosted tabs hint -->
                            <div class="alert alert-light border mb-4 p-3">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <div class="d-flex gap-2 align-items-start">
                                            <i class="bi bi-cloud-check text-success mt-1 fs-5"></i>
                                            <div>
                                                <div class="fw-semibold small">LiveKit Cloud</div>
                                                <div class="text-muted" style="font-size:12px;">
                                                    URL: <code>https://your-project.livekit.cloud</code>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex gap-2 align-items-start">
                                            <i class="bi bi-server text-primary mt-1 fs-5"></i>
                                            <div>
                                                <div class="fw-semibold small">Self-hosted</div>
                                                <div class="text-muted" style="font-size:12px;">
                                                    URL: <code>https://your-server.com</code>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="row g-4">
                                <!-- Server URL -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        Server URL <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.livekit_server_url"
                                        type="url"
                                        class="form-control font-monospace"
                                        placeholder="https://your-project.livekit.cloud"
                                        required
                                    />
                                    <div class="form-text">رابط سيرفر LiveKit (بدون / في النهاية)</div>
                                </div>

                                <!-- API Key -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        API Key <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.livekit_api_key"
                                        type="text"
                                        class="form-control font-monospace"
                                        placeholder="APIxxxxxxxxxxxxxxxx"
                                        required
                                    />
                                    <div class="form-text">من لوحة تحكم LiveKit Cloud أو config السيرفر</div>
                                </div>

                                <!-- API Secret -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">API Secret</label>
                                    <div class="input-group">
                                        <input
                                            v-model="form.livekit_api_secret"
                                            :type="showSecret ? 'text' : 'password'"
                                            class="form-control font-monospace"
                                            :placeholder="settings.livekit_api_secret_masked || 'أدخل السر الجديد لتغييره'"
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
                                        <span v-if="settings.livekit_api_secret_masked">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            يوجد سر محفوظ. اتركه فارغاً للإبقاء على القيمة الحالية.
                                        </span>
                                        <span v-else>API Secret من لوحة تحكم LiveKit</span>
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
                                                    {{ isConfigured ? 'LiveKit مُعدَّد ويعمل' : 'LiveKit يحتاج إعداد' }}
                                                </span>
                                                <div v-if="isConfigured" class="small text-muted">
                                                    {{ settings.livekit_server_url }}
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

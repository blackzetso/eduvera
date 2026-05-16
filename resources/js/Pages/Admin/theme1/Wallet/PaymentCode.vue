<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    rechargeRequest: Object,
    paymentInfo: Object,
    amountEgp: Number,
})

// No auto-refresh needed - webhook will handle payment updates

function formatCurrency(value) {
    if (!value) return '0.00'
    
    // تحويل لرقم مع منزلتين عشريتين
    return parseFloat(value).toFixed(2)
}

const paymentMethodName = computed(() => {
    switch (props.paymentInfo.type) {
        case 'fawry': return 'فوري'
        case 'meeza': return 'المحافظ الإلكترونية (ميزة)'
        case 'aman': return 'أمان'
        case 'basata': return 'بساطة'
        default: return 'الدفع'
    }
})

const instructions = computed(() => {
    switch (props.paymentInfo.type) {
        case 'fawry':
            return [
                'اذهب لأقرب فرع فوري',
                'أخبر الموظف أنك تريد الدفع عبر فوري',
                'أعطه رقم الكود التالي',
                'ادفع المبلغ المطلوب',
                'سيتم تحديث رصيدك تلقائياً بعد إتمام الدفع'
            ]
        case 'meeza':
            return [
                'افتح تطبيق المحفظة الإلكترونية الخاص بك',
                'اختر خيار المسح الضوئي (Scan QR)',
                'امسح رمز الـ QR المعروض أدناه',
                'أو أدخل رقم المرجع يدوياً',
                'أكمل عملية الدفع في التطبيق'
            ]
        case 'aman':
            return [
                'اذهب لأقرب فرع أمان',
                'أخبر الموظف أنك تريد الدفع عبر أمان',
                'أعطه رقم الكود التالي',
                'ادفع المبلغ المطلوب'
            ]
        case 'basata':
            return [
                'اذهب لأقرب فرع بساطة (مصاري)',
                'أخبر الموظف أنك تريد الدفع عبر بساطة',
                'أعطه رقم الكود التالي',
                'ادفع المبلغ المطلوب'
            ]
        default:
            return []
    }
})
</script>

<template>
<Head :title="`الدفع عبر ${paymentMethodName}`" />
<AppLayout>
    <div class="page-content-wrapper border">
        <div class="row mb-3">
            <div class="col-12 d-sm-flex justify-content-between align-items-center">
                <h1 class="h3 mb-2 mb-sm-0">الدفع عبر {{ paymentMethodName }}</h1>
                <Link :href="route('admin.wallet.index')" class="btn btn-sm btn-secondary-soft mb-0">
                    <i class="bi bi-arrow-left me-1"></i>العودة للمحفظة
                </Link>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Success Alert -->
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-1">تم إنشاء طلب الدفع بنجاح!</h5>
                        <p class="mb-0">يرجى إكمال الدفع باستخدام التفاصيل أدناه</p>
                    </div>
                </div>

                <!-- Payment Amount Card -->
                <div class="card bg-primary bg-opacity-10 border border-primary mb-4">
                    <div class="card-body text-center p-4">
                        <h6 class="mb-2">المبلغ المطلوب</h6>
                        <h2 class="display-5 text-primary mb-0">
                            {{ amountEgp }} جنيه مصري
                        </h2>
                        <small class="text-muted">({{ formatCurrency(rechargeRequest.amount) }} دولار)</small>
                    </div>
                </div>

                <!-- Payment Code Card -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title text-white mb-0">
                            <i class="fas fa-barcode me-2"></i>
                            بيانات الدفع
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Fawry Code -->
                        <div v-if="paymentInfo.type === 'fawry'" class="text-center">
                            <h6 class="mb-3">رقم كود فوري:</h6>
                            <div class="bg-light p-4 rounded-3 mb-3">
                                <h1 class="display-3 fw-bold text-primary mb-0 font-monospace">
                                    {{ paymentInfo.code }}
                                </h1>
                            </div>
                            <div v-if="paymentInfo.expire_date" class="alert alert-warning">
                                <i class="fas fa-clock me-1"></i>
                                <strong>ينتهي في:</strong> {{ paymentInfo.expire_date }}
                            </div>
                        </div>

                        <!-- Meeza QR Code -->
                        <div v-else-if="paymentInfo.type === 'meeza'" class="text-center">
                            <h6 class="mb-3">رمز الـ QR للدفع:</h6>
                            <div v-if="paymentInfo.qr_code" class="bg-light p-4 rounded-3 mb-3">
                                <img 
                                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(paymentInfo.qr_code)}`"
                                    alt="QR Code"
                                    class="img-fluid"
                                    style="max-width: 300px;">
                            </div>
                            <div v-if="paymentInfo.reference" class="mb-3">
                                <h6>رقم المرجع:</h6>
                                <div class="bg-light p-3 rounded">
                                    <h3 class="fw-bold text-primary mb-0 font-monospace">
                                        {{ paymentInfo.reference }}
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <!-- Aman Code -->
                        <div v-else-if="paymentInfo.type === 'aman'" class="text-center">
                            <h6 class="mb-3">رقم كود أمان:</h6>
                            <div class="bg-light p-4 rounded-3 mb-3">
                                <h1 class="display-3 fw-bold text-primary mb-0 font-monospace">
                                    {{ paymentInfo.code }}
                                </h1>
                            </div>
                        </div>

                        <!-- Basata Code -->
                        <div v-else-if="paymentInfo.type === 'basata'" class="text-center">
                            <h6 class="mb-3">رقم كود بساطة (مصاري):</h6>
                            <div class="bg-light p-4 rounded-3 mb-3">
                                <h1 class="display-3 fw-bold text-primary mb-0 font-monospace">
                                    {{ paymentInfo.code }}
                                </h1>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-list-ol me-2"></i>
                            خطوات الدفع
                        </h6>
                    </div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li v-for="(instruction, index) in instructions" :key="index" class="mb-2">
                                {{ instruction }}
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Payment Info Notice -->
                <div class="alert alert-success border-success">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle fa-lg me-3 mt-1"></i>
                        <div>
                            <h6 class="alert-heading mb-2">معلومات هامة:</h6>
                            <ul class="mb-0 ps-3">
                                <li>يمكنك إغلاق هذه الصفحة والعودة لاحقاً</li>
                                <li>سيتم تحديث رصيدك تلقائياً بعد إتمام الدفع (خلال دقائق)</li>
                                <li>يمكنك التحقق من حالة الدفع من صفحة المحفظة في أي وقت</li>
                                <li>احتفظ برقم الكود للرجوع إليه عند الحاجة</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Invoice Info -->
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-6">
                                <small class="text-muted">رقم الطلب</small>
                                <p class="mb-0 fw-bold">#{{ rechargeRequest.id }}</p>
                            </div>
                            <div class="col-md-6" v-if="paymentInfo.invoice_id">
                                <small class="text-muted">رقم الفاتورة</small>
                                <p class="mb-0 fw-bold">#{{ paymentInfo.invoice_id }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AppLayout>
</template>

<style scoped>
.font-monospace {
    font-family: 'Courier New', monospace;
    letter-spacing: 2px;
}
</style>


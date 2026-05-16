<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'

const props = defineProps({
    wallet: Object,
    pricingInfo: Object,
    usdToEgp: Number,
})

const form = useForm({
    amount: 0.25, // TODO: Change back to 50 after testing
    payment_method_id: null,
})

const presetAmounts = [0.25, 0.5, 1, 5, 10, 20] // TODO: Change back to [10, 20, 50, 100, 200, 500] after testing

// Payment methods with correct IDs from Fawaterak API
const paymentMethods = [
    { id: 2, name: 'فيزا - ماستر كارد', nameEn: 'Visa-Mastercard', icon: 'fa-credit-card', color: 'primary' },
    { id: 3, name: 'فوري', nameEn: 'Fawry', icon: 'fa-wallet', color: 'warning' },
    { id: 4, name: 'المحافظ الإلكترونية', nameEn: 'Mobile Wallets', icon: 'fa-mobile-alt', color: 'info' },
    { id: 14, name: 'بساطة', nameEn: 'Basata', icon: 'fa-check-circle', color: 'success' },
    { id: 11, name: 'فاليو', nameEn: 'Valu', icon: 'fa-credit-card', color: 'purple' },
    { id: 30, name: 'سهولة', nameEn: 'Souhoola', icon: 'fa-hand-holding-usd', color: 'teal' },
    { id: 6, name: 'تقسيط البنك الأهلي (12 شهر)', nameEn: 'NBE Installment (12M)', icon: 'fa-landmark', color: 'secondary' },
    { id: 7, name: 'تقسيط البنك الأهلي (18 شهر)', nameEn: 'NBE Installment (18M)', icon: 'fa-landmark', color: 'secondary' },
    { id: 0, name: 'كل الطرق المتاحة', nameEn: 'All Methods', icon: 'fa-th', color: 'dark' },
]

function selectAmount(amount) {
    form.amount = amount
}

function formatCurrency(value) {
    if (!value) return '0.00'
    
    // تحويل لرقم مع منزلتين عشريتين
    return parseFloat(value).toFixed(2)
}

function submitRecharge(paymentMethodId) {
    const amount = parseFloat(form.amount)
    if (!amount || amount < 0.25) {
        Swal.fire('خطأ', 'الحد الأدنى للشحن هو 0.25$', 'error') // TODO: Change back to 10$ after testing
        return
    }

    form.payment_method_id = paymentMethodId
    
    form.post(route('admin.wallet.recharge.process'), {
        preserveScroll: true,
        onError: () => {
            Swal.fire('خطأ!', 'حدث خطأ أثناء إنشاء طلب الشحن', 'error')
        }
    })
}
</script>

<template>
<Head title="Recharge Wallet" />
<AppLayout>
    <div class="page-content-wrapper border">
        <div class="row mb-3">
            <div class="col-12 d-sm-flex justify-content-between align-items-center">
                <h1 class="h3 mb-2 mb-sm-0">شحن المحفظة</h1>
                <Link :href="route('admin.wallet.index')" class="btn btn-sm btn-secondary-soft mb-0">
                    <i class="bi bi-arrow-left me-1"></i>العودة للمحفظة
                </Link>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Current Balance Card -->
                <div class="card bg-primary bg-opacity-10 border border-primary mb-4">
                    <div class="card-body text-center p-4">
                        <h3 class="mb-2">رصيدك الحالي</h3>
                        <h2 class="display-4 text-primary">${{ formatCurrency(wallet.balance) }}</h2>
                    </div>
                </div>

                <!-- Recharge Form -->
                <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-header-title">اختر المبلغ للشحن</h5>
                    </div>
                    <div class="card-body">
                        <!-- Preset Amounts -->
                        <div class="row g-3 mb-4">
                            <div v-for="amount in presetAmounts" :key="amount" class="col-md-4">
                                <button 
                                    @click="selectAmount(amount)"
                                    class="btn w-100"
                                    :class="form.amount === amount ? 'btn-primary' : 'btn-outline-primary'">
                                    ${{ amount }}
                                    <br>
                                    <small>{{ Math.round(amount * usdToEgp) }} جنيه</small>
                                </button>
                            </div>
                        </div>

                        <!-- Custom Amount -->
                        <div class="mb-4">
                            <label class="form-label">أو أدخل مبلغ مخصص (بالدولار)</label>
                            <input 
                                type="number" 
                                v-model="form.amount" 
                                class="form-control form-control-lg" 
                                min="0.25" 
                                step="0.25"
                                placeholder="أدخل المبلغ">
                            <div class="form-text">الحد الأدنى: 0.25$ | الحد الأقصى: 10,000$ (مؤقتاً للاختبار)</div>
                        </div>

                        <!-- Summary -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading">ملخص الشحن</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>المبلغ بالدولار:</span>
                                <strong>${{ form.amount || 0 }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>المبلغ بالجنيه المصري:</span>
                                <strong>{{ Math.round((form.amount || 0) * usdToEgp) }} جنيه</strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>الرصيد بعد الشحن:</span>
                                <strong class="text-success">${{ formatCurrency(parseFloat(wallet.balance) + parseFloat(form.amount || 0)) }}</strong>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="mb-3">
                            <h6 class="mb-3">اختر طريقة الدفع:</h6>
                            <div class="row g-3">
                                <div v-for="method in paymentMethods" :key="method.id" class="col-md-6">
                                    <button 
                                        @click="submitRecharge(method.id)" 
                                        :disabled="form.processing || !form.amount || parseFloat(form.amount) < 0.25"
                                        :class="`btn btn-${method.color} w-100 py-3`">
                                        <i :class="`fas ${method.icon} fa-lg me-2`"></i>
                                        <div class="d-block">
                                            <strong>{{ method.name }}</strong>
                                            <br>
                                            <small>{{ method.nameEn }}</small>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Info -->
                        <div class="alert alert-info mt-3 mb-0">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                سيتم تحويلك لصفحة الدفع الآمنة عبر Fawaterak
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Pricing Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">كيف يتم احتساب التكلفة؟</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-database text-primary me-2"></i>
                                <strong>التخزين:</strong> {{ pricingInfo.storage_markup_egp }} جنيه لكل GB شهرياً
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-network-wired text-primary me-2"></i>
                                <strong>الباندويث:</strong> {{ pricingInfo.bandwidth_markup_egp }} جنيه لكل GB مستهلك
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AppLayout>
</template>


<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import Chart from 'chart.js/auto'

const props = defineProps({
    wallet: Object,
    transactions: Array,
    pendingRecharges: Array,
    pricingInfo: Object,
    needsActivation: Boolean,
    consumptionCharts: Object,
})

const syncForm = useForm({})
const checkingStatus = ref({})
const activeTab = ref('all')
const currentTab = ref('overview')

const storageChartRef = ref(null)
const bandwidthChartRef = ref(null)
let storageChart = null
let bandwidthChart = null

function formatCurrency(value) {
    if (!value) return '0.00'
    
    // تحويل لرقم مع منزلتين عشريتين
    return parseFloat(value).toFixed(2)
}

function initCharts() {
    if (!props.consumptionCharts?.available) return
    
    // Storage Chart
    if (storageChartRef.value && !storageChart) {
        storageChart = new Chart(storageChartRef.value, {
            type: 'line',
            data: {
                labels: props.consumptionCharts.dates || [],
                datasets: [{
                    label: 'Storage (GB)',
                    data: props.consumptionCharts.storageData || [],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'استهلاك التخزين (آخر 30 يوم)' },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        })
    }

    // Bandwidth Chart
    if (bandwidthChartRef.value && !bandwidthChart) {
        bandwidthChart = new Chart(bandwidthChartRef.value, {
            type: 'bar',
            data: {
                labels: props.consumptionCharts.dates || [],
                datasets: [{
                    label: 'Bandwidth (GB)',
                    data: props.consumptionCharts.bandwidthData || [],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'استهلاك الباندويث (آخر 30 يوم)' },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        })
    }
}

watch(currentTab, (newTab) => {
    if (newTab === 'consumption') {
        setTimeout(() => initCharts(), 100)
    }
})

onMounted(() => {
    if (currentTab.value === 'consumption') {
        initCharts()
    }
})

const filteredPendingRecharges = computed(() => {
    if (!props.pendingRecharges || props.pendingRecharges.length === 0) return []
    
    let filtered = props.pendingRecharges
    
    if (activeTab.value !== 'all') {
        const methodMap = {
            'fawry': 3,
            'visa': 2,
            'basata': 14,
            'wallet': 4,
            'other': [6, 7, 11, 30]
        }
        
        if (activeTab.value === 'other') {
            filtered = filtered.filter(req => methodMap.other.includes(req.payment_method_id))
        } else {
            filtered = filtered.filter(req => req.payment_method_id === methodMap[activeTab.value])
        }
    }
    
    // Show only first 6
    return filtered.slice(0, 6)
})

function syncConsumption() {
    Swal.fire({
        title: 'مزامنة الاستهلاك',
        text: 'هل تريد مزامنة بيانات استهلاك التخزين والباندويث؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، مزامنة',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            syncForm.post(route('admin.wallet.syncConsumption'), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('تم!', 'تمت المزامنة بنجاح', 'success')
                },
                onError: () => {
                    Swal.fire('خطأ!', 'فشلت عملية المزامنة', 'error')
                }
            })
        }
    })
}

function checkPaymentStatus(requestId) {
    checkingStatus.value[requestId] = true
    
    const form = useForm({})
    form.post(route('admin.wallet.payment.check-status', requestId), {
        preserveScroll: true,
        onFinish: () => {
            checkingStatus.value[requestId] = false
        }
    })
}

function getPaymentMethodName(methodId) {
    const methods = {
        2: 'Visa/Mastercard',
        3: 'فوري',
        4: 'المحافظ الإلكترونية',
        6: 'تقسيط البنك الأهلي (12 شهر)',
        7: 'تقسيط البنك الأهلي (18 شهر)',
        11: 'فاليو',
        14: 'بساطة',
        30: 'سهولة'
    }
    return methods[methodId] || 'طريقة دفع'
}

function getPaymentCode(request) {
    const paymentData = request.gateway_response?.data?.payment_data
    if (!paymentData) return null
    
    return paymentData.fawryCode || paymentData.masaryCode || paymentData.amanCode || paymentData.meezaReference || null
}

function cancelRequest(requestId) {
    Swal.fire({
        title: 'إلغاء طلب الشحن؟',
        text: 'هل أنت متأكد من إلغاء هذا الطلب؟ لن تتمكن من التراجع عن هذا الإجراء.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'نعم، إلغاء الطلب',
        cancelButtonText: 'تراجع'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = useForm({})
            form.post(route('admin.wallet.payment.cancel', requestId), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('تم الإلغاء!', 'تم إلغاء طلب الشحن بنجاح', 'success')
                }
            })
        }
    })
}
</script>

<template>
<Head title="Storage Wallet" />
<AppLayout>
    <div class="page-content-wrapper border">
        <div class="row mb-3">
            <div class="col-12 d-sm-flex justify-content-between align-items-center">
                <h1 class="h3 mb-2 mb-sm-0">محفظة رصيد مساحة التخزين</h1>
                <Link :href="route('admin.wallet.recharge')" class="btn btn-sm btn-primary-soft mb-0">
                    <i class="bi bi-plus-circle me-1"></i>شحن المحفظة
                </Link>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs nav-bottom-line mb-4">
            <li class="nav-item">
                <button 
                    @click="currentTab = 'overview'" 
                    :class="['nav-link', { active: currentTab === 'overview' }]">
                    <i class="fas fa-home me-1"></i>نظرة عامة
                </button>
            </li>
            <li class="nav-item">
                <button 
                    @click="currentTab = 'consumption'" 
                    :class="['nav-link', { active: currentTab === 'consumption' }]">
                    <i class="fas fa-chart-line me-1"></i>تفاصيل الاستهلاك
                </button>
            </li>
        </ul>

        <!-- Overview Tab -->
        <div v-show="currentTab === 'overview'">

        <!-- Activation Alert -->
        <div v-if="needsActivation" class="alert alert-warning alert-dismissible" role="alert">
            <h4 class="alert-heading">تفعيل المحفظة</h4>
            <p>لم يتم تفعيل محفظة رصيد مساحة التخزين بعد. قم بتفعيلها الآن واحصل على <strong>20$ مجاناً</strong>!</p>
            <Link :href="route('admin.wallet.activate')" method="post" as="button" class="btn btn-sm btn-warning">
                تفعيل المحفظة الآن
            </Link>
        </div>

        <!-- Wallet Balance Card -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xxl-3">
                <div class="card card-body bg-primary bg-opacity-10 border border-primary border-opacity-25 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">${{ formatCurrency(wallet.balance) }}</h4>
                            <span class="h6 fw-light mb-0">الرصيد الحالي</span>
                        </div>
                        <div class="icon-lg rounded-circle bg-primary text-white mb-0">
                            <i class="fas fa-wallet fa-fw"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xxl-3">
                <div class="card card-body bg-success bg-opacity-10 border border-success border-opacity-25 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">${{ formatCurrency(wallet.total_credited) }}</h4>
                            <span class="h6 fw-light mb-0">إجمالي الشحن</span>
                        </div>
                        <div class="icon-lg rounded-circle bg-success text-white mb-0">
                            <i class="fas fa-plus fa-fw"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xxl-3">
                <div class="card card-body bg-danger bg-opacity-10 border border-danger border-opacity-25 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">${{ formatCurrency(wallet.total_debited) }}</h4>
                            <span class="h6 fw-light mb-0">إجمالي الخصم</span>
                        </div>
                        <div class="icon-lg rounded-circle bg-danger text-white mb-0">
                            <i class="fas fa-minus fa-fw"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xxl-3">
                <div class="card card-body bg-info bg-opacity-10 border border-info border-opacity-25 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">{{ wallet.is_activated ? 'مفعلة' : 'غير مفعلة' }}</h6>
                            <span class="h6 fw-light mb-0">حالة المحفظة</span>
                        </div>
                        <div class="icon-lg rounded-circle bg-info text-white mb-0">
                            <i class="fas fa-check-circle fa-fw"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Info Card -->
        <div class="card mb-4">
            <div class="card-header border-bottom">
                <h5 class="card-header-title">معلومات التسعير</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>تخزين (لكل GB):</strong> 1 جنيه</p>
                        <p class="mb-0"><strong>باندويث (لكل GB):</strong> 0.5 جنيه</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>سعر الصرف:</strong> 1$ = {{ pricingInfo.usd_to_egp_rate }} جنيه</p>
                        <button @click="syncConsumption" class="btn btn-sm btn-info-soft" :disabled="syncForm.processing">
                            <i class="fas fa-sync me-1"></i>مزامنة الاستهلاك
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Recharge Requests -->
        <div v-if="pendingRecharges && pendingRecharges.length > 0" class="card mb-4">
            <div class="card-header border-bottom bg-warning bg-opacity-10">
                <h5 class="card-header-title">
                    <i class="fas fa-clock me-2"></i>طلبات شحن معلقة
                    <span class="badge bg-warning ms-2">{{ pendingRecharges.length }}</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    لديك طلبات شحن في انتظار الدفع. أكمل الدفع أو اضغط "تحقق الآن" للتحقق من حالة الدفع.
                </div>
                
                <!-- Filter Tabs -->
                <ul class="nav nav-tabs nav-bottom-line mb-4">
                    <li class="nav-item">
                        <button 
                            @click="activeTab = 'all'" 
                            :class="['nav-link', { active: activeTab === 'all' }]">
                            الكل ({{ pendingRecharges.length }})
                        </button>
                    </li>
                    <li class="nav-item">
                        <button 
                            @click="activeTab = 'fawry'" 
                            :class="['nav-link', { active: activeTab === 'fawry' }]">
                            فوري
                        </button>
                    </li>
                    <li class="nav-item">
                        <button 
                            @click="activeTab = 'visa'" 
                            :class="['nav-link', { active: activeTab === 'visa' }]">
                            Visa/Mastercard
                        </button>
                    </li>
                    <li class="nav-item">
                        <button 
                            @click="activeTab = 'basata'" 
                            :class="['nav-link', { active: activeTab === 'basata' }]">
                            بساطة
                        </button>
                    </li>
                    <li class="nav-item">
                        <button 
                            @click="activeTab = 'wallet'" 
                            :class="['nav-link', { active: activeTab === 'wallet' }]">
                            المحافظ
                        </button>
                    </li>
                    <li class="nav-item">
                        <button 
                            @click="activeTab = 'other'" 
                            :class="['nav-link', { active: activeTab === 'other' }]">
                            أخرى
                        </button>
                    </li>
                </ul>

                <div v-if="filteredPendingRecharges.length === 0" class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">لا توجد طلبات في هذا التصنيف</p>
                </div>

                <div v-else class="row g-3">
                    <div v-for="request in filteredPendingRecharges" :key="request.id" class="col-md-6">
                        <div class="card border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1">طلب #{{ request.id }}</h6>
                                        <small class="text-muted">{{ new Date(request.created_at).toLocaleString('ar-EG') }}</small>
                                    </div>
                                    <span class="badge bg-warning">معلق</span>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">المبلغ:</span>
                                        <strong>${{ formatCurrency(request.amount) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">طريقة الدفع:</span>
                                        <strong>{{ getPaymentMethodName(request.payment_method_id) }}</strong>
                                    </div>
                                    <div v-if="getPaymentCode(request)" class="d-flex justify-content-between">
                                        <span class="text-muted">كود الدفع:</span>
                                        <strong class="text-primary font-monospace">{{ getPaymentCode(request) }}</strong>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <div class="btn-group">
                                        <button 
                                            @click="checkPaymentStatus(request.id)" 
                                            :disabled="checkingStatus[request.id]"
                                            class="btn btn-sm btn-success">
                                            <i class="fas fa-sync me-1" :class="{ 'fa-spin': checkingStatus[request.id] }"></i>
                                            {{ checkingStatus[request.id] ? 'جاري التحقق...' : 'تحقق الآن' }}
                                        </button>
                                        <button 
                                            @click="cancelRequest(request.id)"
                                            class="btn btn-sm btn-danger"
                                            title="إلغاء الطلب">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <Link 
                                        v-if="getPaymentCode(request)"
                                        :href="route('admin.wallet.payment.show-code', request.id)"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>
                                        عرض تفاصيل الدفع
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="pendingRecharges.length > 6" class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        يتم عرض آخر 6 طلبات فقط
                    </small>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-header-title">آخر المعاملات</h5>
            </div>
            <div class="card-body">
                <div v-if="transactions && transactions.length > 0" class="table-responsive border-0">
                    <table class="table table-dark-gray align-middle p-4 mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col" class="border-0">#</th>
                                <th scope="col" class="border-0">النوع</th>
                                <th scope="col" class="border-0">المبلغ</th>
                                <th scope="col" class="border-0">الوصف</th>
                                <th scope="col" class="border-0">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="text-center" dir="rtl">
                            <tr v-for="transaction in transactions" :key="transaction.id">
                                <td>{{ transaction.id }}</td>
                                <td>
                                    <span v-if="transaction.type === 'credit'" class="badge bg-success">شحن</span>
                                    <span v-else class="badge bg-danger">خصم</span>
                                </td>
                                <td>${{ formatCurrency(transaction.amount) }}</td>
                                <td>{{ transaction.description }}</td>
                                <td>{{ new Date(transaction.created_at).toLocaleString('ar-EG') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-4">
                    <p class="mb-0">لا توجد معاملات بعد</p>
                </div>
            </div>
        </div>
        </div>
        <!-- End Overview Tab -->

        <!-- Consumption Tab -->
        <div v-show="currentTab === 'consumption'">
            <div v-if="consumptionCharts?.available">
                <!-- Summary Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-info bg-opacity-10 border border-info">
                            <div class="card-body">
                                <h6 class="mb-2">إجمالي التخزين</h6>
                                <h3 class="mb-1">{{ consumptionCharts.totalStorage }} GB</h3>
                                <small class="text-muted">{{ (consumptionCharts.totalStorage * 1).toFixed(2) }} جنيه</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning bg-opacity-10 border border-warning">
                            <div class="card-body">
                                <h6 class="mb-2">إجمالي الباندويث</h6>
                                <h3 class="mb-1">{{ consumptionCharts.totalBandwidth }} GB</h3>
                                <small class="text-muted">{{ (consumptionCharts.totalBandwidth * 0.5).toFixed(2) }} جنيه</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success bg-opacity-10 border border-success">
                            <div class="card-body">
                                <h6 class="mb-2">الإجمالي الكلي</h6>
                                <h3 class="mb-1">{{ ((consumptionCharts.totalStorage * 1) + (consumptionCharts.totalBandwidth * 0.5)).toFixed(2) }} جنيه</h3>
                                <small class="text-muted">${{ (((consumptionCharts.totalStorage * 1) + (consumptionCharts.totalBandwidth * 0.5)) / pricingInfo.usd_to_egp_rate).toFixed(2) }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sync Button -->
                <div class="text-center mb-4">
                    <button @click="syncConsumption" class="btn btn-primary" :disabled="syncForm.processing">
                        <i class="fas fa-sync me-1" :class="{ 'fa-spin': syncForm.processing }"></i>
                        {{ syncForm.processing ? 'جاري المزامنة...' : 'مزامنة الاستهلاك الآن' }}
                    </button>
                </div>

                <!-- Charts -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header border-bottom">
                                <h5 class="card-header-title">استهلاك التخزين</h5>
                            </div>
                            <div class="card-body" style="height: 300px;">
                                <canvas ref="storageChartRef"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header border-bottom">
                                <h5 class="card-header-title">استهلاك الباندويث</h5>
                            </div>
                            <div class="card-body" style="height: 300px;">
                                <canvas ref="bandwidthChartRef"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-header-title">تفاصيل الاستهلاك اليومي</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>Storage (GB)</th>
                                        <th>Bandwidth (GB)</th>
                                        <th>التكلفة (جنيه)</th>
                                        <th>التكلفة (دولار)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(date, index) in consumptionCharts.dates" :key="date">
                                        <td>{{ date }}</td>
                                        <td>{{ consumptionCharts.storageData[index] }}</td>
                                        <td>{{ consumptionCharts.bandwidthData[index] }}</td>
                                        <td>{{ ((consumptionCharts.storageData[index] * 1) + (consumptionCharts.bandwidthData[index] * 0.5)).toFixed(2) }} ج</td>
                                        <td>${{ (((consumptionCharts.storageData[index] * 1) + (consumptionCharts.bandwidthData[index] * 0.5)) / pricingInfo.usd_to_egp_rate).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                لا يمكن عرض بيانات الاستهلاك. تأكد من إعدادات Bunny Library.
            </div>
        </div>
        <!-- End Consumption Tab -->
    </div>
</AppLayout>
</template>

<style scoped>
.font-monospace {
    font-family: 'Courier New', monospace;
    letter-spacing: 1px;
}

.nav-tabs .nav-link {
    cursor: pointer;
}

canvas {
    max-height: 300px;
}
</style>

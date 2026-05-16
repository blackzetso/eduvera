<script setup>
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    teacher:          Object,
    recentStreams:    Array,
    totalStreams:     Number,
    liveStreams:      Number,
    scheduledStreams: Number,
})

const statusLabel = {
    scheduled: { label: 'مجدول',      class: 'bg-primary' },
    live:      { label: 'مباشر الآن', class: 'bg-danger'  },
    ended:     { label: 'انتهى',      class: 'bg-secondary'},
}
</script>

<template>
    <Head title="لوحة التحكم" />
    <AppLayout>
        <div class="page-content-wrapper border">

            <!-- Header -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h3 mb-1">
                        <i class="bi bi-house-fill me-2 text-primary"></i>
                        أهلاً، {{ teacher.name }}
                    </h1>
                    <p class="text-muted mb-0">{{ teacher.email }}</p>
                </div>

            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                                <i class="bi bi-collection-play fs-4 text-primary"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 fw-bold">{{ totalStreams }}</div>
                                <div class="text-muted small">إجمالي البثوث</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                                <i class="bi bi-broadcast fs-4 text-danger"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 fw-bold">{{ liveStreams }}</div>
                                <div class="text-muted small">بث مباشر الآن</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                                <i class="bi bi-calendar-check fs-4 text-success"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 fw-bold">{{ scheduledStreams }}</div>
                                <div class="text-muted small">بث مجدول</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent streams -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-header-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>أحدث البثوث
                    </h5>
                    <Link :href="route('teacher.live-streams.index')" class="btn btn-sm btn-outline-primary">عرض الكل</Link>
                </div>
                <div class="card-body p-0">
                    <div v-if="recentStreams.length === 0" class="text-center text-muted py-5">
                        <i class="bi bi-broadcast fs-1 d-block mb-2 opacity-25"></i>
                        لا توجد بثوث بعد
                    </div>
                    <table v-else class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>العنوان</th>
                                <th>المادة</th>
                                <th>تاريخ البدء</th>
                                <th>الحالة</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in recentStreams" :key="s.id">
                                <td class="fw-semibold">{{ s.title }}</td>
                                <td class="text-muted small">{{ s.subject || '—' }}</td>
                                <td class="small">{{ s.start_datetime }}</td>
                                <td>
                                    <span class="badge" :class="statusLabel[s.status]?.class ?? 'bg-secondary'">
                                        {{ statusLabel[s.status]?.label ?? s.status }}
                                    </span>
                                </td>
                                <td>
                                    <Link :href="route('teacher.live-streams.show', s.id)" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

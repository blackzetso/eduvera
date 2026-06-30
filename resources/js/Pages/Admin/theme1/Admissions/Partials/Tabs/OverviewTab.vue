<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  workspace: { type: Object, required: true },
  formatDateTime: { type: Function, required: true },
})
</script>

<template>
  <div>
    <div class="row g-3 mb-4 eduvera-kpi-row">
      <div class="col-sm-6 col-md-4">
        <div class="card admission-command-card border-0 shadow-sm h-100">
          <div class="card-body p-3">
            <div class="text-muted small">المصدر</div>
            <div class="fw-bold">{{ workspace.overview.source_channel_label }}</div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-4">
        <div class="card admission-command-card border-0 shadow-sm h-100">
          <div class="card-body p-3">
            <div class="text-muted small">آخر نشاط</div>
            <div class="fw-bold">{{ formatDateTime(workspace.overview.last_activity_at) }}</div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-4">
        <div class="card admission-command-card border-0 shadow-sm h-100">
          <div class="card-body p-3">
            <div class="text-muted small">المستندات المكتملة</div>
            <div class="fw-bold" :class="workspace.overview.document_summary.required_incomplete ? 'text-warning' : 'text-success'">
              {{ workspace.overview.document_summary.required_approved ?? 0 }}
              / {{ workspace.overview.document_summary.required_total ?? 0 }}
              <span class="small ms-1">({{ workspace.overview.document_summary.progress_percent ?? 0 }}%)</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card admission-command-card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent border-0 pt-3 pb-0">
        <h6 class="mb-0 fw-bold">القرار والتحويل</h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <div class="text-muted small">القرار الحالي</div>
            <div class="fw-bold">{{ workspace.overview.decision.current_label }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">تاريخ القرار</div>
            <div class="fw-bold">{{ formatDateTime(workspace.overview.decision.decision_at) }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">صادر عن</div>
            <div class="fw-bold">{{ workspace.overview.decision.decision_by || '—' }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">حالة التحويل</div>
            <div class="fw-bold">{{ workspace.overview.decision.conversion_status_label }}</div>
          </div>
          <div v-if="workspace.overview.decision.converted_student" class="col-md-6">
            <div class="text-muted small">ملف الطالب</div>
            <Link :href="workspace.overview.decision.converted_student.profile_url" class="fw-bold">
              {{ workspace.overview.decision.converted_student.name }}
              ({{ workspace.overview.decision.converted_student.student_code }})
            </Link>
          </div>
          <div v-if="workspace.overview.decision.converted_guardian" class="col-md-6">
            <div class="text-muted small">ملف العائلة</div>
            <Link :href="workspace.overview.decision.converted_guardian.profile_url" class="fw-bold">
              {{ workspace.overview.decision.converted_guardian.name }}
            </Link>
          </div>
          <div v-if="workspace.overview.decision.converted_at" class="col-12">
            <span class="text-muted small">{{ formatDateTime(workspace.overview.decision.converted_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card border h-100">
          <div class="card-header">تحليل التكرار (قراءة فقط)</div>
          <div class="card-body small">
            <p v-if="!workspace.duplicate_analysis.possible_existing_students?.length && !workspace.duplicate_analysis.possible_existing_guardians?.length && !workspace.duplicate_analysis.possible_existing_families?.length" class="text-muted mb-0">لا توجد تطابقات واضحة.</p>
            <div v-if="workspace.duplicate_analysis.possible_existing_students?.length">
              <strong>طلاب محتملون:</strong>
              <ul class="mb-2">
                <li v-for="s in workspace.duplicate_analysis.possible_existing_students" :key="s.id">
                  <Link v-if="s.profile_url" :href="s.profile_url" class="fw-semibold">{{ s.name }}</Link>
                  <template v-else>{{ s.name }}</template>
                  ({{ s.confidence }}%)
                </li>
              </ul>
            </div>
            <div v-if="workspace.duplicate_analysis.possible_existing_guardians?.length">
              <strong>أولياء أمور محتملون:</strong>
              <ul class="mb-2">
                <li v-for="g in workspace.duplicate_analysis.possible_existing_guardians" :key="g.id">
                  <Link v-if="g.profile_url" :href="g.profile_url" class="fw-semibold">{{ g.name }}</Link>
                  <template v-else>{{ g.name }}</template>
                  ({{ g.confidence }}%)
                </li>
              </ul>
            </div>
            <div v-if="workspace.duplicate_analysis.possible_existing_families?.length">
              <strong>عائلات محتملة:</strong>
              <ul class="mb-2">
                <li v-for="family in workspace.duplicate_analysis.possible_existing_families" :key="family.guardian_id">
                  <Link v-if="family.guardian_profile_url" :href="family.guardian_profile_url" class="fw-semibold">{{ family.guardian_name }}</Link>
                  <template v-else>{{ family.guardian_name }}</template>
                  —
                  <span v-for="child in family.children" :key="child.id" class="ms-1">
                    <Link v-if="child.profile_url" :href="child.profile_url">{{ child.name }}</Link>
                    <template v-else>{{ child.name }}</template>
                  </span>
                </li>
              </ul>
            </div>
            <div v-if="workspace.guardian_suggestions?.length">
              <strong>اقتراحات المطابقة:</strong>
              <div v-for="group in workspace.guardian_suggestions" :key="group.contact_id" class="mt-1">
                {{ group.contact_name }}:
                <Link
                  v-for="m in group.matches"
                  :key="m.guardian_id"
                  :href="route('admin.parents.show', m.guardian_id)"
                  class="badge bg-light text-dark text-decoration-none me-1"
                >{{ m.name }}</Link>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card border h-100">
          <div class="card-header">سجل التعيينات</div>
          <div class="card-body">
            <div v-if="!workspace.assignment_histories.length" class="text-muted">—</div>
            <div v-for="h in workspace.assignment_histories" :key="h.id" class="small mb-2">
              {{ h.from_user }} → {{ h.to_user }}
              <span class="text-muted">({{ formatDateTime(h.effective_at) }})</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

export const WORKSPACE_MODES = {
  ADMIN: 'admin',
  STUDENT: 'student',
  FAMILY: 'family',
}

export const STUDENT_SIDEBAR_LINKS = [
  { id: 'overview', label: 'نظرة عامة', icon: 'bi-grid' },
  { id: 'personal', label: 'البيانات الشخصية', icon: 'bi-person' },
  { id: 'academic', label: 'أكاديمي', icon: 'bi-journal-bookmark' },
  { id: 'attendance', label: 'الحضور', icon: 'bi-calendar-check' },
  { id: 'behavior', label: 'السلوك', icon: 'bi-emoji-smile' },
  { id: 'wallet', label: 'المحفظة', icon: 'bi-wallet2' },
  { id: 'family', label: 'العائلة', icon: 'bi-people' },
  { id: 'enrollment', label: 'سجل القيد', icon: 'bi-journal-text' },
]

export const FAMILY_SIDEBAR_LINKS = [
  { id: 'overview', label: 'نظرة عامة', icon: 'bi-grid' },
  { id: 'children', label: 'الأبناء', icon: 'bi-people' },
  { id: 'attendance', label: 'الحضور', icon: 'bi-calendar-check' },
  { id: 'academic', label: 'أكاديمي', icon: 'bi-journal-bookmark' },
  { id: 'finance', label: 'المالية', icon: 'bi-cash-coin' },
  { id: 'documents', label: 'المستندات', icon: 'bi-folder' },
  { id: 'guardians', label: 'أولياء الأمور', icon: 'bi-person-heart' },
  { id: 'profile', label: 'الملف الشخصي', icon: 'bi-person' },
  { id: 'overview-timeline', label: 'الخط الزمني', icon: 'bi-clock-history', tab: 'overview', scrollTimeline: true },
]

/**
 * Resolve workspace mode from current Ziggy route name.
 */
export function resolveWorkspaceMode(routeName) {
  if (!routeName) return WORKSPACE_MODES.ADMIN
  if (routeName === 'admin.students.show') return WORKSPACE_MODES.STUDENT
  if (routeName === 'admin.parents.show') return WORKSPACE_MODES.FAMILY
  return WORKSPACE_MODES.ADMIN
}

/**
 * Build workspace_context from page props when backend payload is absent.
 */
export function buildWorkspaceContextFromProps(props, mode) {
  if (mode === WORKSPACE_MODES.STUDENT) {
    const profile = props.profile || {}
    const ctx = props.student_context || {}
    return {
      mode: 'student',
      entity_id: profile.id ?? ctx.studentId,
      entity_name: profile.name ?? '—',
      label: 'Student Workspace',
      label_ar: 'مساحة الطالب',
      icon: 'bi-mortarboard-fill',
      return_url: route('admin.students.index'),
      return_label: 'Return To Admin',
      return_label_ar: 'العودة للوحة الإدارة',
      related_profile_url: profile.primary_guardian_id
        ? route('admin.parents.show', profile.primary_guardian_id)
        : null,
      related_profile_label: profile.primary_guardian_name
        ? `Open Family — ${profile.primary_guardian_name}`
        : 'Open Family Profile',
      related_profile_label_ar: profile.primary_guardian_name
        ? `فتح العائلة — ${profile.primary_guardian_name}`
        : 'فتح ملف العائلة',
      student_context: ctx,
    }
  }

  if (mode === WORKSPACE_MODES.FAMILY) {
    const profile = props.profile || {}
    const children = props.children || []
    const primaryChild = children[0]
    const ctx = props.family_context || {}
    const familyLabel = primaryChild?.name
      ? `Family of ${primaryChild.name}`
      : `Family of ${profile.name || '—'}`

    return {
      mode: 'family',
      entity_id: profile.id ?? ctx.parentId,
      entity_name: familyLabel,
      label: 'Family Workspace',
      label_ar: 'مساحة العائلة',
      icon: 'bi-people-fill',
      return_url: route('admin.parents.index'),
      return_label: 'Return To Admin',
      return_label_ar: 'العودة للوحة الإدارة',
      related_profile_url: primaryChild?.id
        ? route('admin.students.show', primaryChild.id)
        : null,
      related_profile_label: primaryChild?.name
        ? `Open Child — ${primaryChild.name}`
        : 'Open Child Profile',
      related_profile_label_ar: primaryChild?.name
        ? `فتح الطالب — ${primaryChild.name}`
        : 'فتح ملف الطالب',
      family_context: ctx,
      primary_child_id: primaryChild?.id ?? null,
    }
  }

  return {
    mode: 'admin',
    entity_id: null,
    entity_name: null,
    label: 'Admin Workspace',
    label_ar: 'لوحة الإدارة',
    icon: 'bi-speedometer2',
    return_url: route('admin.dashboard.index'),
    return_label: 'Dashboard',
    return_label_ar: 'لوحة التحكم',
  }
}

export function useWorkspaceContext(overrides = {}) {
  const page = usePage()

  const routeName = computed(() => page.props.ziggy?.location?.name ?? page.props.routeName ?? null)

  const mode = computed(() => overrides.mode ?? resolveWorkspaceMode(routeName.value))

  const isStudentWorkspace = computed(() => mode.value === WORKSPACE_MODES.STUDENT)
  const isFamilyWorkspace = computed(() => mode.value === WORKSPACE_MODES.FAMILY)
  const isWorkspaceMode = computed(() => isStudentWorkspace.value || isFamilyWorkspace.value)

  const workspaceContext = computed(() => {
    if (overrides.workspaceContext) {
      return { ...overrides.workspaceContext }
    }

    const fromServer = page.props.workspace_context
    if (fromServer?.mode) {
      return fromServer
    }

    return buildWorkspaceContextFromProps(page.props, mode.value)
  })

  const sidebarLinks = computed(() => {
    if (isStudentWorkspace.value) return STUDENT_SIDEBAR_LINKS
    if (isFamilyWorkspace.value) return FAMILY_SIDEBAR_LINKS
    return []
  })

  return {
    mode,
    workspaceContext,
    sidebarLinks,
    isStudentWorkspace,
    isFamilyWorkspace,
    isWorkspaceMode,
  }
}

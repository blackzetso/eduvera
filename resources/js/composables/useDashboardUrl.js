import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const DASHBOARD_ROUTE_BY_USER_TYPE = {
    admin: 'admin.dashboard.index',
    student: 'student.dashboard',
    guardian: 'guardian.dashboard',
    teacher: 'teacher.dashboard.index',
    control_staff: 'admin.attendances.dashboard',
    social_worker: 'admin.attendances.alerts',
    nurse: 'admin.attendances.alerts',
    department_head: 'department-plan.index',
    card_reader: 'admin.attendances.dashboard',
}

export function useDashboardUrl() {
    const page = usePage()

    const dashboardUrl = computed(() => {
        const userType = page.props.auth?.user?.user_type
        const routeName = DASHBOARD_ROUTE_BY_USER_TYPE[userType] ?? 'home'

        return route(routeName)
    })

    return { dashboardUrl }
}

import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  landingSectionEditLabel,
  landingSectionEditLink,
  landingSectionSettingsLink,
} from '@/utils/landingCmsEditLinks'

function isWebsiteCmsEditor(user) {
  if (!user || typeof user !== 'object') return false
  return user.user_type === 'admin'
}

export function useLandingCmsEdit() {
  const page = usePage()

  const canEditWebsiteCms = computed(() => {
    if (page.props.canEditWebsiteCms === true || page.props.canEditWebsiteCms === 1) {
      return true
    }
    return isWebsiteCmsEditor(page.props.auth?.user)
  })

  function sectionEditHref(section) {
    return landingSectionEditLink(section)
  }

  function sectionEditTitle(section) {
    return landingSectionEditLabel(section)
  }

  function sectionSettingsHref(section) {
    return landingSectionSettingsLink(section)
  }

  return {
    canEditWebsiteCms,
    sectionEditHref,
    sectionEditTitle,
    sectionSettingsHref,
  }
}

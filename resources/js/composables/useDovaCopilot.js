import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

/**
 * Dova Platform Copilot — premium assistant experience.
 */
export function useDovaCopilot() {
  const page = usePage()

  const liveConfig = ref(null)
  const expression = ref('welcome')
  const loading = ref(false)
  const lastResponse = ref(null)

  const config = computed(() => liveConfig.value ?? page.props.dovaCopilot ?? { enabled: false })
  const enabled = computed(() => Boolean(config.value.enabled))
  const portal = computed(() => config.value.portal ?? 'public')
  const role = computed(() => config.value.role ?? 'guest')
  const context = computed(() => config.value.context ?? null)
  const locale = computed(() => config.value.locale ?? page.props.locale ?? 'en')
  const tagline = computed(() => config.value.tagline ?? '')
  const greeting = computed(() => config.value.greeting ?? '')
  const welcome = computed(() => config.value.welcome ?? null)
  const statusLabel = computed(() => config.value.statusLabel ?? (locale.value === 'ar' ? 'متصل الآن' : 'Online'))
  const sampleQuestions = computed(() => config.value.sampleQuestions ?? [])
  const quickActions = computed(() => config.value.quickActions ?? [])
  const whisperFallback = computed(() => Boolean(config.value.voice?.whisperFallback))

  function currentPath() {
    if (typeof window === 'undefined') {
      return page.url ?? '/'
    }

    return window.location.pathname + window.location.search
  }

  async function refreshContext(path = currentPath()) {
    if (!enabled.value && !page.props.dovaCopilot?.enabled) {
      return null
    }

    try {
      const { data } = await axios.get(route('dova.copilot.context'), {
        params: { path },
      })

      if (data?.enabled) {
        liveConfig.value = data
      }

      return data
    } catch {
      return null
    }
  }

  async function suggest(message, path = currentPath(), options = {}) {
    if (!enabled.value) {
      return null
    }

    loading.value = true
    expression.value = 'thinking'

    const payload = {
      message,
      path,
    }

    if (options.inputMethod) {
      payload.input_method = options.inputMethod
    }

    if (options.detectedLanguage) {
      payload.detected_language = options.detectedLanguage
    }

    try {
      const { data } = await axios.post(route('dova.copilot.suggest'), payload)

      if (data?.context) {
        liveConfig.value = {
          ...(liveConfig.value ?? config.value),
          context: data.context,
          portal: data.context.portal,
          role: data.context.role,
        }
      }

      lastResponse.value = data
      expression.value = data.expression ?? 'explaining'

      return data
    } catch {
      expression.value = 'help'
      const isAr = locale.value === 'ar'
      const fallback = {
        introduction: isAr ? 'عذراً، حدث خطأ بسيط.' : 'Sorry, something went wrong.',
        explanation: isAr
          ? 'يمكنك المحاولة مرة أخرى أو اختيار أحد الاقتراحات أدناه.'
          : 'You can try again or pick one of the suggestions below.',
        footer: '',
        text: isAr
          ? 'عذراً، حدث خطأ بسيط. يمكنك المحاولة مرة أخرى أو اختيار أحد الاقتراحات أدناه.'
          : 'Sorry, something went wrong. You can try again or pick one of the suggestions below.',
        expression: 'help',
        actions: quickActions.value,
        workflow: null,
      }
      lastResponse.value = fallback

      return fallback
    } finally {
      loading.value = false
    }
  }

  function executeAction(action) {
    if (!action?.href) {
      return
    }

    if (action.type === 'anchor') {
      const el = document.querySelector(action.href)
      if (el) {
        el.scrollIntoView({ behavior: 'smooth' })
      } else if (action.href.startsWith('#')) {
        window.location.hash = action.href
      }

      return
    }

    if (action.external) {
      window.open(action.href, '_blank', 'noopener')
      return
    }

    router.visit(action.href)
  }

  watch(
    () => page.url,
    () => {
      if (enabled.value) {
        refreshContext()
      }
    },
  )

  async function submitFeedback({ helpful, queryId, faqId, question, path = currentPath() }) {
    try {
      await axios.post(route('dova.copilot.feedback'), {
        helpful,
        query_id: queryId,
        faq_id: faqId,
        question,
        path,
      })
      return true
    } catch {
      return false
    }
  }

  return {
    enabled,
    portal,
    role,
    context,
    locale,
    tagline,
    greeting,
    welcome,
    statusLabel,
    sampleQuestions,
    quickActions,
    whisperFallback,
    expression,
    loading,
    lastResponse,
    refreshContext,
    suggest,
    executeAction,
    submitFeedback,
    currentPath,
  }
}

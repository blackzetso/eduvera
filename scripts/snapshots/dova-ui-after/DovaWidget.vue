<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useDovaCopilot } from '@/composables/useDovaCopilot'
import { dovaAvatarSrc, normalizeDovaExpression, DOVA_TAGLINE } from '@/composables/useDovaAvatar'
import DovaActionBar from '@/Components/Dova/DovaActionBar.vue'

const page = usePage()
const {
  enabled,
  locale,
  loading,
  welcome,
  statusLabel,
  sampleQuestions,
  expression,
  suggest,
  executeAction,
  refreshContext,
} = useDovaCopilot()

const direction = computed(() => page.props.direction ?? 'ltr')
const contextKey = computed(() => `${page.url}:${locale.value}`)

const open = ref(false)
const panelVisible = ref(false)
const input = ref('')
const inputFocused = ref(false)
const messages = ref([])
const messagesEl = ref(null)
const inputEl = ref(null)
const lastExpression = ref('welcome')

const orbSrc = '/brand/dova/orb.svg'

const isEmptyState = computed(() => messages.value.length === 0 && !loading.value)

const avatarExpression = computed(() => {
  if (loading.value) {
    return 'thinking'
  }

  if (inputFocused.value && isEmptyState.value) {
    return 'listening'
  }

  if (isEmptyState.value) {
    return 'welcome'
  }

  return normalizeDovaExpression(lastExpression.value)
})

const mascotSrc = computed(() => dovaAvatarSrc(avatarExpression.value, 'mascot'))

const welcomeCard = computed(() => welcome.value ?? {
  headline: locale.value === 'ar' ? 'مرحباً، أنا Dova 👋' : "Hi, I'm Dova 👋",
  prompt: locale.value === 'ar' ? 'كيف يمكنني مساعدتك اليوم؟' : 'How can I help you today?',
  body: locale.value === 'ar'
    ? 'يمكنني مساعدتك في التنقل داخل المنصة والإجابة عن أسئلتك وإرشادك خلال أي إجراء.'
    : 'I can help you navigate the platform, answer questions, and guide you through any process.',
})

const tagline = computed(() => DOVA_TAGLINE[locale.value] ?? DOVA_TAGLINE.en)

const welcomeCardBody = computed(() => {
  const body = welcomeCard.value.body ?? ''
  const prompt = welcomeCard.value.prompt ?? ''

  if (! prompt || ! body.includes(prompt)) {
    return body
  }

  return body.replace(prompt, '').replace(/\s+\.\s*$/, '.').trim()
})

async function toggle() {
  if (!open.value) {
    await refreshContext()
    lastExpression.value = 'welcome'
    open.value = true
    requestAnimationFrame(() => {
      panelVisible.value = true
      nextTick(() => inputEl.value?.focus())
    })
    return
  }

  panelVisible.value = false
  setTimeout(() => {
    open.value = false
  }, 220)
}

function pushUser(text) {
  messages.value.push({
    id: `u-${Date.now()}`,
    role: 'user',
    text,
  })
  scrollMessages()
}

function pushAssistant(payload, msgExpression = null) {
  const resolvedExpression = normalizeDovaExpression(msgExpression ?? payload.expression ?? 'explaining')

  messages.value.push({
    id: `a-${Date.now()}`,
    role: 'assistant',
    introduction: payload.introduction ?? null,
    explanation: payload.explanation ?? null,
    footer: payload.footer ?? null,
    text: payload.text ?? '',
    actions: payload.actions ?? [],
    workflow: payload.workflow ?? null,
    expression: resolvedExpression,
  })

  lastExpression.value = resolvedExpression

  if (resolvedExpression === 'celebrating' || resolvedExpression === 'success') {
    setTimeout(() => {
      if (!loading.value && messages.value.length > 0) {
        lastExpression.value = 'welcome'
      }
    }, 2800)
  }

  scrollMessages()
}

async function scrollMessages() {
  await nextTick()
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

async function sendMessage(text) {
  const trimmed = (text ?? input.value).trim()
  if (!trimmed || loading.value) {
    return
  }

  input.value = ''
  pushUser(trimmed)

  const response = await suggest(trimmed)

  if (response) {
    pushAssistant(response, response.expression)
  }
}

function onAction(action) {
  executeAction(action)
  panelVisible.value = false
  setTimeout(() => {
    open.value = false
  }, 220)
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    sendMessage()
  }
}

watch(open, (isOpen) => {
  document.body.classList.toggle('dova-widget-open', isOpen)
})

watch(expression, (value) => {
  if (loading.value) {
    lastExpression.value = normalizeDovaExpression(value)
  }
})

watch(contextKey, async () => {
  messages.value = []
  lastExpression.value = 'welcome'

  if (open.value) {
    await refreshContext()
  }
})

onMounted(() => {
  if (enabled.value) {
    refreshContext()
  }
})
</script>

<template>
  <div
    v-if="enabled"
    class="dova-widget"
    :dir="direction"
    aria-live="polite"
  >
    <Transition name="dova-panel">
      <div
        v-if="open"
        class="dova-widget__panel"
        :class="{ 'dova-widget__panel--visible': panelVisible }"
        role="dialog"
        aria-label="Dova assistant"
      >
        <section class="dova-widget__hero" :class="{ 'dova-widget__hero--compact': !isEmptyState }">
          <button
            type="button"
            class="dova-widget__close"
            :aria-label="locale === 'ar' ? 'إغلاق' : 'Close'"
            @click="toggle"
          >
            <i class="bi bi-x-lg" aria-hidden="true" />
          </button>

          <div class="dova-widget__hero-layout">
            <div class="dova-widget__mascot-stage" aria-hidden="true">
              <Transition name="dova-mascot" mode="out-in">
                <img
                  :key="avatarExpression"
                  :src="mascotSrc"
                  alt=""
                  class="dova-widget__mascot"
                  :class="`dova-widget__mascot--${avatarExpression}`"
                />
              </Transition>
            </div>

            <div class="dova-widget__hero-copy">
              <p class="dova-widget__title">Dova</p>
              <p class="dova-widget__tagline">{{ tagline }}</p>
              <p class="dova-widget__status">
                <span class="dova-widget__status-dot" />
                {{ statusLabel }}
              </p>
              <p v-if="isEmptyState" class="dova-widget__prompt">
                {{ welcomeCard.prompt }}
              </p>
            </div>
          </div>
        </section>

        <div ref="messagesEl" class="dova-widget__body">
          <article v-if="isEmptyState" class="dova-widget__welcome-card">
            <p class="dova-widget__welcome-card-title">
              {{ locale === 'ar' ? '👋 أهلاً بك' : '👋 Welcome' }}
            </p>
            <p class="dova-widget__welcome-card-headline">
              {{ welcomeCard.headline }}
            </p>
            <p class="dova-widget__welcome-card-body">
              {{ welcomeCardBody }}
            </p>
            <p class="dova-widget__welcome-card-prompt">
              {{ welcomeCard.prompt }}
            </p>
          </article>

          <div class="dova-widget__messages">
            <div
              v-for="msg in messages"
              :key="msg.id"
              class="dova-widget__bubble"
              :class="msg.role === 'user' ? 'dova-widget__bubble--user' : 'dova-widget__bubble--assistant'"
            >
              <div v-if="msg.role === 'assistant'" class="dova-widget__bubble-meta">
                <img
                  :src="dovaAvatarSrc(msg.expression ?? 'explaining', 'bust')"
                  alt=""
                  class="dova-widget__bubble-avatar"
                  aria-hidden="true"
                />
              </div>
              <div class="dova-widget__bubble-body">
                <template v-if="msg.introduction || msg.explanation">
                  <p v-if="msg.introduction" class="dova-widget__bubble-intro">
                    {{ msg.introduction }}
                  </p>
                  <p v-if="msg.explanation" class="dova-widget__bubble-detail">
                    {{ msg.explanation }}
                  </p>
                  <p v-if="msg.footer" class="dova-widget__bubble-footer">
                    {{ msg.footer }}
                  </p>
                </template>
                <p v-else>{{ msg.text }}</p>
                <div v-if="msg.actions?.length" class="dova-widget__bubble-actions">
                  <DovaActionBar
                    :actions="msg.actions"
                    :workflow="msg.workflow"
                    @execute="onAction"
                  />
                </div>
              </div>
            </div>

            <div v-if="loading" class="dova-widget__bubble dova-widget__bubble--assistant">
              <div class="dova-widget__bubble-meta">
                <img
                  :src="dovaAvatarSrc('thinking', 'bust')"
                  alt=""
                  class="dova-widget__bubble-avatar dova-widget__bubble-avatar--thinking"
                  aria-hidden="true"
                />
              </div>
              <div class="dova-widget__bubble-body">
                <p class="dova-widget__thinking-label">
                  {{ locale === 'ar' ? 'دوفا تفكر...' : 'Dova is thinking...' }}
                </p>
                <div class="dova-widget__typing" aria-hidden="true">
                  <span /><span /><span />
                </div>
              </div>
            </div>
          </div>

          <div v-if="sampleQuestions.length && isEmptyState" class="dova-widget__suggestions">
            <p class="dova-widget__suggestions-label">
              {{ locale === 'ar' ? 'اقتراحات سريعة' : 'Suggested questions' }}
            </p>
            <div class="dova-widget__suggestions-list">
              <button
                v-for="(q, i) in sampleQuestions"
                :key="i"
                type="button"
                class="dova-widget__suggestion"
                @click="sendMessage(q)"
              >
                {{ q }}
              </button>
            </div>
          </div>
        </div>

        <form class="dova-widget__composer" @submit.prevent="sendMessage()">
          <input
            ref="inputEl"
            v-model="input"
            type="text"
            class="dova-widget__input"
            :placeholder="locale === 'ar' ? 'اكتب سؤالك هنا...' : 'Write your question here...'"
            :disabled="loading"
            autocomplete="off"
            @focus="inputFocused = true"
            @blur="inputFocused = false"
            @keydown="onKeydown"
          />
          <button type="submit" class="dova-widget__send" :disabled="loading || !input.trim()" :aria-label="locale === 'ar' ? 'إرسال' : 'Send'">
            <i class="bi bi-send-fill" aria-hidden="true" />
          </button>
        </form>
      </div>
    </Transition>

    <button
      type="button"
      class="dova-widget__launcher"
      :class="{ 'dova-widget__launcher--open': open }"
      :aria-expanded="open"
      :aria-label="locale === 'ar' ? 'اسأل دوفا' : 'Ask Dova'"
      @click="toggle"
    >
      <img :src="orbSrc" alt="Dova" />
    </button>
  </div>
</template>

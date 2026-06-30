<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useDovaCopilot } from '@/composables/useDovaCopilot'
import { useDovaSpeechRecognition } from '@/composables/useDovaSpeechRecognition'
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
  submitFeedback,
  refreshContext,
  whisperFallback,
  currentPath,
} = useDovaCopilot()

const {
  isRecording,
  isProcessing,
  timerLabel,
  error: voiceError,
  errorMessage: voiceErrorMessage,
  lastDetectedLanguage,
  startRecording,
  stopRecording,
  cancelRecording,
  clearError: clearVoiceError,
} = useDovaSpeechRecognition({
  locale,
  whisperFallback,
  currentPath,
})

const direction = computed(() => page.props.direction ?? 'ltr')
const contextKey = computed(() => `${page.url}:${locale.value}`)

const open = ref(false)
const panelVisible = ref(false)
const input = ref('')
const inputFocused = ref(false)
const pendingVoiceInput = ref(false)
const transcriptReview = ref(null)
const answerCelebrating = ref(false)
const messages = ref([])
const messagesEl = ref(null)
const inputEl = ref(null)
const lastExpression = ref('welcome')
let celebrateTimer = null

const orbSrc = '/brand/dova/dova-mascot-welcome.png'

const isEmptyState = computed(() => messages.value.length === 0 && !loading.value)

const avatarExpression = computed(() => {
  if (isRecording.value || transcriptReview.value) {
    return 'listening'
  }

  if (isProcessing.value || loading.value) {
    return 'thinking'
  }

  if (answerCelebrating.value) {
    return 'success'
  }

  if (!isEmptyState.value) {
    return normalizeDovaExpression(lastExpression.value)
  }

  if (inputFocused.value) {
    return 'listening'
  }

  return 'welcome'
})

const heroStatusLabel = computed(() => {
  if (isRecording.value) {
    return locale.value === 'ar' ? 'أستمع إليك...' : 'Listening...'
  }

  if (isProcessing.value) {
    return locale.value === 'ar' ? 'أفهم ما قلته...' : 'Processing speech...'
  }

  if (transcriptReview.value) {
    return locale.value === 'ar' ? 'راجع رسالتك' : 'Review your message'
  }

  if (loading.value) {
    return locale.value === 'ar' ? 'أفكر...' : 'Thinking...'
  }

  if (answerCelebrating.value) {
    return locale.value === 'ar' ? 'إليك الإجابة!' : 'Here is your answer!'
  }

  return statusLabel.value
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

const launcherHint = computed(() =>
  locale.value === 'ar' ? 'تحتاج مساعدة؟' : 'Need help?',
)

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

function pushAssistant(payload, msgExpression = null, userQuestion = null) {
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
    knowledgeDebug: payload.knowledgeDebug ?? null,
    queryId: payload.queryId ?? null,
    faqId: payload.faqId ?? null,
    showFeedback: payload.showFeedback ?? false,
    feedbackGiven: false,
    userQuestion: userQuestion ?? null,
  })

  lastExpression.value = resolvedExpression === 'success' || resolvedExpression === 'celebrating'
    ? 'explaining'
    : resolvedExpression

  flashAnswerSuccess()

  scrollMessages()
}

function flashAnswerSuccess() {
  if (celebrateTimer) {
    clearTimeout(celebrateTimer)
  }

  answerCelebrating.value = true
  celebrateTimer = setTimeout(() => {
    answerCelebrating.value = false
    celebrateTimer = null
  }, 2600)
}

async function scrollMessages() {
  await nextTick()
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

async function sendMessage(text, options = {}) {
  const trimmed = (text ?? input.value).trim()
  if (!trimmed || loading.value) {
    return
  }

  const sendOptions = {
    inputMethod: options.inputMethod ?? (pendingVoiceInput.value ? 'voice' : 'text'),
    detectedLanguage: options.detectedLanguage ?? lastDetectedLanguage.value ?? null,
  }

  transcriptReview.value = null
  input.value = ''
  pendingVoiceInput.value = false
  pushUser(trimmed)

  const response = await suggest(trimmed, undefined, sendOptions)

  if (response) {
    pushAssistant(response, response.expression, trimmed)
  }
}

function showTranscriptReview(result) {
  if (!result?.transcript) {
    return
  }

  transcriptReview.value = {
    text: result.transcript,
    detectedLanguage: result.detectedLanguage ?? null,
  }
  pendingVoiceInput.value = true
}

async function toggleVoiceRecording() {
  if (loading.value || isProcessing.value || transcriptReview.value) {
    return
  }

  if (isRecording.value) {
    const result = await stopRecording()
    showTranscriptReview(result)
    return
  }

  clearVoiceError()
  await startRecording()
}

async function finishVoiceRecording() {
  const result = await stopRecording()
  showTranscriptReview(result)
}

function cancelVoiceRecording() {
  cancelRecording()
  clearVoiceError()
}

function editTranscriptReview() {
  if (!transcriptReview.value) {
    return
  }

  input.value = transcriptReview.value.text
  transcriptReview.value = null
  nextTick(() => inputEl.value?.focus())
}

async function reRecordTranscript() {
  transcriptReview.value = null
  pendingVoiceInput.value = false
  clearVoiceError()
  await startRecording()
}

function sendTranscriptReview() {
  if (!transcriptReview.value?.text?.trim() || loading.value) {
    return
  }

  const text = transcriptReview.value.text.trim()
  const detectedLanguage = transcriptReview.value.detectedLanguage

  sendMessage(text, {
    inputMethod: 'voice',
    detectedLanguage,
  })
}

async function giveFeedback(msg, helpful) {
  if (msg.feedbackGiven) return
  msg.feedbackGiven = true
  await submitFeedback({
    helpful,
    queryId: msg.queryId,
    faqId: msg.faqId,
    question: msg.userQuestion,
  })
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
  transcriptReview.value = null
  pendingVoiceInput.value = false
  cancelVoiceRecording()

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
        <section
          class="dova-widget__hero"
          :class="{
            'dova-widget__hero--compact': !isEmptyState,
            'dova-widget__hero--listening': isRecording,
            'dova-widget__hero--thinking': isProcessing || loading,
          }"
        >
          <button
            type="button"
            class="dova-widget__close"
            :aria-label="locale === 'ar' ? 'إغلاق' : 'Close'"
            @click="toggle"
          >
            <i class="bi bi-x-lg" aria-hidden="true" />
          </button>

          <div class="dova-widget__hero-layout">
            <div
              class="dova-widget__mascot-stage"
              :class="{ 'dova-widget__mascot-stage--listening': isRecording || transcriptReview }"
              aria-hidden="true"
            >
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
                <span
                  class="dova-widget__status-dot"
                  :class="{
                    'dova-widget__status-dot--listening': isRecording,
                    'dova-widget__status-dot--thinking': isProcessing || loading,
                  }"
                />
                {{ heroStatusLabel }}
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
                <div v-if="msg.knowledgeDebug" class="dova-widget__knowledge-debug">
                  <p class="dova-widget__knowledge-debug-title">
                    {{ locale === 'ar' ? 'مصدر المعرفة' : 'Knowledge source' }}
                  </p>
                  <p>{{ locale === 'ar' ? 'المصدر' : 'Source' }}: {{ msg.knowledgeDebug.source }}</p>
                  <p>{{ locale === 'ar' ? 'السجل' : 'Record' }}: {{ msg.knowledgeDebug.record }}</p>
                  <p>{{ locale === 'ar' ? 'الثقة' : 'Confidence' }}: {{ Math.round((msg.knowledgeDebug.confidence ?? 0) * 100) }}%</p>
                  <p v-if="msg.knowledgeDebug.matchedText">
                    {{ locale === 'ar' ? 'المحتوى' : 'Content' }}: {{ msg.knowledgeDebug.matchedText }}
                  </p>
                </div>
              </template>
              <p v-else>{{ msg.text }}</p>
                <div v-if="msg.showFeedback && !msg.feedbackGiven" class="dova-widget__feedback">
                  <p class="dova-widget__feedback-label">{{ locale === 'ar' ? 'هل كانت الإجابة مفيدة؟' : 'Was this helpful?' }}</p>
                  <div class="dova-widget__feedback-actions">
                    <button type="button" class="dova-widget__feedback-btn" @click="giveFeedback(msg, true)">👍 {{ locale === 'ar' ? 'نعم' : 'Yes' }}</button>
                    <button type="button" class="dova-widget__feedback-btn" @click="giveFeedback(msg, false)">👎 {{ locale === 'ar' ? 'لا' : 'No' }}</button>
                  </div>
                </div>
                <p v-else-if="msg.feedbackGiven" class="dova-widget__feedback-thanks small text-muted">
                  {{ locale === 'ar' ? 'شكراً على ملاحظتك!' : 'Thanks for your feedback!' }}
                </p>
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

        <div v-if="voiceErrorMessage" class="dova-widget__voice-error" role="alert">
          {{ voiceErrorMessage }}
        </div>

        <div v-if="isRecording || isProcessing" class="dova-widget__recording dova-widget__recording--active" role="status">
          <div class="dova-widget__recording-pulse" aria-hidden="true" />
          <div class="dova-widget__recording-main">
            <div class="dova-widget__recording-indicator">
              <span class="dova-widget__recording-dot" aria-hidden="true" />
              <span>{{ isProcessing ? (locale === 'ar' ? 'جاري الفهم...' : 'Understanding...') : (locale === 'ar' ? 'أستمع...' : 'Listening...') }}</span>
            </div>
            <span class="dova-widget__recording-timer">{{ timerLabel }}</span>
            <div v-if="isRecording" class="dova-widget__recording-waves" aria-hidden="true">
              <span /><span /><span /><span />
            </div>
          </div>
          <div v-if="isRecording" class="dova-widget__recording-actions">
            <button
              type="button"
              class="dova-widget__recording-btn dova-widget__recording-btn--stop"
              @click="finishVoiceRecording"
            >
              {{ locale === 'ar' ? 'إيقاف' : 'Stop' }}
            </button>
            <button
              type="button"
              class="dova-widget__recording-btn dova-widget__recording-btn--cancel"
              @click="cancelVoiceRecording"
            >
              {{ locale === 'ar' ? 'إلغاء' : 'Cancel' }}
            </button>
          </div>
        </div>

        <div v-if="transcriptReview" class="dova-widget__transcript-review" role="region" :aria-label="locale === 'ar' ? 'مراجعة النص' : 'Transcript review'">
          <p class="dova-widget__transcript-label">
            {{ locale === 'ar' ? 'قلت:' : 'You said:' }}
          </p>
          <p class="dova-widget__transcript-text">{{ transcriptReview.text }}</p>
          <div class="dova-widget__transcript-actions">
            <button type="button" class="dova-widget__transcript-btn" @click="editTranscriptReview">
              {{ locale === 'ar' ? 'تعديل' : 'Edit' }}
            </button>
            <button type="button" class="dova-widget__transcript-btn" @click="reRecordTranscript">
              {{ locale === 'ar' ? 'إعادة التسجيل' : 'Re-record' }}
            </button>
            <button type="button" class="dova-widget__transcript-btn dova-widget__transcript-btn--send" @click="sendTranscriptReview">
              {{ locale === 'ar' ? 'إرسال' : 'Send' }}
            </button>
          </div>
        </div>

        <form class="dova-widget__composer" @submit.prevent="sendMessage()">
          <input
            ref="inputEl"
            v-model="input"
            type="text"
            class="dova-widget__input"
            :placeholder="locale === 'ar' ? 'اكتب سؤالك هنا...' : 'Write your question here...'"
            :disabled="loading || isRecording || isProcessing || transcriptReview"
            autocomplete="off"
            @focus="inputFocused = true"
            @blur="inputFocused = false"
            @keydown="onKeydown"
          />
          <button
            type="button"
            class="dova-widget__mic"
            :class="{ 'dova-widget__mic--active': isRecording }"
            :disabled="loading || isProcessing || transcriptReview"
            :aria-label="locale === 'ar' ? 'تسجيل رسالة صوتية' : 'Record voice note'"
            :aria-pressed="isRecording"
            @click="toggleVoiceRecording"
          >
            <i class="bi bi-mic-fill" aria-hidden="true" />
          </button>
          <button type="submit" class="dova-widget__send" :disabled="loading || isRecording || isProcessing || transcriptReview || !input.trim()" :aria-label="locale === 'ar' ? 'إرسال' : 'Send'">
            <i class="bi bi-send-fill" aria-hidden="true" />
          </button>
        </form>
      </div>
    </Transition>

    <div class="dova-widget__launcher-wrap">
      <p v-if="!open" class="dova-widget__launcher-hint" aria-hidden="true">
        {{ launcherHint }}
      </p>
      <button
        type="button"
        class="dova-widget__launcher"
        :class="{ 'dova-widget__launcher--open': open }"
        :aria-expanded="open"
        :aria-label="locale === 'ar' ? 'اسأل دوفا — مساعد ذكي للإجابة على استفساراتك' : 'Ask Dova — AI assistant for your questions'"
        @click="toggle"
      >
        <img :src="orbSrc" alt="Dova" />
      </button>
    </div>
  </div>
</template>

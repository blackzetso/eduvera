<script setup>
import { computed, ref, watch, onUnmounted } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useCta } from '@/composables/useCta'

const { imageSrc, stageModalUi } = useWebsiteContent()
const { resolveCta } = useCta()

const props = defineProps({
  stage: { type: Object, default: null },
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const tabs = computed(() => stageModalUi.value?.tabs ?? [])
const paneTitles = computed(() => stageModalUi.value?.paneTitles ?? {})
const footerUi = computed(() => stageModalUi.value?.footer ?? {})

const applyCta = computed(() => {
  const cta = resolveCta(footerUi.value.applyCtaId ?? 'apply')
  return { ...cta, label: footerUi.value.applyLabel || cta?.label }
})
const visitCta = computed(() => resolveCta(footerUi.value.visitCtaId ?? 'visit'))
const closeLabel = computed(() => footerUi.value.closeLabel ?? 'Close')

const activeTab = ref('overview')

watch(
  () => props.stage?.id,
  () => {
    activeTab.value = 'overview'
  }
)

watch(
  () => props.open,
  (v) => {
    document.body.style.overflow = v ? 'hidden' : ''
  }
)

onUnmounted(() => {
  document.body.style.overflow = ''
})

const stageTitle = computed(() => props.stage?.title ?? '')

function paneTitle(key, fallback) {
  return paneTitles.value[key] ?? fallback
}

function close() {
  emit('close')
}

function onBackdrop(e) {
  if (e.target === e.currentTarget) close()
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && stage"
      class="st-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="`${stageTitle} details`"
      @click="onBackdrop"
    >
      <div class="st-modal__panel">
        <button type="button" class="st-modal__close" :aria-label="closeLabel" @click="close">
          <i class="bi bi-x-lg"></i>
        </button>

        <div class="st-modal__hero" :style="{ backgroundImage: `url(${imageSrc(stage.image)})` }">
          <div class="st-modal__hero-overlay">
            <span class="st-modal__eyebrow">{{ stage.subtitle }}</span>
            <h2>{{ stage.title }}</h2>
            <p>{{ stage.tagline }}</p>
          </div>
        </div>

        <div class="st-modal__tabs" role="tablist">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            role="tab"
            class="st-modal__tab"
            :class="{ 'st-modal__tab--active': activeTab === tab.id }"
            :aria-selected="activeTab === tab.id"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="st-modal__body">
          <div v-show="activeTab === 'overview'" class="st-modal__pane">
            <p>{{ stage.overview }}</p>
          </div>
          <div v-show="activeTab === 'curriculum'" class="st-modal__pane">
            <h4>{{ paneTitle('curriculum', 'Subjects & Program') }}</h4>
            <ul class="st-modal__list">
              <li v-for="item in stage.curriculum" :key="item">
                <i class="bi bi-check2-circle"></i>
                {{ item }}
              </li>
            </ul>
          </div>
          <div v-show="activeTab === 'activities'" class="st-modal__pane">
            <h4>{{ paneTitle('activities', 'Activities & Student Life') }}</h4>
            <ul class="st-modal__list">
              <li v-for="item in stage.activities" :key="item">
                <i class="bi bi-star"></i>
                {{ item }}
              </li>
            </ul>
          </div>
          <div v-show="activeTab === 'schedule'" class="st-modal__pane">
            <h4>{{ paneTitle('schedule', 'Daily Schedule') }}</h4>
            <ul class="st-modal__schedule">
              <li v-for="row in stage.schedule" :key="row.time">
                <span class="st-modal__schedule-time">{{ row.time }}</span>
                <span>{{ row.activity }}</span>
              </li>
            </ul>
          </div>
          <div v-show="activeTab === 'outcomes'" class="st-modal__pane">
            <h4>{{ paneTitle('outcomes', 'Learning Outcomes') }}</h4>
            <ul class="st-modal__list">
              <li v-for="item in stage.learningOutcomes" :key="item">
                <i class="bi bi-mortarboard"></i>
                {{ item }}
              </li>
            </ul>
          </div>
          <div v-show="activeTab === 'gallery'" class="st-modal__pane">
            <div class="st-modal__gallery">
              <img v-for="(img, i) in stage.gallery" :key="i" :src="img" :alt="`${stage.title} ${i + 1}`" loading="lazy" />
            </div>
          </div>
          <div v-show="activeTab === 'teachers'" class="st-modal__pane">
            <h4>{{ paneTitle('teachers', 'Our Educators') }}</h4>
            <p>{{ stage.teachers }}</p>
          </div>
          <div v-show="activeTab === 'faq'" class="st-modal__pane">
            <h4>{{ paneTitle('faq', 'Parent FAQ') }}</h4>
            <div class="st-modal__faq">
              <details v-for="item in stage.parentFaq" :key="item.q">
                <summary>{{ item.q }}</summary>
                <p>{{ item.a }}</p>
              </details>
            </div>
          </div>
          <div v-show="activeTab === 'admission'" class="st-modal__pane">
            <h4>{{ paneTitle('admission', 'Admission Requirements') }}</h4>
            <ul class="st-modal__list">
              <li v-for="item in stage.admission" :key="item">
                <i class="bi bi-file-earmark-text"></i>
                {{ item }}
              </li>
            </ul>
          </div>
        </div>

        <div class="st-modal__footer">
          <a :href="applyCta.href" class="st-btn st-btn--gold" @click="close">{{ applyCta.label }}</a>
          <a :href="visitCta.href" class="st-btn st-btn--outline" @click="close">{{ visitCta.label }}</a>
          <button type="button" class="st-btn st-btn--ghost-muted" @click="close">{{ closeLabel }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

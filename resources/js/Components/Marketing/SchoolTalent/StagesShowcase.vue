<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useStCarousel } from '@/composables/useStCarousel'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'

const { stages, stageShowcaseLabels } = useWebsiteContent()

const emit = defineEmits(['explore'])

const len = computed(() => stages.value?.length ?? 0)
const { index, next, prev, goTo, onTouchStart, onTouchEnd, pause, resume } = useStCarousel(len, {
  autoplayMs: 8000,
})

const stageIcons = {
  'early-years': 'bi-balloon-heart',
  primary: 'bi-book',
  preparatory: 'bi-compass',
  secondary: 'bi-mortarboard',
}

function displayTitle(stage) {
  const labels = stageShowcaseLabels.value ?? {}
  return labels[stage.id] ?? stage.title
}

function explore(stage) {
  emit('explore', stage)
}
</script>

<template>
  <div
    class="st-stages-showcase st-reveal"
    @mouseenter="pause"
    @mouseleave="resume"
    @touchstart.passive="onTouchStart"
    @touchend.passive="onTouchEnd"
  >
    <div class="st-stages-showcase__tabs" role="tablist" aria-label="School stages">
      <button
        v-for="(stage, i) in stages"
        :key="'tab-' + stage.id"
        type="button"
        role="tab"
        class="st-stages-showcase__tab"
        :class="{ active: i === index }"
        :aria-selected="i === index"
        @click="goTo(i)"
      >
        {{ displayTitle(stage) }}
      </button>
    </div>

    <div class="st-stages-showcase__viewport">
      <div
        v-for="(stage, i) in stages"
        :key="stage.id"
        class="st-stages-showcase__panel"
        :class="{ 'st-stages-showcase__panel--active': i === index }"
        role="tabpanel"
      >
        <div class="st-stages-showcase__card">
          <div class="st-stages-showcase__visual">
            <SchoolTalentMediaImage :image="stage.image" :alt="stage.image?.alt || displayTitle(stage)" />
            <div class="st-stages-showcase__visual-overlay" aria-hidden="true"></div>
            <span class="st-stages-showcase__badge">
              <i :class="['bi', stageIcons[stage.id] || 'bi-mortarboard']"></i>
              {{ stage.ageRange }}
            </span>
          </div>
          <div class="st-stages-showcase__body">
            <span class="st-eyebrow">{{ displayTitle(stage) }}</span>
            <h3>{{ stage.tagline }}</h3>
            <p class="st-stages-showcase__overview">{{ stage.overview }}</p>
            <div class="st-stages-showcase__skills">
              <span class="st-stages-showcase__skills-label">Key skills</span>
              <span v-for="skill in stage.keySkills" :key="skill" class="st-stages-showcase__skill">{{ skill }}</span>
            </div>
            <ul class="st-stages-showcase__activities">
              <li v-for="act in stage.activities.slice(0, 4)" :key="act">
                <i class="bi bi-check-circle-fill"></i>
                {{ act }}
              </li>
            </ul>
            <button type="button" class="st-btn st-btn--gold st-btn--lift" @click="explore(stage)">
              Explore {{ displayTitle(stage) }}
              <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="st-carousel-controls">
      <button type="button" class="st-carousel-controls__btn" aria-label="Previous stage" @click="prev">
        <i class="bi bi-chevron-left"></i>
      </button>
      <div class="st-carousel-dots" role="presentation">
        <button
          v-for="(stage, i) in stages"
          :key="'sdot-' + stage.id"
          type="button"
          class="st-carousel-dots__dot"
          :class="{ active: i === index }"
          :aria-label="displayTitle(stage)"
          @click="goTo(i)"
        ></button>
      </div>
      <button type="button" class="st-carousel-controls__btn" aria-label="Next stage" @click="next">
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

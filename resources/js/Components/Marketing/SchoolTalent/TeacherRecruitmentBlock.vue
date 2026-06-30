<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useCta } from '@/composables/useCta'
import { useUiLabel } from '@/composables/useUiLabel'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'

const { teacherRecruitment, schoolInfo } = useWebsiteContent()
const { resolveCta } = useCta()
const { l } = useUiLabel()

const applyHref = computed(() => `mailto:${schoolInfo.value?.contact?.careersEmail}?subject=Teacher%20Application%20-%20School%20Talent`)
const positionsCta = computed(() => resolveCta('teacher'))
</script>

<template>
  <section class="st-section st-section--soft-blue st-recruitment" id="careers">
    <div class="st-section-shape st-section-shape--2" aria-hidden="true"></div>
    <div class="st-container">
      <div class="st-recruitment__grid st-reveal">
        <div class="st-recruitment__visual">
          <SchoolTalentMediaImage
            :image="teacherRecruitment.image"
            :alt="teacherRecruitment.image?.alt || 'School Talent faculty'"
          />
          <div class="st-recruitment__visual-glow" aria-hidden="true"></div>
        </div>
        <div class="st-recruitment__content">
          <span class="st-eyebrow">{{ teacherRecruitment.eyebrow }}</span>
          <h2 class="st-section-title">{{ teacherRecruitment.title }}</h2>
          <p class="st-recruitment__intro">{{ teacherRecruitment.intro }}</p>

          <ul class="st-recruitment__benefits">
            <li v-for="b in teacherRecruitment.benefits" :key="b">
              <i class="bi bi-check-circle-fill"></i>
              {{ b }}
            </li>
          </ul>

          <div class="st-recruitment__highlights">
            <div v-for="h in teacherRecruitment.highlights" :key="h.title" class="st-recruitment__highlight">
              <span class="st-recruitment__highlight-icon"><i :class="['bi', h.icon]"></i></span>
              <div>
                <strong>{{ h.title }}</strong>
                <p>{{ h.text }}</p>
              </div>
            </div>
          </div>

          <p class="st-recruitment__vacancies">
            <strong>{{ l('global.open_positions', 'Open positions:') }}</strong>
            {{ teacherRecruitment.vacancies.join(' · ') }}
          </p>

          <div class="st-recruitment__actions">
            <a :href="applyHref" class="st-btn st-btn--gold st-btn--lift">
              {{ teacherRecruitment.applyLabel }}
            </a>
            <a :href="positionsCta.href" class="st-btn st-btn--outline st-btn--lift">
              {{ teacherRecruitment.positionsLabel || positionsCta.label }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useStCarousel } from '@/composables/useStCarousel'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'

const { events } = useWebsiteContent()

const len = computed(() => events.value?.length ?? 0)
const { index, next, prev, goTo, onTouchStart, onTouchEnd, pause, resume } = useStCarousel(len, {
  autoplayMs: 6000,
})
</script>

<template>
  <div
    class="st-events-carousel st-reveal"
    @mouseenter="pause"
    @mouseleave="resume"
    @touchstart.passive="onTouchStart"
    @touchend.passive="onTouchEnd"
  >
    <div class="st-events-carousel__viewport">
      <article
        v-for="(e, i) in events"
        :key="e.id"
        class="st-events-carousel__slide"
        :class="{
          'st-events-carousel__slide--active': i === index,
          'st-event-card--open-day': e.isOpenDay,
        }"
      >
        <div class="st-events-carousel__card st-event-card st-lift">
          <div class="st-event-card__img">
            <SchoolTalentMediaImage :image="e.image" :alt="e.image?.alt || e.title" />
            <span class="st-event-card__type">{{ e.type }}</span>
            <span v-if="e.isOpenDay && e.limitedSeatsLabel" class="st-event-card__seats">
              {{ e.limitedSeatsLabel }}
            </span>
          </div>
          <div class="st-event-card__body">
            <time class="st-event-card__date"><i class="bi bi-calendar3"></i> {{ e.date }}</time>
            <h3>{{ e.title }}</h3>
            <p class="st-event-card__audience"><i class="bi bi-people"></i> {{ e.audience }}</p>
            <p class="st-event-card__location"><i class="bi bi-geo-alt"></i> {{ e.location }}</p>
            <a :href="e.href" class="st-btn st-btn--gold st-btn--sm st-btn--lift">{{ e.cta }}</a>
          </div>
        </div>
      </article>
    </div>

    <div class="st-carousel-controls">
      <button type="button" class="st-carousel-controls__btn" aria-label="Previous event" @click="prev">
        <i class="bi bi-chevron-left"></i>
      </button>
      <div class="st-carousel-dots" role="tablist" aria-label="Events">
        <button
          v-for="(e, i) in events"
          :key="'dot-' + e.id"
          type="button"
          class="st-carousel-dots__dot"
          :class="{ active: i === index }"
          :aria-label="`Go to ${e.title}`"
          :aria-selected="i === index"
          role="tab"
          @click="goTo(i)"
        ></button>
      </div>
      <button type="button" class="st-carousel-controls__btn" aria-label="Next event" @click="next">
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

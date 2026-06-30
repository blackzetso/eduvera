<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useStCarousel } from '@/composables/useStCarousel'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'

const { studentSuccessStories } = useWebsiteContent()

const len = computed(() => studentSuccessStories.value?.length ?? 0)
const { index, next, prev, goTo, onTouchStart, onTouchEnd, pause, resume } = useStCarousel(len, {
  autoplayMs: 5000,
})
</script>

<template>
  <section class="st-section st-section--white st-success-stories" id="success-stories">
    <div class="st-section-shape st-section-shape--1" aria-hidden="true"></div>
    <div class="st-container">
      <div class="st-section__head st-reveal">
        <span class="st-eyebrow">Student Success</span>
        <h2>Stories of Excellence</h2>
        <p>Scholarships, competitions, university placements, and achievements that define our community.</p>
      </div>

      <div
        class="st-success-carousel st-reveal"
        @mouseenter="pause"
        @mouseleave="resume"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
      >
        <div class="st-success-carousel__track">
          <article
            v-for="(story, i) in studentSuccessStories"
            :key="story.id"
            class="st-success-card"
            :class="{ 'st-success-card--active': i === index }"
          >
            <div class="st-success-card__img">
              <SchoolTalentMediaImage :image="story.image" :alt="story.title" />
            </div>
            <div class="st-success-card__body">
              <span class="st-success-card__cat"><i :class="['bi', story.icon]"></i> {{ story.category }}</span>
              <div class="st-success-card__stat">
                <strong>{{ story.stat }}</strong>
                <span>{{ story.statLabel }}</span>
              </div>
              <h3>{{ story.title }}</h3>
              <p>{{ story.text }}</p>
            </div>
          </article>
        </div>

        <div class="st-carousel-controls">
          <button type="button" class="st-carousel-controls__btn" aria-label="Previous story" @click="prev">
            <i class="bi bi-chevron-left"></i>
          </button>
          <div class="st-carousel-dots">
            <button
              v-for="(story, i) in studentSuccessStories"
              :key="'sd-' + story.id"
              type="button"
              class="st-carousel-dots__dot"
              :class="{ active: i === index }"
              :aria-label="story.title"
              @click="goTo(i)"
            ></button>
          </div>
          <button type="button" class="st-carousel-controls__btn" aria-label="Next story" @click="next">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

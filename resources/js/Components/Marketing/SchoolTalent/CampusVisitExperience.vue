<script setup>
import BookVisitForm from '@/Components/Marketing/SchoolTalent/BookVisitForm.vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'

const { visitCampusReasons, campusVisit } = useWebsiteContent()
</script>

<template>
  <div class="st-campus-visit">
    <div class="st-campus-visit__hero">
      <div class="st-campus-visit__hero-pattern" aria-hidden="true"></div>
      <div class="st-campus-visit__hero-inner">
        <span class="st-campus-visit__badge">
          <i class="bi bi-calendar2-check"></i>
          {{ campusVisit.hero_badge || 'Campus Tours' }}
        </span>
        <h3 class="st-campus-visit__hero-title">
          {{ campusVisit.form_title || 'Book Your Campus Visit' }}
        </h3>
        <p class="st-campus-visit__hero-lead">
          {{ campusVisit.lead || 'See our school in person and meet our admissions team.' }}
        </p>
      </div>
    </div>

    <ul class="st-campus-visit__chips">
      <li v-for="r in visitCampusReasons" :key="r.text">
        <i :class="['bi', r.icon]"></i>
        <span>{{ r.text }}</span>
      </li>
    </ul>

    <div class="st-campus-visit__form-wrap">
      <p v-if="campusVisit.form_subtitle" class="st-campus-visit__form-note">
        {{ campusVisit.form_subtitle }}
      </p>
      <BookVisitForm />
    </div>
  </div>
</template>

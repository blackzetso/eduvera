<script setup>
import { computed, provide } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import SchoolTalentLayout from '@/Layouts/SchoolTalentLayout.vue'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'
import { useLocale } from '@/composables/useLocale'

const props = defineProps({
  article: Object,
  type: String,
  websiteContent: Object,
  websiteSeo: Object,
})

provide('websiteContent', computed(() => props.websiteContent))

const { locale } = useLocale()
const backLabel = computed(() => (locale.value === 'ar' ? '← العودة للرئيسية' : '← Back to home'))
</script>

<template>
  <Head :title="websiteSeo?.meta_title || article?.title" />
  <SchoolTalentLayout>
    <article class="st-section st-section--white py-5">
      <div class="st-container" style="max-width: 720px">
        <Link href="/" class="small text-muted mb-3 d-inline-block">{{ backLabel }}</Link>
        <span class="st-eyebrow d-block">{{ article.category }}</span>
        <h1 class="st-section-title">{{ article.title }}</h1>
        <time class="text-muted small">{{ article.publishedAt || article.date }}</time>
        <SchoolTalentMediaImage v-if="article.image" :image="article.image" :alt="article.title" img-class="img-fluid rounded my-4" />
        <p class="lead">{{ article.excerpt }}</p>
        <div v-if="article.content" class="article-body" v-html="article.content" />
        <p v-else class="text-muted">{{ article.excerpt }}</p>
      </div>
    </article>
  </SchoolTalentLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useUiLabel } from '@/composables/useUiLabel'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'

const { getNewsBlogFeed } = useWebsiteContent()
const { l } = useUiLabel()

const feed = computed(() => getNewsBlogFeed())
const featured = computed(() => feed.value.find((i) => i.isFeatured) ?? feed.value[0])
const secondary = computed(() => feed.value.filter((i) => i.id !== featured.value?.id).slice(0, 4))
</script>

<template>
  <div class="st-news-blog st-reveal">
    <article v-if="featured" class="st-news-blog__featured st-lift">
      <a :href="featured.url" class="st-news-blog__featured-link">
        <div class="st-news-blog__featured-img">
          <SchoolTalentMediaImage :image="featured.image" :alt="featured.title" />
          <span class="st-news-blog__badge">{{ featured.category }}</span>
        </div>
        <div class="st-news-blog__featured-body">
          <time>{{ featured.publishedAt || featured.date }}</time>
          <h3>{{ featured.title }}</h3>
          <p>{{ featured.excerpt }}</p>
          <span class="st-news-blog__read">{{ l('global.read_more', 'Read more') }} <i class="bi bi-arrow-right"></i></span>
        </div>
      </a>
    </article>

    <div class="st-news-blog__secondary">
      <article v-for="item in secondary" :key="item.id" class="st-news-blog__card st-lift">
        <a :href="item.url" class="st-news-blog__card-link">
          <div class="st-news-blog__card-img">
            <SchoolTalentMediaImage :image="item.image" :alt="item.title" />
            <span class="st-news-blog__badge st-news-blog__badge--sm">{{ item.category }}</span>
          </div>
          <div class="st-news-blog__card-body">
            <time>{{ item.publishedAt || item.date }}</time>
            <h4>{{ item.title }}</h4>
            <span class="st-news-blog__read">{{ l('global.read_more', 'Read more') }} <i class="bi bi-arrow-right"></i></span>
          </div>
        </a>
      </article>
    </div>
  </div>
</template>

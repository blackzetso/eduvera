<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const now = ref(new Date())
let timer = null

const formattedDate = computed(() =>
  now.value.toLocaleString('ar-EG', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }),
)

onMounted(() => {
  timer = setInterval(() => { now.value = new Date() }, 30000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <header class="pos-header">
    <div class="pos-header__user">
      <div class="pos-header__avatar">
        <i class="bi bi-person-fill"></i>
      </div>
      <div class="min-w-0">
        <div class="pos-header__name">{{ user?.name ?? 'الكاشير' }}</div>
        <div class="pos-header__datetime">{{ formattedDate }}</div>
      </div>
    </div>

    <nav class="pos-header__nav">
      <Link :href="route('canteen.dashboard')" class="pos-header__nav-btn" title="الرئيسية">
        <i class="bi bi-house-door"></i>
      </Link>
      <span class="pos-header__nav-btn active" title="نقطة البيع">
        <i class="bi bi-cart3"></i>
      </span>
      <Link :href="route('canteen.reports.index')" class="pos-header__nav-btn" title="التقارير">
        <i class="bi bi-receipt"></i>
      </Link>
    </nav>
  </header>
</template>

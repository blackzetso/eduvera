<script setup>
import AppLayout from '@/Pages/Student/Theme1/Layout/App.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'

const authUser = usePage().props.auth?.user ?? { name: '', email: '' }

const props = defineProps({
    stream: { type: Object, required: true },
})

// â”€â”€â”€ Active tab â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const activeTab = ref('overview')

// â”€â”€â”€ Reactive status (polls server every 8 s) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const currentStatus = ref(props.stream.status)
const nowMs         = ref(Date.now())
let   tickTimer     = null
let   pollTimer     = null

const startDate = props.stream.start_datetime
    ? new Date(props.stream.start_datetime.replace(' ', 'T'))
    : null

const secondsUntilStart = computed(() =>
    startDate ? Math.max(0, Math.round((startDate.getTime() - nowMs.value) / 1000)) : 0
)
const beforeStart = computed(() => secondsUntilStart.value > 0)
const isLive      = computed(() => currentStatus.value === 'live')
const isEnded     = computed(() => currentStatus.value === 'ended')
const isScheduled = computed(() => currentStatus.value === 'scheduled')

const countdownDisplay = computed(() => {
    const s   = secondsUntilStart.value
    const h   = Math.floor(s / 3600)
    const m   = Math.floor((s % 3600) / 60)
    const sec = s % 60
    const pad = n => String(n).padStart(2, '0')
    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`
})

async function pollStatus() {
    try {
        const res  = await fetch(`/join/${props.stream.id}/status`, { headers: { Accept: 'application/json' } })
        const data = await res.json()
        currentStatus.value = data.status
    } catch (_) { /* silent */ }
}

onMounted(() => {
    tickTimer = setInterval(() => { nowMs.value = Date.now() }, 1000)
    pollTimer = setInterval(() => {
        if (currentStatus.value !== 'live' && currentStatus.value !== 'ended') pollStatus()
    }, 8000)
    loadReviews()
    loadComments()
})
onUnmounted(() => {
    clearInterval(tickTimer)
    clearInterval(pollTimer)
})

// â”€â”€â”€ Colours â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const GRADIENTS = [
    ['#1a1a2e', '#0f3460'],
    ['#0d2137', '#1b6ca8'],
    ['#1a0533', '#6a0dad'],
    ['#0c2340', '#1e5f74'],
    ['#1e0a00', '#b34700'],
    ['#001a12', '#00704a'],
    ['#1a0020', '#8b0049'],
    ['#00151f', '#005f87'],
]
const heroGradient = computed(() => {
    const [from, to] = GRADIENTS[props.stream.id % GRADIENTS.length]
    return `linear-gradient(135deg, ${from} 0%, ${to} 100%)`
})

function formatDate(dt) {
    if (!dt) return ''
    const d = new Date(dt.replace(' ', 'T'))
    return d.toLocaleString('en-GB', {
        weekday: 'long', year: 'numeric', month: 'long',
        day: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

function formatDateShort(dt) {
    if (!dt) return ''
    const d = new Date(dt.replace(' ', 'T'))
    return d.toLocaleString('en-GB', {
        year: 'numeric', month: 'short',
        day: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

// ─── Reviews ──────────────────────────────────────────────────────────────────
const reviews        = ref([])
const reviewsAvg     = ref(0)
const reviewsCount   = ref(0)
const reviewsLoading = ref(false)

const reviewForm       = ref({ rating: 5, body: '' })
const reviewSubmitting = ref(false)
const reviewError      = ref('')
const reviewSuccess    = ref(false)

// percentage per star level (5→1)
const ratingBars = computed(() => {
    const total = reviews.value.length || 1
    return [5, 4, 3, 2, 1].map(star => {
        const count = reviews.value.filter(r => r.rating === star).length
        return Math.round((count / total) * 100)
    })
})

async function loadReviews() {
    reviewsLoading.value = true
    try {
        const res  = await fetch(`/student/live-streams/${props.stream.id}/reviews`, { headers: { Accept: 'application/json' } })
        const data = await res.json()
        reviews.value      = data.reviews
        reviewsAvg.value   = data.avg
        reviewsCount.value = data.count
    } catch (_) { /* silent */ } finally {
        reviewsLoading.value = false
    }
}

async function submitReview() {
    reviewError.value   = ''
    reviewSuccess.value = false
    if (!reviewForm.value.body.trim()) {
        reviewError.value = 'Review text is required.'
        return
    }
    reviewSubmitting.value = true
    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]')
        const res = await fetch(`/student/live-streams/${props.stream.id}/reviews`, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '',
            },
            body: JSON.stringify({
                reviewer_name:  authUser.name,
                reviewer_email: authUser.email,
                rating:         reviewForm.value.rating,
                body:           reviewForm.value.body,
            }),
        })
        if (!res.ok) {
            const err = await res.json()
            reviewError.value = Object.values(err.errors || {}).flat().join(' ') || 'Failed to submit.'
            return
        }
        const newReview = await res.json()
        reviews.value.unshift(newReview)
        reviewsCount.value++
        reviewsAvg.value = Math.round((reviews.value.reduce((s, r) => s + r.rating, 0) / reviews.value.length) * 10) / 10
        reviewForm.value = { rating: 5, body: '' }
        reviewSuccess.value = true
    } catch (_) {
        reviewError.value = 'An unexpected error occurred. Please try again.'
    } finally {
        reviewSubmitting.value = false
    }
}

function starIcon(pos, rating) {
    if (rating >= pos)       return 'fas fa-star text-warning'
    if (rating >= pos - 0.5) return 'fas fa-star-half-alt text-warning'
    return 'far fa-star text-warning'
}

// ─── Comments ─────────────────────────────────────────────────────────────────
const comments         = ref([])
const commentsLoading  = ref(false)
const commentForm      = ref({ body: '' })
const commentSubmitting = ref(false)
const commentError     = ref('')

// reply state: keyed by comment id
const replyOpen = ref({})   // { [id]: bool }
const replyForms = ref({})  // { [id]: { body } }
const replySubmitting = ref({})

function toggleReply(id) {
    replyOpen.value[id] = !replyOpen.value[id]
    if (!replyForms.value[id]) replyForms.value[id] = { body: '' }
}

async function loadComments() {
    commentsLoading.value = true
    try {
        const res  = await fetch(`/student/live-streams/${props.stream.id}/comments`, { headers: { Accept: 'application/json' } })
        const data = await res.json()
        comments.value = data.comments
    } catch (_) { /* silent */ } finally {
        commentsLoading.value = false
    }
}

async function submitComment() {
    commentError.value = ''
    if (!commentForm.value.body.trim()) {
        commentError.value = 'Comment text is required.'
        return
    }
    commentSubmitting.value = true
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        const res  = await fetch(`/student/live-streams/${props.stream.id}/comments`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    JSON.stringify({
                author_name:  authUser.name,
                author_email: authUser.email,
                body:         commentForm.value.body,
            }),
        })
        if (!res.ok) {
            const err = await res.json()
            commentError.value = Object.values(err.errors || {}).flat().join(' ') || 'Failed to post.'
            return
        }
        const newComment = await res.json()
        comments.value.unshift(newComment)
        commentForm.value = { body: '' }
    } catch (_) {
        commentError.value = 'An unexpected error occurred.'
    } finally {
        commentSubmitting.value = false
    }
}

async function submitReply(commentId) {
    const form = replyForms.value[commentId]
    if (!form?.body?.trim()) return
    replySubmitting.value[commentId] = true
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        const res  = await fetch(`/student/live-streams/${props.stream.id}/comments/${commentId}/replies`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    JSON.stringify({
                author_name:  authUser.name,
                author_email: authUser.email,
                body:         form.body,
            }),
        })
        if (!res.ok) return
        const reply = await res.json()
        const target = comments.value.find(c => c.id === commentId)
        if (target) target.replies.push(reply)
        replyForms.value[commentId] = { body: '' }
        replyOpen.value[commentId] = false
    } catch (_) { /* silent */ } finally {
        replySubmitting.value[commentId] = false
    }
}
</script>

<template>
    <Head :title="stream.title" />
    <AppLayout>

        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
             PAGE INTRO
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section class="bg-light py-0 py-sm-5">
            <div class="container">
                <div class="row py-5">
                    <div class="col-lg-8">

                        <!-- Subject badge -->
                        <h6 class="mb-3 font-base bg-primary text-white py-2 px-4 rounded-2 d-inline-block">
                            {{ stream.subject || 'Live Stream' }}
                        </h6>

                        <!-- Status badge -->
                        <span v-if="isLive"
                            class="badge bg-danger ms-2 px-3 py-2 d-inline-flex align-items-center gap-1"
                            style="font-size:13px;vertical-align:middle;">
                            <span class="live-dot"></span>Live Now
                        </span>
                        <span v-else-if="isScheduled"
                            class="badge bg-warning text-dark ms-2 px-3 py-2 d-inline-flex align-items-center gap-1"
                            style="font-size:13px;vertical-align:middle;">
                            <i class="bi bi-calendar-event"></i>Scheduled
                        </span>
                        <span v-else-if="isEnded"
                            class="badge bg-secondary ms-2 px-3 py-2"
                            style="font-size:13px;vertical-align:middle;">
                            <i class="bi bi-check-circle me-1"></i>Ended
                        </span>

                        <!-- Title -->
                        <h1 class="mt-2">{{ stream.title }}</h1>

                        <!-- Short description -->
                        <p v-if="stream.description" class="mb-3">
                            {{ stream.description.replace(/<[^>]*>/g, '').slice(0, 160) }}{{ stream.description.length > 160 ? 'â€¦' : '' }}
                        </p>

                        <!-- Meta row -->
                        <ul class="list-inline mb-0">
                            <li class="list-inline-item h6 me-3 mb-1 mb-sm-0">
                                <i class="fas fa-chalkboard-teacher text-primary me-2"></i>{{ stream.teacher_name }}
                            </li>
                            <li v-if="stream.start_datetime" class="list-inline-item h6 me-3 mb-1 mb-sm-0">
                                <i class="bi bi-calendar-event text-orange me-2"></i>{{ formatDateShort(stream.start_datetime) }}
                            </li>
                            <li v-if="stream.end_datetime && isLive" class="list-inline-item h6 mb-0">
                                <i class="bi bi-alarm text-danger me-2"></i>Ends {{ formatDateShort(stream.end_datetime) }}
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
        </section>

        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
             TWO-COLUMN LAYOUT
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section class="pb-0 py-lg-5">
            <div class="container">
                <div class="row">

                    <!-- â”€â”€ LEFT COLUMN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                    <div class="col-lg-8">
                        <div class="card shadow rounded-2 p-0">

                            <!-- Tab nav -->
                            <div class="card-header border-bottom px-4 py-3">
                                <ul class="nav nav-pills nav-tabs-line py-0" role="tablist">
                                    <li class="nav-item me-2 me-sm-4" role="presentation">
                                        <button class="nav-link mb-2 mb-md-0" :class="{ active: activeTab === 'overview' }"
                                            type="button" @click="activeTab = 'overview'">Overview</button>
                                    </li>
                                    <li class="nav-item me-2 me-sm-4" role="presentation">
                                        <button class="nav-link mb-2 mb-md-0" :class="{ active: activeTab === 'instructor' }"
                                            type="button" @click="activeTab = 'instructor'">Instructor</button>
                                    </li>
                                    <li class="nav-item me-2 me-sm-4" role="presentation">
                                        <button class="nav-link mb-2 mb-md-0" :class="{ active: activeTab === 'reviews' }"
                                            type="button" @click="activeTab = 'reviews'">Reviews</button>
                                    </li>
                                    <li class="nav-item me-2 me-sm-4" role="presentation">
                                        <button class="nav-link mb-2 mb-md-0" :class="{ active: activeTab === 'comment' }"
                                            type="button" @click="activeTab = 'comment'">Comment</button>
                                    </li>
                                </ul>
                            </div>

                            <!-- Tab content -->
                            <div class="card-body p-4">

                                <!-- â”€â”€ OVERVIEW â”€â”€ -->
                                <div v-show="activeTab === 'overview'">
                                    <h5 class="mb-3">Stream Description</h5>
                                    <div v-if="stream.description" v-html="stream.description" class="mb-3"></div>
                                    <p v-else class="text-muted mb-3">No description available for this session.</p>

                                    <h5 class="mt-4">What you'll learn</h5>
                                    <ul class="list-group list-group-borderless mb-3">
                                        <template v-if="stream.learning_points && stream.learning_points.length">
                                            <li v-for="(point, i) in stream.learning_points" :key="i" class="list-group-item h6 fw-light d-flex mb-0">
                                                <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                {{ point }}
                                            </li>
                                        </template>
                                        <li v-else class="list-group-item h6 fw-light d-flex mb-0 text-muted">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            Real-time interactive live session with the instructor
                                        </li>
                                    </ul>
                                </div>

                                <!-- â”€â”€ INSTRUCTOR â”€â”€ -->
                                <div v-show="activeTab === 'instructor'">
                                    <!-- Instructor card -->
                                    <div class="card mb-4">
                                        <div class="row g-0 align-items-center">
                                            <div class="col-md-4 text-center p-4">
                                                <div class="rounded-3 d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10"
                                                    style="width:130px;height:160px;">
                                                    <i class="bi bi-person-fill text-primary" style="font-size:5rem;"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="card-body">
                                                    <h3 class="card-title mb-0">{{ stream.teacher_name }}</h3>
                                                    <p class="mb-2 text-muted">Instructor{{ stream.subject ? ' â€” ' + stream.subject : '' }}</p>
                                                    <ul class="list-inline mb-0">
                                                        <li class="list-inline-item">
                                                            <div class="d-flex align-items-center me-3 mb-2">
                                                                <span class="icon-md bg-primary bg-opacity-10 text-primary rounded-circle">
                                                                    <i class="bi bi-broadcast"></i>
                                                                </span>
                                                                <span class="h6 fw-light mb-0 ms-2">Live Session</span>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <h5 class="mb-3">About Instructor</h5>
                                    <p class="mb-3">
                                        {{ stream.teacher_name }} is conducting this live session{{ stream.subject ? ' for ' + stream.subject : '' }}.
                                        Join now to benefit from direct interaction and real-time instruction.
                                    </p>

                                    <div class="col-12">
                                        <ul class="list-group list-group-borderless mb-0">
                                            <li v-if="stream.teacher_email" class="list-group-item d-flex align-items-center gap-2 px-0">
                                                <span class="h6 fw-light mb-0">Mail ID:</span>
                                                <a :href="'mailto:' + stream.teacher_email" class="text-primary">
                                                    {{ stream.teacher_email }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Reviews Tab -->
                                <div v-show="activeTab === 'reviews'">
    <div v-if="reviewsLoading" class="text-center py-4 text-muted">
        <div class="spinner-border spinner-border-sm me-2"></div> Loading reviews...
    </div>
    <template v-else>
        <!-- Rating summary -->
        <div class="row mb-4">
            <h5 class="mb-4">Our Student Reviews</h5>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="text-center">
                    <h2 class="mb-0">{{ reviewsCount ? reviewsAvg : '—' }}</h2>
                    <ul class="list-inline mb-0">
                        <li v-for="pos in [1,2,3,4,5]" :key="pos" class="list-inline-item me-0">
                            <i :class="starIcon(pos, reviewsAvg)"></i>
                        </li>
                    </ul>
                    <p class="mb-0">({{ reviewsCount }} review{{ reviewsCount !== 1 ? 's' : '' }})</p>
                </div>
            </div>
            <div class="col-md-8">
                <div class="row align-items-center">
                    <template v-for="(pct, idx) in ratingBars" :key="idx">
                        <div class="col-6 col-sm-8">
                            <div class="progress progress-sm bg-warning bg-opacity-15">
                                <div class="progress-bar bg-warning" :style="{ width: pct + '%' }"></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4">
                            <ul class="list-inline mb-0">
                                <li v-for="pos in [1,2,3,4,5]" :key="pos" class="list-inline-item me-0 small">
                                    <i :class="pos <= (5-idx) ? 'fas fa-star text-warning' : 'far fa-star text-warning'"></i>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Reviews list -->
        <div class="row">
            <template v-if="reviews.length">
                <template v-for="(review, i) in reviews" :key="review.id">
                    <div class="d-md-flex my-4">
                        <div class="avatar avatar-xl me-4 flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                <i class="bi bi-person-fill text-primary fs-3"></i>
                            </div>
                        </div>
                        <div>
                            <div class="d-sm-flex mt-1 mt-md-0 align-items-center">
                                <h5 class="me-3 mb-0">{{ review.reviewer_name }}</h5>
                                <ul class="list-inline mb-0">
                                    <li v-for="pos in [1,2,3,4,5]" :key="pos" class="list-inline-item me-0">
                                        <i :class="pos <= review.rating ? 'fas fa-star text-warning' : 'far fa-star text-warning'"></i>
                                    </li>
                                </ul>
                            </div>
                            <p class="small mb-2">{{ review.created_at }}</p>
                            <p class="mb-0">{{ review.body }}</p>
                        </div>
                    </div>
                    <hr v-if="i < reviews.length - 1">
                </template>
            </template>
            <div v-else class="text-center py-4 text-muted">
                <i class="fas fa-star fa-2x mb-2 opacity-25 d-block"></i>
                No reviews yet. Be the first to leave one!
            </div>
        </div>

        <!-- Leave a Review -->
        <div class="mt-4">
            <h5 class="mb-4">Leave a Review</h5>
            <div v-if="reviewSuccess" class="alert alert-success mb-3">
                <i class="fas fa-check-circle me-2"></i>Your review has been posted. Thank you!
            </div>
            <div v-if="reviewError" class="alert alert-danger mb-3">{{ reviewError }}</div>
            <form class="row g-3" @submit.prevent="submitReview">
                <div class="col-12 bg-light-input">
                    <select v-model.number="reviewForm.rating" class="form-select">
                        <option :value="5">&#9733;&#9733;&#9733;&#9733;&#9733; (5/5)</option>
                        <option :value="4">&#9733;&#9733;&#9733;&#9733;&#9734; (4/5)</option>
                        <option :value="3">&#9733;&#9733;&#9733;&#9734;&#9734; (3/5)</option>
                        <option :value="2">&#9733;&#9733;&#9734;&#9734;&#9734; (2/5)</option>
                        <option :value="1">&#9733;&#9734;&#9734;&#9734;&#9734; (1/5)</option>
                    </select>
                </div>
                <div class="col-12 bg-light-input">
                    <textarea v-model="reviewForm.body" class="form-control" placeholder="Share your experience with this live session..." rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary mb-0" :disabled="reviewSubmitting">
                        <span v-if="reviewSubmitting">
                            <span class="spinner-border spinner-border-sm me-1"></span>Posting...
                        </span>
                        <span v-else>Post Review</span>
                    </button>
                </div>
            </form>
        </div>
    </template>
</div>

                                <!-- Comment Tab -->
                                <div v-show="activeTab === 'comment'">
                                    <h5 class="mb-4">Ask Your Question</h5>

                                    <!-- Post a comment form -->
                                    <div class="d-flex mb-4">
                                        <div class="me-3 flex-shrink-0">
                                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                                                style="width:44px;height:44px;">
                                                <i class="bi bi-person-fill text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div v-if="commentError" class="alert alert-danger py-2 mb-2 small">{{ commentError }}</div>
                                            <div class="d-flex gap-2">
                                                <textarea v-model="commentForm.body" class="form-control" rows="2" placeholder="Add a comment or ask a question..."></textarea>
                                                <div class="align-self-end">
                                                    <button class="btn btn-primary mb-0" @click="submitComment" :disabled="commentSubmitting">
                                                        <span v-if="commentSubmitting"><span class="spinner-border spinner-border-sm"></span></span>
                                                        <span v-else>Post</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Loading -->
                                    <div v-if="commentsLoading" class="text-center py-3 text-muted">
                                        <div class="spinner-border spinner-border-sm me-2"></div> Loading comments...
                                    </div>

                                    <!-- Empty state -->
                                    <div v-else-if="!comments.length" class="text-center py-4 text-muted">
                                        <i class="fas fa-comment-dots fa-3x mb-3 opacity-25 d-block"></i>
                                        <p class="mb-0">No comments yet. Be the first to ask a question.</p>
                                    </div>

                                    <!-- Comments list -->
                                    <div v-else>
                                        <div v-for="comment in comments" :key="comment.id" class="mb-4">
                                            <!-- Comment -->
                                            <div class="d-flex">
                                                <div class="me-3 flex-shrink-0">
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                        style="width:40px;height:40px;">
                                                        <i class="bi bi-person-fill text-secondary"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="bg-light rounded-3 p-3">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span class="fw-semibold small">{{ comment.author_name }}</span>
                                                            <span class="text-muted" style="font-size:11px;">{{ comment.created_at }}</span>
                                                        </div>
                                                        <p class="mb-0 small">{{ comment.body }}</p>
                                                    </div>
                                                    <button class="btn btn-link btn-sm px-1 text-muted" @click="toggleReply(comment.id)">
                                                        <i class="bi bi-reply me-1"></i>Reply
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Replies -->
                                            <div v-if="comment.replies && comment.replies.length" class="ms-5 mt-2">
                                                <div v-for="reply in comment.replies" :key="reply.id" class="d-flex mb-2">
                                                    <div class="me-2 flex-shrink-0">
                                                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                                                            style="width:32px;height:32px;">
                                                            <i class="bi bi-person-fill text-primary" style="font-size:14px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="bg-white border rounded-3 p-2">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="fw-semibold" style="font-size:12px;">{{ reply.author_name }}</span>
                                                                <span class="text-muted" style="font-size:11px;">{{ reply.created_at }}</span>
                                                            </div>
                                                            <p class="mb-0" style="font-size:13px;">{{ reply.body }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Reply form -->
                                            <div v-if="replyOpen[comment.id]" class="ms-5 mt-2">
                                                <div class="d-flex gap-2">
                                                    <input v-model="replyForms[comment.id].body" type="text" class="form-control form-control-sm" placeholder="Write a reply...">
                                                    <button class="btn btn-sm btn-primary flex-shrink-0" @click="submitReply(comment.id)" :disabled="replySubmitting[comment.id]">
                                                        <span v-if="replySubmitting[comment.id]"><span class="spinner-border spinner-border-sm"></span></span>
                                                        <span v-else>Send</span>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary flex-shrink-0" @click="replyOpen[comment.id] = false">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Back link -->
                        <div class="mt-4 mb-5">
                            <a href="/student/live-streams"
                                class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                                <i class="bi bi-arrow-right-short fs-5"></i>
                                Back to Live Streams
                            </a>
                        </div>
                    </div>

                    <!-- â”€â”€ RIGHT COLUMN: join card â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                    <div class="col-lg-4 pt-5 pt-lg-0">
                        <div class="row mb-5 mb-lg-0">
                            <div class="col-md-6 col-lg-12">
                                <div>

                                <!-- Join card -->
                                <div class="card shadow p-2 mb-4">

                                    <!-- Thumbnail / Gradient -->
                                    <div class="overflow-hidden rounded-3 position-relative">
                                        <img v-if="stream.thumbnail_url" :src="stream.thumbnail_url"
                                            class="w-100 rounded-3" style="height:180px;object-fit:cover;display:block;">
                                        <div v-else :style="{ background: heroGradient }"
                                            class="d-flex align-items-center justify-content-center"
                                            style="height:180px;">
                                            <div class="text-center">
                                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                                    style="width:72px;height:72px;background:rgba(255,255,255,.18);backdrop-filter:blur(6px);">
                                                    <i class="bi bi-broadcast text-white" style="font-size:2.2rem;"></i>
                                                </div>
                                                <div class="text-white fw-semibold mt-2 px-2" style="font-size:14px;">
                                                    {{ stream.subject || stream.title }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="isLive" class="position-absolute top-0 start-0 m-2">
                                            <span class="badge bg-danger d-inline-flex align-items-center gap-1 px-2 py-1">
                                                <span class="live-dot-sm"></span>LIVE
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Card body -->
                                    <div class="card-body px-3">

                                        <!-- LIVE: join button -->
                                        <template v-if="isLive">
                                            <a :href="stream.join_url"
                                                class="btn btn-danger btn-lg w-100 fw-bold d-flex align-items-center justify-content-center gap-2 mb-2 mt-2">
                                                <i class="bi bi-broadcast"></i>
                                                Join Live Now
                                            </a>
                                            <p class="text-center text-muted small mb-0">
                                                <i class="bi bi-shield-check me-1 text-success"></i>
                                                You will be asked for your name upon joining
                                            </p>
                                        </template>

                                        <!-- SCHEDULED: countdown + smart join -->
                                        <template v-else-if="isScheduled">
                                            <!-- Countdown (before start time) -->
                                            <div v-if="beforeStart" class="text-center py-2 mb-2">
                                                <div class="text-muted small mb-1">Session starts in</div>
                                                <div class="fw-bold text-primary"
                                                    style="font-size:2.4rem;letter-spacing:4px;font-variant-numeric:tabular-nums;">
                                                    {{ countdownDisplay }}
                                                </div>
                                            </div>
                                            <!-- After start time but not yet live -->
                                            <div v-if="!beforeStart" class="alert alert-warning text-center mt-2 mb-2 py-2">
                                                <i class="bi bi-hourglass-split me-2"></i>Waiting for the instructor to start...
                                            </div>
                                        </template>

                                        <!-- ENDED -->
                                        <template v-else-if="isEnded">
                                            <a v-if="stream.has_recording" :href="stream.watch_url"
                                                class="btn btn-primary btn-lg w-100 fw-bold d-flex align-items-center justify-content-center gap-2 mb-2 mt-2">
                                                <i class="bi bi-play-circle"></i>
                                                Watch Recording
                                            </a>
                                            <div class="alert alert-secondary text-center mt-2 mb-0">
                                                <i class="bi bi-clock-history me-2"></i>This session has ended
                                            </div>
                                        </template>

                                    </div>
                                </div>

                                </div><!-- /sticky wrapper -->

                                <!-- Stream info card -->
                                <div class="card card-body shadow p-4 mb-4">
                                    <h4 class="mb-3">This session includes</h4>
                                    <ul class="list-group list-group-borderless">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="h6 fw-light mb-0">
                                                <i class="fas fa-fw fa-chalkboard-teacher text-primary"></i>
                                                Instructor
                                            </span>
                                            <span>{{ stream.teacher_name }}</span>
                                        </li>
                                        <li v-if="stream.subject" class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="h6 fw-light mb-0">
                                                <i class="fas fa-fw fa-book-open text-primary"></i>
                                                Subject
                                            </span>
                                            <span>{{ stream.subject }}</span>
                                        </li>
                                        <li v-if="stream.start_datetime" class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="h6 fw-light mb-0">
                                                <i class="far fa-fw fa-calendar text-primary"></i>
                                                Date
                                            </span>
                                            <span class="text-end" style="max-width:130px;font-size:13px;">
                                                {{ formatDateShort(stream.start_datetime) }}
                                            </span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="h6 fw-light mb-0">
                                                <i class="fas fa-fw fa-signal text-primary"></i>
                                                Status
                                            </span>
                                            <span v-if="isLive" class="badge bg-danger">Live</span>
                                            <span v-else-if="isScheduled" class="badge bg-warning text-dark">Scheduled</span>
                                            <span v-else-if="isEnded" class="badge bg-secondary">Ended</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="h6 fw-light mb-0">
                                                <i class="fas fa-fw fa-globe text-primary"></i>
                                                Type
                                            </span>
                                            <span>Live Stream</span>
                                        </li>
                                    </ul>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

    </AppLayout>
</template>

<style scoped>
.live-dot {
    width: 8px; height: 8px;
    background: #fff;
    border-radius: 50%;
    display: inline-block;
    animation: blink 1.2s ease-in-out infinite;
}
.live-dot-sm {
    width: 6px; height: 6px;
    background: #fff;
    border-radius: 50%;
    display: inline-block;
    animation: blink 1.2s ease-in-out infinite;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: .3; }
}
</style>

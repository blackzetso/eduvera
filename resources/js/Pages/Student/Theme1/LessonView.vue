<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Student/Theme1/Layout/App.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    lesson: Object,
})

const selectedFile = ref(null)
const selectedLecture = ref(null)

// Select first file of first lecture by default
if (props.lesson.lectures && props.lesson.lectures.length > 0) {
    const firstLecture = props.lesson.lectures[0]
    selectedLecture.value = firstLecture
    if (firstLecture.files && firstLecture.files.length > 0) {
        selectedFile.value = firstLecture.files[0]
    }
}

function selectFile(file, lecture) {
    selectedFile.value = file
    selectedLecture.value = lecture
}

const videoPlayer = computed(() => {
    if (!selectedFile.value) return null

    const file = selectedFile.value

    // Video Stream
    if (file.type === 'bunny_stream' && file.path) {
        return {
            type: 'video',
            html: `<iframe src="${file.path}" loading="lazy" style="border:none;position:absolute;top:0;height:100%;width:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen="true"></iframe>`
        }
    }

    // YouTube/Vimeo
    if (file.type === 'youtube' && file.embed_code) {
        return {
            type: 'youtube',
            html: file.embed_code
        }
    }

    // External link
    if (file.type === 'external' && file.embed_code) {
        return {
            type: 'external',
            html: file.embed_code
        }
    }

    // Fallback - direct URL
    if (file.url) {
        return {
            type: 'url',
            html: `<iframe src="${file.url}" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>`
        }
    }

    return null
})
</script>

<template>
<Head :title="lesson.name" />
<AppLayout>
    <div class="page-content-wrapper">
        <!-- Lesson Header -->
        <div class="card bg-dark-overlay-4 overflow-hidden card-bg-scale h-300px text-center" :style="lesson.image ? `background-image:url(/storage/${lesson.image}); background-position: center left; background-size: cover;` : ''">
            <div class="card-img-overlay d-flex align-items-center p-3 p-sm-4">
                <div class="w-100 my-auto">
                    <h1 class="text-white display-5">{{ lesson.name }}</h1>
                    <p class="text-white mb-0" v-if="lesson.short_description">{{ lesson.short_description }}</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4 mt-3">
            <!-- Video Player -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="position-relative" style="padding-top: 56.25%; background: #000;">
                            <div v-if="videoPlayer" v-html="videoPlayer.html" style="position:absolute;top:0;left:0;width:100%;height:100%;"></div>
                            <div v-else class="position-absolute top-50 start-50 translate-middle text-white">
                                <i class="bi bi-film fs-1"></i>
                                <p>اختر فيديو من القائمة</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Video Info -->
                    <div v-if="selectedFile" class="card-footer">
                        <h5 class="mb-2">{{ selectedFile.name }}</h5>
                        <p v-if="selectedFile.description" class="text-muted mb-0">{{ selectedFile.description }}</p>
                        <div class="mt-2">
                            <span v-if="selectedFile.access_type === 'premium'" class="badge bg-warning">Premium</span>
                            <span v-else class="badge bg-success">Free</span>
                            <span v-if="selectedFile.type === 'bunny_stream'" class="badge bg-primary ms-2">HD Quality</span>
                            <span v-else-if="selectedFile.type === 'youtube'" class="badge bg-danger ms-2">YouTube</span>
                            <span v-else-if="selectedFile.type === 'external'" class="badge bg-info ms-2">رابط خارجي</span>
                        </div>
                    </div>
                </div>

                <!-- Lesson Description -->
                <div class="card shadow mt-4" v-if="lesson.description">
                    <div class="card-header border-bottom">
                        <h5 class="mb-0">عن الدرس</h5>
                    </div>
                    <div class="card-body" v-html="lesson.description"></div>
                </div>
            </div>

            <!-- Curriculum Sidebar -->
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header border-bottom">
                        <h5 class="mb-0">محتوى الدرس</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="accordion accordion-icon accordion-bg-light" id="accordionCurriculum">
                            <div v-for="(lecture, lIndex) in lesson.lectures" :key="lecture.id" class="accordion-item">
                                <h6 class="accordion-header">
                                    <button 
                                        class="accordion-button fw-bold"
                                        :class="{ collapsed: lIndex !== 0 }"
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        :data-bs-target="'#collapse' + lecture.id">
                                        {{ lecture.name }}
                                        <span class="badge bg-primary ms-auto me-2">{{ lecture.files ? lecture.files.length : 0 }}</span>
                                    </button>
                                </h6>

                                <div 
                                    :id="'collapse' + lecture.id" 
                                    class="accordion-collapse collapse"
                                    :class="{ show: lIndex === 0 }"
                                    data-bs-parent="#accordionCurriculum">
                                    <div class="accordion-body p-0">
                                        <div v-if="lecture.files && lecture.files.length > 0" class="list-group list-group-flush">
                                            <a 
                                                v-for="file in lecture.files" 
                                                :key="file.id"
                                                href="javascript:void(0)"
                                                @click="selectFile(file, lecture)"
                                                class="list-group-item list-group-item-action"
                                                :class="{ active: selectedFile && selectedFile.id === file.id }">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="bi bi-play-circle me-2"></i>
                                                        <span>{{ file.name }}</span>
                                                    </div>
                                                    <div>
                                                        <span v-if="file.access_type === 'premium'" class="badge bg-warning">Premium</span>
                                                        <span v-else class="badge bg-success">Free</span>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                        <div v-else class="p-3 text-muted text-center">
                                            لا توجد ملفات
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="!lesson.lectures || lesson.lectures.length === 0" class="p-4 text-center text-muted">
                            لا يوجد محتوى متاح حالياً
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AppLayout>
</template>

<style scoped>
.list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

.card-bg-scale {
    transition: transform 0.3s ease;
}
</style>


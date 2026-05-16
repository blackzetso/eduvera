<script setup>
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    stream:    Object,
    isYoutube: Boolean,
    youtubeId: { type: String, default: null },
    isServer:  Boolean,
    videoUrl:  { type: String, default: null },
})
</script>

<template>
    <Head :title="`مشاهدة: ${stream.title}`" />

    <div class="sw-page" dir="rtl">

        <!-- Header -->
        <div class="sw-header">
            <div class="sw-header-inner">
                <div>
                    <h1 class="sw-title">{{ stream.title }}</h1>
                    <p class="sw-meta">
                        <span v-if="stream.subject" class="sw-badge">{{ stream.subject }}</span>
                        <span class="sw-instructor">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4z"/>
                            </svg>
                            {{ stream.teacher_name }}
                        </span>
                        <span v-if="stream.start_datetime" class="sw-date">{{ stream.start_datetime }}</span>
                    </p>
                </div>

            </div>
        </div>

        <!-- Player -->
        <div class="sw-player-wrap">

            <!-- YouTube embed -->
            <div v-if="isYoutube && youtubeId" class="sw-iframe-wrap">
                <iframe
                    :src="`https://www.youtube.com/embed/${youtubeId}?rel=0&modestbranding=1`"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                    class="sw-iframe"
                    :title="stream.title"
                ></iframe>
            </div>

            <!-- Server-stored video -->
            <div v-else-if="isServer" class="sw-video-wrap">
                <video
                    controls
                    controlslist="nodownload"
                    oncontextmenu="return false;"
                    class="sw-video"
                    :title="stream.title"
                    preload="metadata"
                >
                    <source :src="videoUrl" type="video/webm" />
                    متصفحك لا يدعم تشغيل الفيديو مباشرة.
                </video>
            </div>

            <!-- Fallback -->
            <div v-else class="sw-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.146.146 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.163.163 0 0 1-.054.06.116.116 0 0 1-.066.017H1.146a.115.115 0 0 1-.066-.017.163.163 0 0 1-.054-.06.176.176 0 0 1 .002-.183L7.884 2.073a.147.147 0 0 1 .054-.057zm1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z"/>
                    <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995z"/>
                </svg>
                <p>تعذّر تحميل الفيديو. قد يكون الملف غير متاح.</p>
            </div>

        </div>
    </div>
</template>

<style scoped>
* { box-sizing: border-box; margin: 0; padding: 0; }

.sw-page {
    min-height: 100vh;
    background: #0d0d1a;
    color: #e8e8f0;
    font-family: 'Cairo', 'Segoe UI', sans-serif;
}

/* ── Header ──────────────────────────────────────────────────── */
.sw-header {
    background: linear-gradient(135deg, #12122a 0%, #1a1a40 100%);
    border-bottom: 1px solid #2a2a5a;
    padding: 20px 24px;
}
.sw-header-inner {
    max-width: 960px;
    margin: 0 auto;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.sw-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
    line-height: 1.3;
}
.sw-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    color: #aaa;
}
.sw-badge {
    background: #0d6efd25;
    border: 1px solid #0d6efd50;
    color: #6ea8fe;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 12px;
}
.sw-instructor {
    display: flex;
    align-items: center;
    gap: 5px;
}
.sw-date { color: #888; }

.sw-download-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #198754;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.2s;
    flex-shrink: 0;
}
.sw-download-btn:hover { background: #157347; color: #fff; }

/* ── Player ──────────────────────────────────────────────────── */
.sw-player-wrap {
    max-width: 960px;
    margin: 32px auto;
    padding: 0 16px;
}

/* YouTube iframe — 16:9 */
.sw-iframe-wrap {
    position: relative;
    width: 100%;
    padding-top: 56.25%;
    border-radius: 12px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
}
.sw-iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

/* HTML5 video */
.sw-video-wrap {
    border-radius: 12px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
}
.sw-video {
    width: 100%;
    max-height: 72vh;
    display: block;
}

/* Error state */
.sw-error {
    text-align: center;
    padding: 60px 20px;
    color: #888;
}
.sw-error svg { opacity: 0.4; margin-bottom: 16px; }
.sw-error p { font-size: 15px; }
</style>

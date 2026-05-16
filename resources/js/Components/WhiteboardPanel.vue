<template>
    <div ref="container" class="wb-panel-container"></div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    readOnly:         { type: Boolean, default: true },
    initialElements:  { type: Array,   default: () => [] },
    isTeacher:        { type: Boolean, default: false },
    uploadWbMediaUrl: { type: String,  default: null },
})

const emit = defineEmits([
    'change',
    'pdf-file', 'video-file', 'video-url',
    'pdf-prev', 'pdf-next', 'pdf-close',
    'video-close', 'video-play', 'video-pause', 'video-seeked',
])

const container = ref(null)
let   api       = null

onMounted(async () => {
    const { mountExcalidraw } = await import('./ExcalidrawWrapper.jsx')
    api = mountExcalidraw(container.value, {
        onChange:        (elements) => emit('change', elements),
        readOnly:        props.readOnly,
        initialElements: props.initialElements,
        isTeacher:       props.isTeacher,
        onPdfFile:       (f)    => emit('pdf-file', f),
        onVideoFile:     (f)    => emit('video-file', f),
        onVideoUrl:      (url)  => emit('video-url', url),
        onPdfPrev:       ()     => emit('pdf-prev'),
        onPdfNext:       ()     => emit('pdf-next'),
        onPdfClose:      ()     => emit('pdf-close'),
        onVideoClose:    ()     => emit('video-close'),
        onVideoPlay:     (t)    => emit('video-play', t),
        onVideoPause:    (t)    => emit('video-pause', t),
        onVideoSeeked:   (t)    => emit('video-seeked', t),
    })
})

onBeforeUnmount(() => {
    api?.unmount()
    api = null
})

watch(() => props.readOnly, (value) => {
    api?.setReadOnly(value)
})

defineExpose({
    updateScene(elements)                    { api?.updateScene(elements) },
    clearBoard()                             { api?.clearBoard() },
    setReadOnly(value)                       { api?.setReadOnly(value) },
    showPdf(dataURL, fileId, cur, tot, w, h) { api?.showPdf(dataURL, fileId, cur, tot, w, h) },
    updatePdfPage(dataURL, fileId, cur, tot, w, h) { api?.showPdf(dataURL, fileId, cur, tot, w, h) },
    addFile(fileId, dataURL)                 { api?.addFile(fileId, dataURL) },
    closePdf()                               { api?.closePdf() },
    showVideo(url)                           { api?.showVideo(url) },
    closeVideo()                             { api?.closeVideo() },
    syncVideo(action, time)                  { api?.syncVideo(action, time) },
    setLoading(val)                          { api?.setLoading(val) },
})
</script>

<style scoped>
.wb-panel-container {
    width:    100%;
    height:   100%;
    overflow: hidden;
}
</style>

<script setup>
import { computed } from 'vue'
import { getPreviewProfile } from '@/data/website-image-preview-profiles'
import { evaluateSafeZones } from '@/utils/imageSafeZoneCheck'
import WebsiteImageSafeZoneOverlay from '@/Components/Admin/Website/WebsiteImageSafeZoneOverlay.vue'
import WebsiteImageFramePreview from '@/Components/Admin/Website/WebsiteImageFramePreview.vue'

const props = defineProps({
  imageUrl: { type: String, default: '' },
  specKey: { type: String, required: true },
  imageDims: { type: Object, default: null },
})

const profile = computed(() => getPreviewProfile(props.specKey))

const safeZoneEval = computed(() => {
  if (!profile.value || !props.imageUrl) {
    return { hasRisk: false, message: null, frameIssues: [] }
  }
  return evaluateSafeZones(profile.value.zones ?? [], profile.value.frames, props.imageDims)
})

const showPanel = computed(() => Boolean(props.imageUrl && profile.value?.frames?.length))
</script>

<template>
  <div v-if="showPanel" class="wiup card card-body bg-light border mt-3">
    <h3 class="h6 mb-1">Usage &amp; crop preview</h3>
    <p class="small text-muted mb-3">
      إرشاد فقط — الصورة الأصلية لا تُعدَّل. الشكلان Desktop/Mobile يوضّحان القصّ المحتمل على الموقع بعد الحفظ والنشر.
    </p>

    <div v-if="profile.zones?.length" class="mb-3">
      <div class="small text-muted mb-2">Original image — safe zones</div>
      <WebsiteImageSafeZoneOverlay
        :image-url="imageUrl"
        :zones="profile.zones"
        max-height="240px"
      />
      <ul class="wiup__legend small text-muted mt-2 mb-0">
        <li><span class="wiup__swatch wiup__swatch--safe" /> Safe area (keep important content inside)</li>
        <li><span class="wiup__swatch wiup__swatch--crop" /> Potential crop area</li>
      </ul>
    </div>

    <div class="wiup__frames">
      <div class="small text-muted mb-2">How it appears on the site</div>
      <div class="row g-3">
        <div
          v-for="frame in profile.frames"
          :key="frame.id"
          class="col-md-6"
          :class="{ 'col-lg-4': profile.frames.length >= 3 }"
        >
          <WebsiteImageFramePreview
            :image-url="imageUrl"
            :label="frame.label"
            :aspect-ratio="frame.aspectRatio"
            :mock="frame.mock"
            :image-dims="imageDims"
          />
        </div>
      </div>
    </div>

    <div v-if="safeZoneEval.message" class="alert alert-warning py-2 small mt-3 mb-0">
      {{ safeZoneEval.message }}
      <span v-if="safeZoneEval.frameIssues.length" class="d-block mt-1 text-muted">
        Affected:
        {{ safeZoneEval.frameIssues.map((f) => f.frameLabel).join(', ') }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.wiup__legend {
  list-style: none;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
}
.wiup__legend li {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.wiup__swatch {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 2px;
}
.wiup__swatch--safe {
  background: rgba(25, 135, 84, 0.35);
  border: 2px solid rgba(25, 135, 84, 0.85);
}
.wiup__swatch--crop {
  background: rgba(220, 53, 69, 0.08);
  border: 2px dashed rgba(220, 53, 69, 0.65);
}
</style>

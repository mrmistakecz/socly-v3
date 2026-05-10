<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 z-[200] bg-black flex items-center justify-center" @click.self="close">
      <!-- Progress bars -->
      <div class="absolute top-0 left-0 right-0 pt-safe z-20">
        <div class="flex gap-1 px-3 pt-3">
          <div v-for="(s, i) in currentStories" :key="s.id" class="flex-1 h-0.5 bg-white/30 rounded-full overflow-hidden">
            <div
              :class="['h-full bg-white rounded-full transition-all', i < storyIndex ? 'w-full' : i === storyIndex ? '' : 'w-0']"
              :style="i === storyIndex ? { width: progressWidth + '%', transition: 'width 0.1s linear' } : {}"
            />
          </div>
        </div>

        <!-- User info -->
        <div class="flex items-center justify-between px-4 py-3">
          <div class="flex items-center gap-3">
            <img :src="currentUser?.avatar || '/images/default-avatar.svg'" class="w-9 h-9 rounded-full object-cover border-2 border-white/30" />
            <div>
              <span class="text-white font-semibold text-sm">{{ currentUser?.name }}</span>
              <span class="text-white/50 text-xs ml-2">{{ timeAgo }}</span>
            </div>
          </div>
          <button @click="close" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/10 transition">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Navigation zones -->
      <div class="absolute inset-0 flex z-10">
        <div class="w-1/3 h-full cursor-pointer" @click="prevStory" />
        <div class="w-1/3 h-full" />
        <div class="w-1/3 h-full cursor-pointer" @click="nextStory" />
      </div>

      <!-- Media -->
      <div class="w-full h-full flex items-center justify-center">
        <video
          v-if="currentStory?.type === 'video'"
          :key="currentStory.id"
          :src="currentStory.media_url"
          class="max-w-full max-h-full object-contain"
          autoplay
          playsinline
          @ended="nextStory"
        />
        <img
          v-else-if="currentStory"
          :key="currentStory.id"
          :src="currentStory.media_url"
          class="max-w-full max-h-full object-contain"
        />
      </div>

      <!-- Caption -->
      <div v-if="currentStory?.caption" class="absolute bottom-0 left-0 right-0 pb-safe z-20">
        <div class="px-4 pb-6 pt-12 bg-gradient-to-t from-black/80 to-transparent">
          <p class="text-white text-sm text-center">{{ currentStory.caption }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  visible: Boolean,
  storyGroups: { type: Array, default: () => [] },
  initialGroupIndex: { type: Number, default: 0 },
})

const emit = defineEmits(['close'])

const groupIndex = ref(props.initialGroupIndex)
const storyIndex = ref(0)
const progress = ref(0)
let timer = null
const STORY_DURATION = 5000

const currentGroup = computed(() => props.storyGroups[groupIndex.value])
const currentUser = computed(() => currentGroup.value)
const currentStories = computed(() => currentGroup.value?.stories || [])
const currentStory = computed(() => currentStories.value[storyIndex.value])
const progressWidth = computed(() => progress.value)

const timeAgo = computed(() => {
  if (!currentStory.value?.created_at) return ''
  const diff = Date.now() - new Date(currentStory.value.created_at).getTime()
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'právě teď'
  if (hours < 24) return `před ${hours}h`
  return 'před 1d'
})

function startTimer() {
  stopTimer()
  progress.value = 0

  if (currentStory.value?.type === 'video') return

  const interval = 50
  const step = (interval / STORY_DURATION) * 100
  timer = setInterval(() => {
    progress.value += step
    if (progress.value >= 100) {
      nextStory()
    }
  }, interval)
}

function stopTimer() {
  if (timer) { clearInterval(timer); timer = null }
}

function trackView() {
  if (currentStory.value?.id) {
    axios.post(`/stories/${currentStory.value.id}/view`).catch(() => {})
  }
}

function nextStory() {
  if (storyIndex.value < currentStories.value.length - 1) {
    storyIndex.value++
  } else if (groupIndex.value < props.storyGroups.length - 1) {
    groupIndex.value++
    storyIndex.value = 0
  } else {
    close()
    return
  }
  startTimer()
  trackView()
}

function prevStory() {
  if (storyIndex.value > 0) {
    storyIndex.value--
  } else if (groupIndex.value > 0) {
    groupIndex.value--
    storyIndex.value = (props.storyGroups[groupIndex.value]?.stories?.length || 1) - 1
  }
  startTimer()
}

function close() {
  stopTimer()
  emit('close')
}

watch(() => props.visible, (val) => {
  if (val) {
    groupIndex.value = props.initialGroupIndex
    storyIndex.value = 0
    startTimer()
    trackView()
  } else {
    stopTimer()
  }
})

onUnmounted(() => stopTimer())
</script>

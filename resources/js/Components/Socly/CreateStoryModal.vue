<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 z-[200] bg-black/90 backdrop-blur-sm flex items-center justify-center">
      <div class="w-full max-w-md mx-4 bg-background rounded-2xl overflow-hidden border border-border/50 shadow-2xl">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-border/50">
          <h3 class="font-bold">Nová Story</h3>
          <button @click="$emit('close')" class="p-2 rounded-xl hover:bg-secondary/50 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Preview -->
        <div class="relative aspect-[9/16] max-h-[50vh] bg-black flex items-center justify-center overflow-hidden">
          <video v-if="previewUrl && isVideo" :src="previewUrl" class="max-w-full max-h-full object-contain" autoplay muted playsinline loop />
          <img v-else-if="previewUrl" :src="previewUrl" class="max-w-full max-h-full object-contain" />
          <label v-else class="flex flex-col items-center gap-3 cursor-pointer text-muted-foreground hover:text-foreground transition">
            <div class="w-16 h-16 rounded-2xl bg-secondary/50 flex items-center justify-center">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            <span class="text-sm font-medium">Vybrat fotku nebo video</span>
            <input type="file" accept="image/*,video/mp4,video/webm" class="hidden" @change="handleFile" />
          </label>
        </div>

        <!-- Caption + Submit -->
        <div class="p-4 space-y-3">
          <input
            v-model="caption"
            type="text"
            placeholder="Popisek (volitelný)"
            maxlength="200"
            class="w-full bg-secondary/50 border border-border rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-primary transition"
          />
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-muted-foreground cursor-pointer">
              <input type="checkbox" v-model="isLocked" class="rounded border-border text-primary focus:ring-primary" />
              Pouze pro odběratele
            </label>
          </div>
          <button
            @click="submit"
            :disabled="!file || uploading"
            :class="[
              'w-full py-3 rounded-xl font-bold text-white transition-all',
              file && !uploading
                ? 'bg-gradient-to-r from-primary to-pink-500 glow-primary btn-premium'
                : 'bg-secondary text-muted-foreground cursor-not-allowed'
            ]"
          >
            {{ uploading ? 'Nahrávám...' : 'Sdílet Story' }}
          </button>
          <p v-if="error" class="text-destructive text-xs text-center">{{ error }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({ visible: Boolean })
const emit = defineEmits(['close', 'created'])

const file = ref(null)
const previewUrl = ref(null)
const isVideo = ref(false)
const caption = ref('')
const isLocked = ref(false)
const uploading = ref(false)
const error = ref('')

function handleFile(e) {
  const f = e.target.files[0]
  if (!f) return
  file.value = f
  isVideo.value = f.type.startsWith('video/')
  previewUrl.value = URL.createObjectURL(f)
}

async function submit() {
  if (!file.value || uploading.value) return
  uploading.value = true
  error.value = ''

  const fd = new FormData()
  fd.append('media', file.value)
  if (caption.value) fd.append('caption', caption.value)
  fd.append('is_locked', isLocked.value ? '1' : '0')

  try {
    const { data } = await axios.post('/stories', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    emit('created', data.story)
    reset()
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'Nepodařilo se nahrát story.'
  } finally {
    uploading.value = false
  }
}

function reset() {
  file.value = null
  previewUrl.value = null
  isVideo.value = false
  caption.value = ''
  isLocked.value = false
  error.value = ''
}

watch(() => props.visible, (val) => {
  if (!val) reset()
})
</script>

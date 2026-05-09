<template>
  <div class="fixed inset-0 z-50 bg-black flex flex-col" v-if="show">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 bg-black/80 backdrop-blur-sm">
      <div class="flex items-center gap-3">
        <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse" v-if="connected"></div>
        <span class="text-white font-semibold">{{ roomName }}</span>
        <span class="text-gray-400 text-sm">{{ viewerCount }} diváků</span>
      </div>
      <button @click="leave" class="text-white hover:text-red-400 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Video area -->
    <div class="flex-1 relative">
      <video
        ref="videoEl"
        autoplay
        playsinline
        muted
        class="w-full h-full object-contain"
        v-if="isHost"
      ></video>
      <div
        ref="remoteContainer"
        class="w-full h-full flex items-center justify-center"
        v-else
      >
        <p class="text-gray-400" v-if="!connected">Připojování...</p>
      </div>
    </div>

    <!-- Controls (host only) -->
    <div class="flex items-center justify-center gap-6 p-4 bg-black/80" v-if="isHost">
      <button
        @click="toggleCamera"
        :class="['w-12 h-12 rounded-full flex items-center justify-center transition',
          cameraOn ? 'bg-white/20 text-white' : 'bg-red-500/20 text-red-400']"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
        </svg>
      </button>
      <button
        @click="toggleMic"
        :class="['w-12 h-12 rounded-full flex items-center justify-center transition',
          micOn ? 'bg-white/20 text-white' : 'bg-red-500/20 text-red-400']"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
        </svg>
      </button>
      <button
        @click="leave"
        class="w-12 h-12 rounded-full bg-red-500 text-white flex items-center justify-center"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useLiveStream } from '@/composables/useLiveStream';

const props = defineProps({
  show: Boolean,
  roomName: { type: String, required: true },
  isHost: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const { getToken, stopStream } = useLiveStream();

const videoEl = ref(null);
const remoteContainer = ref(null);
const connected = ref(false);
const viewerCount = ref(0);
const cameraOn = ref(true);
const micOn = ref(true);
let localStream = null;
let peerConnection = null;

async function connect() {
  const tokenData = await getToken(props.roomName);
  if (!tokenData) return;

  connected.value = true;
  viewerCount.value = 1;

  if (props.isHost && videoEl.value) {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({
        video: { width: 1280, height: 720 },
        audio: true,
      });
      videoEl.value.srcObject = localStream;
    } catch (e) {
      console.error('Camera access denied:', e);
    }
  }
}

function toggleCamera() {
  if (!localStream) return;
  const track = localStream.getVideoTracks()[0];
  if (track) {
    track.enabled = !track.enabled;
    cameraOn.value = track.enabled;
  }
}

function toggleMic() {
  if (!localStream) return;
  const track = localStream.getAudioTracks()[0];
  if (track) {
    track.enabled = !track.enabled;
    micOn.value = track.enabled;
  }
}

function leave() {
  if (localStream) {
    localStream.getTracks().forEach(t => t.stop());
    localStream = null;
  }
  connected.value = false;
  stopStream();
  emit('close');
}

watch(() => props.show, (val) => {
  if (val) connect();
  else leave();
});

onUnmounted(() => {
  leave();
});
</script>

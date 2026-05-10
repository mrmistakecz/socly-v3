<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { MessageCircle, Lock, Grid3X3, PlayCircle, Bookmark, Settings, ChevronLeft, BadgeCheck, Share2, Heart, Crown, Sparkles, Edit3, X, Calendar, MapPin, Link as LinkIcon, Users, Check, CreditCard } from 'lucide-vue-next'

const props = defineProps({
  profileUser: Object,
  posts: { type: Array, default: () => [] },
  isFollowing: Boolean,
  isSubscribed: Boolean,
  isOwn: Boolean,
})

const page = usePage()
const activeTab = ref('posts')
const following = ref(props.isFollowing)
const subscribed = ref(props.isSubscribed)
const showPostModal = ref(false)
const selectedPost = ref(null)
const showShareMenu = ref(false)
const shareToast = ref('')
const followersCount = ref(props.profileUser.followers)
const postsCount = ref(props.profileUser.posts_count)

const tabs = computed(() => {
  const base = [
    { id: 'posts', label: 'Příspěvky', icon: Grid3X3 },
    { id: 'videos', label: 'Videa', icon: PlayCircle },
  ]
  if (props.isOwn) {
    base.push({ id: 'saved', label: 'Uložené', icon: Bookmark })
  }
  return base
})

const formatNumber = (num) => {
  if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`
  if (num >= 1000) return `${(num / 1000).toFixed(1)}K`
  return num?.toString() || '0'
}

const handleFollow = async () => {
  following.value = !following.value
  followersCount.value += following.value ? 1 : -1
  try {
    await axios.post(`/users/${props.profileUser.id}/follow`)
  } catch {
    following.value = !following.value
    followersCount.value += following.value ? 1 : -1
  }
}

const handleSubscribe = async () => {
  if (subscribed.value) return
  try {
    await axios.post(`/users/${props.profileUser.id}/subscribe`)
    subscribed.value = true
  } catch {}
}

const handleMessage = () => {
  router.visit('/?tab=messages&chat=' + props.profileUser.id)
}

const handleShare = async () => {
  const url = `${window.location.origin}/profile/${props.profileUser.id}`
  const shareData = {
    title: `${props.profileUser.name} na SOCLY`,
    text: `Podívejte se na profil ${props.profileUser.name} na SOCLY`,
    url,
  }

  if (navigator.share) {
    try { await navigator.share(shareData) } catch {}
  } else {
    showShareMenu.value = !showShareMenu.value
  }
}

const copyProfileLink = async () => {
  const url = `${window.location.origin}/profile/${props.profileUser.id}`
  try {
    await navigator.clipboard.writeText(url)
    shareToast.value = 'Odkaz zkopírován!'
    showShareMenu.value = false
    setTimeout(() => shareToast.value = '', 2000)
  } catch {}
}

const savedPosts = ref([])
const savedLoaded = ref(false)

const filteredPosts = computed(() => {
  if (activeTab.value === 'videos') return props.posts.filter(p => p.isVideo)
  if (activeTab.value === 'saved') return savedPosts.value
  return props.posts
})

const handleTabClick = async (tabId) => {
  activeTab.value = tabId
  if (tabId === 'saved' && !savedLoaded.value && props.isOwn) {
    try {
      const { data } = await axios.get('/api/bookmarks')
      savedPosts.value = data.posts || []
      savedLoaded.value = true
    } catch { savedPosts.value = [] }
  }
}

const openPost = (post) => {
  selectedPost.value = post
  showPostModal.value = true
}

const closePostModal = () => {
  showPostModal.value = false
  selectedPost.value = null
}

const likePost = async (post) => {
  post.liked = !post.liked
  post.likesRaw = (post.likesRaw || 0) + (post.liked ? 1 : -1)
  try {
    await axios.post(`/posts/${post.id}/like`)
  } catch {
    post.liked = !post.liked
    post.likesRaw = (post.likesRaw || 0) + (post.liked ? 1 : -1)
  }
}

const bookmarkPost = async (post) => {
  post.bookmarked = !post.bookmarked
  try {
    await axios.post(`/posts/${post.id}/bookmark`)
  } catch {
    post.bookmarked = !post.bookmarked
  }
}

const closeShareMenu = (e) => {
  if (showShareMenu.value) showShareMenu.value = false
}
</script>

<template>
  <Head :title="profileUser.name" />
  
  <div class="min-h-dvh bg-background pb-32 lg:pb-8">
    <!-- Cover Photo -->
    <div class="relative h-48 lg:h-64">
      <img
        :src="profileUser.cover"
        alt="Cover"
        class="w-full h-full object-cover"
      />
      <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-background" />
      
      <!-- Top Actions -->
      <div class="absolute top-0 left-0 right-0 pt-safe">
        <div class="flex items-center justify-between p-4">
          <button 
            @click="router.visit('/')"
            class="w-10 h-10 rounded-xl glass flex items-center justify-center"
          >
            <ChevronLeft class="w-5 h-5 text-white" />
          </button>
          <div class="flex items-center gap-2">
            <div class="relative">
              <button @click="handleShare" class="w-10 h-10 rounded-xl glass flex items-center justify-center">
                <Share2 class="w-5 h-5 text-white" />
              </button>
              <div v-if="showShareMenu" class="absolute right-0 top-12 w-48 bg-background border border-border/50 rounded-xl shadow-xl p-2 z-50">
                <button @click="copyProfileLink" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg hover:bg-secondary/50 transition-colors">
                  <LinkIcon class="w-4 h-4" />
                  Kopírovat odkaz
                </button>
              </div>
            </div>
            <button 
              v-if="isOwn"
              @click="router.visit('/settings')"
              class="w-10 h-10 rounded-xl glass flex items-center justify-center"
            >
              <Settings class="w-5 h-5 text-white" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Profile Info -->
    <div class="relative px-4 lg:px-6">
      <!-- Avatar & Stats Row -->
      <div class="flex flex-col lg:flex-row lg:items-end lg:gap-6 -mt-16 lg:-mt-20 mb-5">
        <!-- Avatar -->
        <div class="relative self-start lg:self-auto">
          <div class="w-28 h-28 lg:w-36 lg:h-36 rounded-2xl bg-gradient-to-br from-primary via-pink-500 to-accent p-[3px] glow-primary-intense">
            <div class="w-full h-full rounded-2xl overflow-hidden bg-background p-[3px]">
              <img
                :src="profileUser.avatar || '/images/default-avatar.svg'"
                :alt="profileUser.name"
                class="w-full h-full rounded-2xl object-cover"
              />
            </div>
          </div>
          
          <div v-if="profileUser.isVIP" class="absolute -bottom-2 left-1/2 -translate-x-1/2 flex items-center gap-1 px-2.5 py-1 bg-gradient-to-r from-gold to-amber-500 rounded-full border-2 border-background">
            <Crown class="w-3 h-3 text-black" />
            <span class="text-[9px] font-bold text-black uppercase">TOP</span>
          </div>
        </div>
        
        <!-- Stats - Desktop -->
        <div class="hidden lg:flex gap-8 mb-3">
          <div class="text-center">
            <p class="text-2xl font-bold">{{ profileUser.posts_count }}</p>
            <p class="text-sm text-muted-foreground">Příspěvků</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold">{{ formatNumber(followersCount) }}</p>
            <p class="text-sm text-muted-foreground">Odběratelů</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold">{{ formatNumber(profileUser.likes) }}</p>
            <p class="text-sm text-muted-foreground">Líbí se</p>
          </div>
        </div>

        <!-- Desktop Actions -->
        <div class="hidden lg:flex gap-3 ml-auto mb-3">
          <template v-if="isOwn">
            <button
              @click="router.visit('/settings')"
              class="px-8 py-3 rounded-xl font-semibold bg-secondary border border-border transition-all hover:bg-secondary/80"
            >
              Upravit profil
            </button>
          </template>
          <template v-else>
            <button
              @click="handleFollow"
              :class="[
                'px-8 py-3 rounded-xl font-semibold transition-all btn-premium',
                following 
                  ? 'bg-secondary border border-border' 
                  : 'bg-gradient-to-r from-primary to-pink-500 text-white glow-primary'
              ]"
            >
              {{ following ? 'Sledujete' : 'Sledovat' }}
            </button>
            <button @click="handleMessage" class="px-4 py-3 rounded-xl bg-secondary hover:bg-secondary/80 transition-colors">
              <MessageCircle class="w-5 h-5" />
            </button>
          </template>
        </div>
      </div>

      <!-- Stats - Mobile -->
      <div class="flex justify-around py-4 border-y border-border/50 mb-4 lg:hidden">
        <div class="text-center">
          <p class="text-lg font-bold">{{ profileUser.posts_count }}</p>
          <p class="text-xs text-muted-foreground">Příspěvků</p>
        </div>
        <div class="text-center">
          <p class="text-lg font-bold">{{ formatNumber(followersCount) }}</p>
          <p class="text-xs text-muted-foreground">Odběratelů</p>
        </div>
        <div class="text-center">
          <p class="text-lg font-bold">{{ formatNumber(profileUser.likes) }}</p>
          <p class="text-xs text-muted-foreground">Líbí se</p>
        </div>
      </div>

      <!-- Name & Bio -->
      <div class="mb-5">
        <div class="flex items-center gap-2 mb-1">
          <h1 class="text-xl lg:text-2xl font-bold">{{ profileUser.name }}</h1>
          <BadgeCheck v-if="profileUser.verified" class="w-5 h-5 lg:w-6 lg:h-6 text-primary fill-primary/20" />
        </div>
        <p class="text-sm text-muted-foreground mb-2">{{ profileUser.username }}</p>
        <p v-if="profileUser.bio" class="text-sm whitespace-pre-line leading-relaxed text-foreground/85 mb-3">{{ profileUser.bio }}</p>
        
        <!-- Meta Info -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
          <span v-if="profileUser.joinedAt" class="flex items-center gap-1">
            <Calendar class="w-3.5 h-3.5" />
            Člen od {{ profileUser.joinedAt }}
          </span>
          <span v-if="profileUser.location" class="flex items-center gap-1">
            <MapPin class="w-3.5 h-3.5" />
            {{ profileUser.location }}
          </span>
          <span v-if="profileUser.following" class="flex items-center gap-1">
            <Users class="w-3.5 h-3.5" />
            <b class="text-foreground">{{ formatNumber(profileUser.following) }}</b> sleduje
          </span>
        </div>
      </div>

      <!-- Subscription CTA for creators -->
      <div v-if="!isOwn && profileUser.isCreator && profileUser.subscriptionPrice > 0 && !subscribed" class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-primary/10 via-pink-500/10 to-accent/10 border border-primary/20">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-bold text-sm">Předplatné</p>
            <p class="text-xs text-muted-foreground">Přístup ke všem exkluzivním příspěvkům</p>
          </div>
          <button @click="handleSubscribe" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary to-pink-500 text-white rounded-xl text-sm font-bold btn-premium">
            <CreditCard class="w-4 h-4" />
            {{ profileUser.subscriptionPrice }} Kč/měs
          </button>
        </div>
      </div>

      <div v-if="subscribed && !isOwn" class="mb-6 flex items-center gap-2 px-4 py-2.5 rounded-xl bg-green-500/10 border border-green-500/20 text-green-500 text-sm font-medium">
        <Check class="w-4 h-4" />
        Aktivní předplatné
      </div>

      <!-- Mobile Action Buttons -->
      <div class="flex gap-3 mb-6 lg:hidden">
        <template v-if="isOwn">
          <button
            @click="router.visit('/settings')"
            class="flex-1 py-3.5 rounded-xl font-bold bg-secondary border border-border transition-all"
          >
            <span class="flex items-center justify-center gap-2">
              <Edit3 class="w-4 h-4" />
              Upravit profil
            </span>
          </button>
        </template>
        <template v-else>
          <button
            @click="handleFollow"
            :class="[
              'flex-1 py-3.5 rounded-xl font-bold transition-all',
              following 
                ? 'bg-secondary border border-border' 
                : 'bg-gradient-to-r from-primary to-pink-500 text-white glow-primary btn-premium'
            ]"
          >
            <span class="flex items-center justify-center gap-2">
              <Sparkles v-if="!following" class="w-4 h-4" />
              {{ following ? 'Sledujete' : 'Sledovat' }}
            </span>
          </button>
          <button @click="handleMessage" class="w-14 h-14 rounded-xl bg-secondary flex items-center justify-center">
            <MessageCircle class="w-5 h-5" />
          </button>
        </template>
      </div>

      <!-- Tabs -->
      <div class="flex border-b border-border">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="handleTabClick(tab.id)"
          :class="[
            'flex-1 py-4 flex items-center justify-center gap-2 transition-all relative',
            activeTab === tab.id ? 'text-primary' : 'text-muted-foreground hover:text-foreground'
          ]"
        >
          <component :is="tab.icon" class="w-5 h-5" />
          <span class="text-sm font-medium hidden sm:inline">{{ tab.label }}</span>
          <span v-if="activeTab === tab.id" class="absolute bottom-0 left-4 right-4 h-0.5 bg-primary rounded-full" />
        </button>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-0.5 lg:gap-1 mt-0.5 lg:mt-1 lg:px-6">
      <button 
        v-for="item in filteredPosts" 
        :key="item.id" 
        @click="openPost(item)"
        class="relative aspect-square bg-secondary/20 overflow-hidden group"
      >
        <img
          :src="item.image"
          :alt="`Post ${item.id}`"
          loading="lazy"
          :class="[
            'w-full h-full object-cover transition-all duration-300 group-hover:scale-105',
            item.locked && !subscribed && !isOwn ? 'blur-xl scale-110' : ''
          ]"
        />
        
        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
          <div class="flex items-center gap-1 text-white">
            <Heart class="w-5 h-5 fill-white" />
            <span class="font-bold">{{ item.likes }}</span>
          </div>
        </div>
        
        <div v-if="item.isVideo" class="absolute top-2 right-2 w-6 h-6 rounded-full glass flex items-center justify-center">
          <PlayCircle class="w-3.5 h-3.5 text-white" />
        </div>
        
        <div v-if="item.locked && !subscribed && !isOwn" class="absolute inset-0 flex items-center justify-center bg-black/30">
          <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-xl glass flex items-center justify-center">
            <Lock class="w-5 h-5 lg:w-6 lg:h-6 text-primary" />
          </div>
        </div>
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="filteredPosts.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
      <div class="w-20 h-20 rounded-2xl bg-secondary/50 flex items-center justify-center mb-4">
        <Grid3X3 class="w-10 h-10 text-muted-foreground" />
      </div>
      <p class="text-lg font-semibold mb-1">Zatím žádné příspěvky</p>
      <p class="text-sm text-muted-foreground">Příspěvky se zobrazí zde</p>
    </div>

    <!-- Post Detail Modal -->
    <Teleport to="body">
      <div v-if="showPostModal && selectedPost" class="fixed inset-0 z-[100] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closePostModal" />
        <div class="relative w-full max-w-lg mx-4 bg-background rounded-2xl overflow-hidden shadow-2xl border border-border/50 max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-border/50">
            <div class="flex items-center gap-3">
              <img :src="profileUser.avatar || '/images/default-avatar.svg'" class="w-8 h-8 rounded-lg object-cover" />
              <div>
                <div class="flex items-center gap-1">
                  <span class="text-sm font-semibold">{{ profileUser.name }}</span>
                  <BadgeCheck v-if="profileUser.verified" class="w-3.5 h-3.5 text-primary fill-primary/20" />
                </div>
                <p class="text-[10px] text-muted-foreground">{{ selectedPost.date || '' }}</p>
              </div>
            </div>
            <button @click="closePostModal" class="p-2 rounded-xl hover:bg-secondary/50 transition-colors">
              <X class="w-5 h-5" />
            </button>
          </div>
          
          <!-- Modal Image / Video -->
          <div class="relative overflow-hidden">
            <video v-if="selectedPost.isVideo && !(selectedPost.locked && !subscribed && !isOwn)" :src="selectedPost.image" class="w-full max-h-[60vh] object-contain bg-black" controls playsinline />
            <img 
              v-else
              :src="selectedPost.image" 
              :alt="`Post ${selectedPost.id}`"
              loading="lazy"
              :class="[
                'w-full max-h-[60vh] object-contain bg-black',
                selectedPost.locked && !subscribed && !isOwn ? 'blur-xl scale-110' : ''
              ]"
            />
            <div v-if="selectedPost.locked && !subscribed && !isOwn" class="absolute inset-0 flex items-center justify-center bg-black/30">
              <div class="flex flex-col items-center gap-3">
                <div class="w-14 h-14 rounded-2xl glass flex items-center justify-center">
                  <Lock class="w-7 h-7 text-primary" />
                </div>
                <p class="text-white text-sm font-semibold">Exkluzivní obsah</p>
                <button v-if="profileUser.subscriptionPrice" @click="handleSubscribe" class="px-5 py-2 bg-primary text-white rounded-xl text-sm font-bold btn-premium">
                  Odblokovat za {{ profileUser.subscriptionPrice }} Kč/měs
                </button>
              </div>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="px-4 py-3 border-t border-border/50">
            <div class="flex items-center gap-4">
              <button @click="likePost(selectedPost)" class="flex items-center gap-1.5 text-sm transition-colors" :class="selectedPost.liked ? 'text-red-500' : 'text-muted-foreground hover:text-foreground'">
                <Heart :class="['w-5 h-5 transition-transform', selectedPost.liked ? 'fill-red-500 scale-110' : '']" />
                <span class="font-medium">{{ selectedPost.likes }}</span>
              </button>
              <button class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors">
                <MessageCircle class="w-5 h-5" />
                <span class="font-medium">{{ selectedPost.comments || 0 }}</span>
              </button>
              <button @click="bookmarkPost(selectedPost)" :class="['ml-auto transition-colors', selectedPost.bookmarked ? 'text-primary' : 'text-muted-foreground hover:text-foreground']">
                <Bookmark :class="['w-5 h-5', selectedPost.bookmarked ? 'fill-current' : '']" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Share Toast -->
    <Teleport to="body">
      <Transition name="toast">
        <div v-if="shareToast" class="fixed bottom-24 lg:bottom-8 left-1/2 -translate-x-1/2 z-[110] px-5 py-3 bg-foreground text-background rounded-xl text-sm font-medium shadow-xl">
          {{ shareToast }}
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translate(-50%, 10px);
}
</style>

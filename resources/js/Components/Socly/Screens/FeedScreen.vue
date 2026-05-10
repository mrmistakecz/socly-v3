<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { Play, Plus, Crown, Flame, Clock, ImageOff, Bell } from 'lucide-vue-next'
import FeedCard from './FeedCard.vue'
import StoryViewer from '@/Components/Socly/StoryViewer.vue'
import CreateStoryModal from '@/Components/Socly/CreateStoryModal.vue'
import axios from 'axios'
import { usePullToRefresh } from '@/composables/usePullToRefresh'

const props = defineProps({
  posts: { type: Array, default: () => [] },
  stories: { type: Array, default: () => [] },
  postUpdates: { type: Object, default: () => ({}) },
})

const page = usePage()
const authUser = computed(() => page.props.auth?.user)

const showStoryViewer = ref(false)
const storyGroupIndex = ref(0)
const showCreateStory = ref(false)

const storiesWithOwn = computed(() => {
  const ownStories = props.stories.find(s => s.id === authUser.value?.id)
  const own = {
    id: 0,
    name: 'Vaše story',
    avatar: authUser.value?.avatar || '/images/default-avatar.svg',
    hasStory: !!ownStories,
    isOwn: true,
    stories: ownStories?.stories || [],
  }
  return [own, ...props.stories.filter(s => s.id !== authUser.value?.id)]
})

const storyGroupsForViewer = computed(() => {
  return storiesWithOwn.value.filter(s => s.stories?.length > 0)
})

const handleStoryClick = (story, index) => {
  if (story.isOwn && !story.hasStory) {
    showCreateStory.value = true
    return
  }
  if (!story.stories?.length) return
  const viewerGroups = storyGroupsForViewer.value
  const gIdx = viewerGroups.findIndex(g => g.id === story.id)
  if (gIdx === -1) return
  storyGroupIndex.value = gIdx
  showStoryViewer.value = true
}

const handleStoryCreated = () => {
  showCreateStory.value = false
  router.reload({ only: ['stories'], preserveScroll: true })
}

const feedContainer = ref(null)
const { isPulling, isRefreshing, pullDistance } = usePullToRefresh(feedContainer)

const feedData = ref([...props.posts])
const activeFilter = ref('latest')
const isLoadingMore = ref(false)
const hasMore = ref(props.posts.length === 20)
let pageCount = 1

// Watch for initial posts prop change (when filter changes via Inertia)
watch(() => props.posts, (newPosts) => {
  feedData.value = [...newPosts]
  hasMore.value = newPosts.length === 20
  pageCount = 1
})

const changeFilter = (filter) => {
  activeFilter.value = filter
  router.reload({ data: { sort: filter }, only: ['posts'], preserveScroll: true })
}

const loadMore = async () => {
  if (isLoadingMore.value || !hasMore.value) return
  
  isLoadingMore.value = true
  pageCount++
  
  try {
    const { data } = await axios.get('/api/posts', {
      params: { sort: activeFilter.value, page: pageCount }
    })
    
    if (data.posts && data.posts.length) {
      feedData.value.push(...data.posts)
      hasMore.value = data.posts.length === 20
    } else {
      hasMore.value = false
    }
  } catch (e) {
    pageCount--
  }
  
  isLoadingMore.value = false
}

// Setup intersection observer for infinite scroll

const loadMoreTrigger = ref(null)
let observer = null

onMounted(() => {
  observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && hasMore.value && !isLoadingMore.value) {
      loadMore()
    }
  }, { rootMargin: '400px' })
  
  if (loadMoreTrigger.value) {
    observer.observe(loadMoreTrigger.value)
  }
})

onUnmounted(() => {
  if (observer && loadMoreTrigger.value) {
    observer.unobserve(loadMoreTrigger.value)
  }
})
</script>

<template>
  <div ref="feedContainer" class="min-h-dvh pb-32 lg:pb-8">
    <!-- Pull to refresh indicator -->
    <Transition name="slide-up">
      <div v-if="isPulling || isRefreshing" class="flex justify-center py-4">
        <div :class="['w-6 h-6 border-2 border-primary border-t-transparent rounded-full', isRefreshing ? 'animate-spin' : '']" />
      </div>
    </Transition>
    <!-- Desktop Header -->
    <div class="hidden lg:block sticky top-0 z-30 bg-background/80 backdrop-blur-xl border-b border-border/50 px-6 py-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Hlavní zeď</h1>
          <p class="text-sm text-muted-foreground">Obsah od tvůrců, které sleduješ</p>
        </div>
        <div class="flex items-center gap-2">
          <button 
            @click="changeFilter('trending')"
            :class="['flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all', activeFilter === 'trending' ? 'bg-primary/10 text-primary' : 'bg-secondary/50 hover:bg-secondary text-muted-foreground']"
          >
            <Flame class="w-4 h-4" />
            Trendující
          </button>
          <button 
            @click="changeFilter('latest')"
            :class="['flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all', activeFilter === 'latest' ? 'bg-primary/10 text-primary' : 'bg-secondary/50 hover:bg-secondary text-muted-foreground']"
          >
            <Clock class="w-4 h-4" />
            Nejnovější
          </button>
        </div>
      </div>
    </div>

    <div class="pt-4 lg:pt-6 px-4 lg:px-6">
      <!-- Stories Row -->
      <div class="flex gap-3 overflow-x-auto hide-scrollbar py-2 -mx-4 px-4 lg:mx-0 lg:px-0 mb-6">
        <button 
          v-for="story in storiesWithOwn" 
          :key="story.id" 
          @click="handleStoryClick(story, storiesWithOwn.indexOf(story))"
          class="flex flex-col items-center gap-2 flex-shrink-0 transition-transform active:scale-95"
        >
          <div class="relative">
            <div :class="[
              'w-[72px] h-[72px] lg:w-[80px] lg:h-[80px] rounded-full p-[3px] transition-all',
              story.isOwn ? 'bg-secondary' : '',
              story.isLive ? 'bg-gradient-to-br from-destructive via-primary to-accent animate-pulse-glow' : '',
              story.hasStory && !story.isLive && !story.isOwn ? 'story-ring' : '',
              !story.hasStory && !story.isOwn ? 'bg-secondary/50' : ''
            ]">
              <div class="w-full h-full rounded-full overflow-hidden bg-background p-[2px]">
                <img
                  :src="story.avatar || '/images/default-avatar.svg'"
                  :alt="story.name"
                  class="w-full h-full rounded-full object-cover"
                  loading="lazy"
                />
              </div>
            </div>

            <div v-if="story.isOwn" class="absolute bottom-0 right-0 w-6 h-6 rounded-full bg-primary flex items-center justify-center border-2 border-background">
              <Plus class="w-3.5 h-3.5 text-white" stroke-width="3" />
            </div>

            <div v-if="story.isLive" class="absolute -bottom-1 left-1/2 -translate-x-1/2 flex items-center gap-1 px-2 py-0.5 bg-destructive rounded-full border-2 border-background">
              <Play class="w-2 h-2 text-white fill-white" />
              <span class="text-[9px] font-bold text-white uppercase">Zive</span>
            </div>

            <div v-if="story.isVIP && !story.isLive" class="absolute -bottom-1 left-1/2 -translate-x-1/2 flex items-center gap-0.5 px-1.5 py-0.5 bg-gradient-to-r from-gold to-amber-500 rounded-full border-2 border-background">
              <Crown class="w-2.5 h-2.5 text-black" />
            </div>

            <div v-if="story.isNew" class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-accent flex items-center justify-center border-2 border-background">
              <span class="text-[8px] font-bold text-white">N</span>
            </div>
          </div>
          
          <span :class="[
            'text-xs font-medium truncate w-[72px] lg:w-[80px] text-center',
            story.isOwn ? 'text-muted-foreground' : ''
          ]">
            {{ story.name }}
          </span>
        </button>
      </div>

      <div class="h-px bg-gradient-to-r from-transparent via-border to-transparent mb-6" />

      <!-- Feed - Responsive Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-5 lg:gap-6 max-w-7xl">
        <FeedCard 
          v-for="post in feedData" 
          :key="post.id" 
          v-bind="post"
          :realtime-update="postUpdates.postId === post.id ? postUpdates : null"
          @deleted="feedData = feedData.filter(p => p.id !== post.id)"
        />
      </div>

      <!-- Loading Trigger -->
      <div v-if="hasMore" ref="loadMoreTrigger" class="flex justify-center py-8">
        <div class="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Empty State -->
      <div v-if="feedData.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 rounded-3xl bg-secondary/50 flex items-center justify-center mb-5">
          <ImageOff class="w-10 h-10 text-muted-foreground" />
        </div>
        <h3 class="text-lg font-semibold mb-2">Zatím žádné příspěvky</h3>
        <p class="text-sm text-muted-foreground max-w-xs">Začněte sledovat tvůrce, abyste viděli jejich obsah ve svém feedu</p>
      </div>
    </div>
    
    <!-- Story Viewer -->
    <StoryViewer
      :visible="showStoryViewer"
      :story-groups="storyGroupsForViewer"
      :initial-group-index="storyGroupIndex"
      @close="showStoryViewer = false"
    />

    <!-- Create Story Modal -->
    <CreateStoryModal
      :visible="showCreateStory"
      @close="showCreateStory = false"
      @created="handleStoryCreated"
    />
  </div>
</template>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active {
  transition: all 0.3s ease;
}
.slide-up-enter-from, .slide-up-leave-to {
  opacity: 0;
  transform: translate(-50%, 20px);
}
</style>

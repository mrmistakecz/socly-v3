<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Users, Image, MessageCircle, TrendingUp, Shield, Crown, BadgeCheck, Trash2, ChevronLeft, Star, UserCheck, Search, Ban, AlertTriangle, DollarSign, CheckCircle, XCircle, Mail } from 'lucide-vue-next'

const props = defineProps({
  stats: Object,
  users: { type: Array, default: () => [] },
  posts: { type: Array, default: () => [] },
  reports: { type: Array, default: () => [] },
  search: { type: String, default: '' },
})

const page = usePage()
const activeTab = ref('users')
const searchQuery = ref(props.search)
let searchTimeout = null

watch(searchQuery, (val) => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    router.get('/admin', { search: val }, { preserveState: true, preserveScroll: true })
  }, 400)
})

const toggleFlag = (userId, flag, currentValue) => {
  router.put(`/admin/users/${userId}`, { [flag]: !currentValue }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const banUser = (userId, name, isBanned) => {
  const action = isBanned ? 'odbanovat' : 'zabanovat'
  if (!confirm(`Opravdu chcete ${action} uživatele ${name}?`)) return
  router.post(`/admin/users/${userId}/ban`, {}, { preserveScroll: true })
}

const deleteUser = (userId, name) => {
  if (!confirm(`Opravdu chcete SMAZAT uživatele ${name}? Toto je nevratné!`)) return
  router.delete(`/admin/users/${userId}`, { preserveScroll: true })
}

const deletePost = (postId) => {
  if (!confirm('Opravdu chcete smazat tento příspěvek?')) return
  router.delete(`/admin/posts/${postId}`, { preserveScroll: true })
}

const resolveReport = (id) => {
  router.post(`/admin/reports/${id}/resolve`, {}, { preserveScroll: true })
}

const dismissReport = (id) => {
  router.post(`/admin/reports/${id}/dismiss`, {}, { preserveScroll: true })
}

const reasonLabels = {
  spam: 'Spam',
  harassment: 'Obtěžování',
  fake: 'Falešný profil',
  nsfw: 'Nevhodný obsah',
  other: 'Jiný důvod',
}
</script>

<template>
  <Head title="Admin Dashboard" />
  
  <div class="min-h-dvh bg-background">
    <!-- Top Bar -->
    <div class="sticky top-0 z-50 bg-background/80 backdrop-blur-xl border-b border-border/50">
      <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <button @click="router.visit('/')" class="p-2 rounded-xl hover:bg-secondary/50 transition-colors">
            <ChevronLeft class="w-5 h-5" />
          </button>
          <div>
            <h1 class="text-xl font-black tracking-tight">
              <span class="text-gradient-premium">SOCLY</span>
              <span class="text-sm font-semibold text-destructive ml-2">ADMIN</span>
            </h1>
          </div>
        </div>
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
          <Shield class="w-4 h-4 text-destructive" />
          {{ page.props.auth.user.name }}
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-6">
      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="mb-4 px-4 py-3 rounded-xl bg-green-500/10 border border-green-500/30 text-green-400 text-sm font-medium">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error" class="mb-4 px-4 py-3 rounded-xl bg-destructive/10 border border-destructive/30 text-destructive text-sm font-medium">{{ $page.props.flash.error }}</div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <div class="p-4 rounded-2xl bg-card/50 border border-border/50">
          <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center mb-2"><Users class="w-5 h-5 text-primary" /></div>
          <p class="text-2xl font-bold">{{ stats.totalUsers }}</p>
          <p class="text-xs text-muted-foreground">Uživatelů</p>
        </div>
        <div class="p-4 rounded-2xl bg-card/50 border border-border/50">
          <div class="w-10 h-10 rounded-xl bg-pink-500/10 flex items-center justify-center mb-2"><Image class="w-5 h-5 text-pink-500" /></div>
          <p class="text-2xl font-bold">{{ stats.totalPosts }}</p>
          <p class="text-xs text-muted-foreground">Příspěvků</p>
        </div>
        <div class="p-4 rounded-2xl bg-card/50 border border-border/50">
          <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center mb-2"><AlertTriangle class="w-5 h-5 text-orange-500" /></div>
          <p class="text-2xl font-bold">{{ stats.pendingReports }}</p>
          <p class="text-xs text-muted-foreground">Čeká reportů</p>
        </div>
        <div class="p-4 rounded-2xl bg-card/50 border border-border/50">
          <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center mb-2"><Ban class="w-5 h-5 text-red-500" /></div>
          <p class="text-2xl font-bold">{{ stats.bannedUsers }}</p>
          <p class="text-xs text-muted-foreground">Zabanovaní</p>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="flex gap-2 mb-6 overflow-x-auto hide-scrollbar">
        <button @click="activeTab = 'users'" :class="['px-5 py-2.5 rounded-xl text-sm font-semibold transition-all whitespace-nowrap', activeTab === 'users' ? 'bg-gradient-to-r from-primary to-pink-500 text-white shadow-lg shadow-primary/25' : 'bg-secondary/50 text-muted-foreground hover:bg-secondary']">
          <span class="flex items-center gap-2"><Users class="w-4 h-4" /> Uživatelé</span>
        </button>
        <button @click="activeTab = 'posts'" :class="['px-5 py-2.5 rounded-xl text-sm font-semibold transition-all whitespace-nowrap', activeTab === 'posts' ? 'bg-gradient-to-r from-primary to-pink-500 text-white shadow-lg shadow-primary/25' : 'bg-secondary/50 text-muted-foreground hover:bg-secondary']">
          <span class="flex items-center gap-2"><Image class="w-4 h-4" /> Příspěvky</span>
        </button>
        <button @click="activeTab = 'reports'" :class="['px-5 py-2.5 rounded-xl text-sm font-semibold transition-all whitespace-nowrap relative', activeTab === 'reports' ? 'bg-gradient-to-r from-primary to-pink-500 text-white shadow-lg shadow-primary/25' : 'bg-secondary/50 text-muted-foreground hover:bg-secondary']">
          <span class="flex items-center gap-2"><AlertTriangle class="w-4 h-4" /> Reporty</span>
          <span v-if="stats.pendingReports > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-destructive text-white text-[10px] font-bold rounded-full flex items-center justify-center">{{ stats.pendingReports }}</span>
        </button>
      </div>

      <!-- Search -->
      <div v-if="activeTab === 'users'" class="relative mb-4">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        <input v-model="searchQuery" type="text" placeholder="Hledat uživatele..." class="w-full pl-11 pr-4 py-3 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20 transition-all" />
      </div>

      <!-- Users Tab -->
      <div v-if="activeTab === 'users'" class="space-y-2">
        <div v-for="user in users" :key="user.id" :class="['flex items-center gap-4 p-4 rounded-2xl border transition-all', user.is_banned ? 'bg-destructive/5 border-destructive/20' : 'bg-card/50 border-border/50 hover:bg-secondary/20']">
          <div class="relative">
            <img :src="user.avatar || '/images/default-avatar.svg'" class="w-12 h-12 rounded-xl object-cover" />
            <div v-if="user.is_banned" class="absolute -top-1 -right-1 w-5 h-5 bg-destructive rounded-full flex items-center justify-center"><Ban class="w-3 h-3 text-white" /></div>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-0.5 flex-wrap">
              <span class="font-semibold text-sm">{{ user.name }}</span>
              <span class="text-xs text-muted-foreground">@{{ user.username }}</span>
              <BadgeCheck v-if="user.is_verified" class="w-4 h-4 text-primary" />
              <Crown v-if="user.is_vip" class="w-4 h-4 text-amber-500" />
              <Shield v-if="user.is_admin" class="w-4 h-4 text-destructive" />
              <span v-if="user.is_banned" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-destructive/20 text-destructive">BAN</span>
              <Mail v-if="user.email_verified" class="w-3.5 h-3.5 text-green-500" title="Email ověřen" />
            </div>
            <p class="text-xs text-muted-foreground">{{ user.email }} · {{ user.created_at }}</p>
            <div class="flex gap-3 mt-1 text-xs text-muted-foreground">
              <span>{{ user.posts_count }} postů</span>
              <span>{{ user.followers_count }} sledujících</span>
            </div>
          </div>
          <div class="flex items-center gap-1.5 flex-shrink-0">
            <button @click="toggleFlag(user.id, 'is_verified', user.is_verified)" :class="['p-2 rounded-lg transition-all', user.is_verified ? 'bg-primary/20 text-primary' : 'bg-secondary/50 text-muted-foreground hover:text-foreground']" title="Verified"><BadgeCheck class="w-4 h-4" /></button>
            <button @click="toggleFlag(user.id, 'is_vip', user.is_vip)" :class="['p-2 rounded-lg transition-all', user.is_vip ? 'bg-amber-500/20 text-amber-500' : 'bg-secondary/50 text-muted-foreground hover:text-foreground']" title="VIP"><Crown class="w-4 h-4" /></button>
            <button @click="toggleFlag(user.id, 'is_creator', user.is_creator)" :class="['p-2 rounded-lg transition-all', user.is_creator ? 'bg-pink-500/20 text-pink-500' : 'bg-secondary/50 text-muted-foreground hover:text-foreground']" title="Creator"><UserCheck class="w-4 h-4" /></button>
            <button v-if="!user.is_admin" @click="banUser(user.id, user.name, user.is_banned)" :class="['p-2 rounded-lg transition-all', user.is_banned ? 'bg-green-500/20 text-green-500' : 'bg-secondary/50 text-muted-foreground hover:text-orange-500']" :title="user.is_banned ? 'Odbanovat' : 'Zabanovat'"><Ban class="w-4 h-4" /></button>
            <button v-if="!user.is_admin" @click="deleteUser(user.id, user.name)" class="p-2 rounded-lg bg-secondary/50 text-muted-foreground hover:bg-destructive/20 hover:text-destructive transition-all" title="Smazat"><Trash2 class="w-4 h-4" /></button>
          </div>
        </div>
      </div>

      <!-- Posts Tab -->
      <div v-if="activeTab === 'posts'" class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3">
        <div v-for="post in posts" :key="post.id" class="relative rounded-2xl overflow-hidden bg-card/50 border border-border/50 group">
          <div class="aspect-square"><img :src="post.image" :alt="post.caption" class="w-full h-full object-cover" loading="lazy" /></div>
          <div class="p-3">
            <p class="text-xs font-medium truncate">{{ post.user_name }}</p>
            <p v-if="post.caption" class="text-xs text-muted-foreground truncate mt-0.5">{{ post.caption }}</p>
            <div class="flex items-center gap-3 mt-1.5 text-xs text-muted-foreground">
              <span>{{ post.likes_count }} likes</span>
              <span>{{ post.comments_count }} kom.</span>
            </div>
            <p class="text-[10px] text-muted-foreground/60 mt-1">{{ post.created_at }}</p>
          </div>
          <button @click="deletePost(post.id)" class="absolute top-2 right-2 p-2 rounded-lg bg-black/60 text-white hover:bg-destructive transition-all opacity-0 group-hover:opacity-100"><Trash2 class="w-4 h-4" /></button>
        </div>
      </div>

      <!-- Reports Tab -->
      <div v-if="activeTab === 'reports'" class="space-y-3">
        <div v-for="report in reports" :key="report.id" :class="['p-4 rounded-2xl border transition-all', report.status === 'pending' ? 'bg-orange-500/5 border-orange-500/20' : 'bg-card/50 border-border/50 opacity-60']">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span :class="['px-2 py-0.5 rounded-md text-[10px] font-bold uppercase', report.status === 'pending' ? 'bg-orange-500/20 text-orange-500' : report.status === 'resolved' ? 'bg-green-500/20 text-green-500' : 'bg-muted text-muted-foreground']">{{ report.status === 'pending' ? 'Čeká' : report.status === 'resolved' ? 'Vyřešeno' : 'Zamítnuto' }}</span>
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-secondary text-foreground">{{ reasonLabels[report.reason] || report.reason }}</span>
              </div>
              <p class="text-sm"><span class="text-muted-foreground">Nahlásil:</span> <span class="font-medium">{{ report.reporter_name }}</span> <span class="text-xs text-muted-foreground">@{{ report.reporter_username }}</span></p>
              <p v-if="report.target" class="text-sm mt-0.5"><span class="text-muted-foreground">Cíl:</span> <span class="font-medium">{{ report.target.name }}</span> <span class="text-xs text-muted-foreground">({{ report.target.type === 'user' ? 'uživatel' : 'příspěvek' }})</span></p>
              <p v-if="report.notes" class="text-xs text-muted-foreground mt-1 italic">"{{ report.notes }}"</p>
              <p class="text-[10px] text-muted-foreground/60 mt-1">{{ report.created_at }}</p>
            </div>
            <div v-if="report.status === 'pending'" class="flex items-center gap-2 flex-shrink-0">
              <button @click="resolveReport(report.id)" class="p-2 rounded-lg bg-green-500/10 text-green-500 hover:bg-green-500/20 transition-all" title="Vyřešit"><CheckCircle class="w-5 h-5" /></button>
              <button @click="dismissReport(report.id)" class="p-2 rounded-lg bg-secondary/50 text-muted-foreground hover:text-foreground transition-all" title="Zamítnout"><XCircle class="w-5 h-5" /></button>
            </div>
          </div>
        </div>
        <div v-if="!reports.length" class="text-center py-16"><p class="text-muted-foreground">Žádné reporty</p></div>
      </div>

      <div v-if="(activeTab === 'users' && !users.length) || (activeTab === 'posts' && !posts.length)" class="text-center py-16">
        <p class="text-muted-foreground">Žádná data</p>
      </div>
    </div>
  </div>
</template>

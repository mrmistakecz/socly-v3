<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Eye, EyeOff, Mail, Lock, User, AtSign, Calendar, ShieldCheck, ChevronRight } from 'lucide-vue-next'

const form = useForm({
  name: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  date_of_birth: '',
  terms: false,
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)
const step = ref(1)

const passwordStrength = computed(() => {
  const p = form.password
  if (!p) return { level: 0, label: '', color: '' }
  let score = 0
  if (p.length >= 8) score++
  if (/[a-z]/.test(p) && /[A-Z]/.test(p)) score++
  if (/\d/.test(p)) score++
  if (/[^a-zA-Z0-9]/.test(p)) score++
  if (score <= 1) return { level: 1, label: 'Slabé', color: 'bg-destructive' }
  if (score === 2) return { level: 2, label: 'Průměrné', color: 'bg-amber-500' }
  if (score === 3) return { level: 3, label: 'Silné', color: 'bg-green-500' }
  return { level: 4, label: 'Výborné', color: 'bg-emerald-400' }
})

const maxDate = computed(() => {
  const d = new Date()
  d.setFullYear(d.getFullYear() - 18)
  return d.toISOString().split('T')[0]
})

const canProceed = computed(() => {
  if (step.value === 1) return form.name && form.username && form.email
  return true
})

const nextStep = () => {
  if (step.value < 2) step.value++
}

const prevStep = () => {
  if (step.value > 1) step.value--
}

const submit = () => {
  form.post('/register', {
    onError: (errors) => {
      if (errors.name || errors.username || errors.email) {
        step.value = 1
      }
      form.reset('password', 'password_confirmation')
    },
  })
}
</script>

<template>
  <Head title="Registrace" />
  
  <div class="min-h-dvh bg-background flex items-center justify-center p-4">
    <div class="w-full max-w-[440px]">
      <!-- Logo -->
      <div class="flex flex-col items-center mb-8">
        <h1 class="text-4xl font-black tracking-tight mb-2">
          <span class="text-gradient-premium">SOCLY</span><sup class="text-[0.4em] text-primary animate-pulse-spark align-super font-bold">;)</sup>
        </h1>
        <p class="text-sm text-muted-foreground mt-1">Vytvořte si účet</p>
      </div>

      <!-- Progress Steps -->
      <div class="flex items-center gap-3 mb-6 px-2">
        <div class="flex-1 h-1.5 rounded-full overflow-hidden bg-secondary/50">
          <div :class="['h-full rounded-full transition-all duration-500', step >= 1 ? 'bg-primary w-full' : 'w-0']" />
        </div>
        <div class="flex-1 h-1.5 rounded-full overflow-hidden bg-secondary/50">
          <div :class="['h-full rounded-full transition-all duration-500', step >= 2 ? 'bg-primary w-full' : 'w-0']" />
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-4">

        <!-- Step 1: Základní údaje -->
        <Transition name="slide" mode="out-in">
        <div v-if="step === 1" key="step1" class="glass-card p-6 space-y-4">
          <h2 class="text-lg font-bold mb-1">Základní údaje</h2>
          <p class="text-xs text-muted-foreground mb-3">Vyplňte své údaje pro vytvoření účtu</p>

          <!-- Name -->
          <div>
            <label class="block text-sm font-medium mb-2">Jméno</label>
            <div class="relative">
              <User class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full pl-11 pr-4 py-3 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20 transition-all"
                placeholder="Vaše jméno"
              />
            </div>
            <p v-if="form.errors.name" class="text-xs text-destructive mt-1">{{ form.errors.name }}</p>
          </div>

          <!-- Username -->
          <div>
            <label class="block text-sm font-medium mb-2">Uživatelské jméno</label>
            <div class="relative">
              <AtSign class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
              <input
                v-model="form.username"
                type="text"
                required
                class="w-full pl-11 pr-4 py-3 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20 transition-all"
                placeholder="username"
              />
            </div>
            <p v-if="form.errors.username" class="text-xs text-destructive mt-1">{{ form.errors.username }}</p>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium mb-2">Email</label>
            <div class="relative">
              <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
              <input
                v-model="form.email"
                type="email"
                required
                class="w-full pl-11 pr-4 py-3 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20 transition-all"
                placeholder="vas@email.cz"
              />
            </div>
            <p v-if="form.errors.email" class="text-xs text-destructive mt-1">{{ form.errors.email }}</p>
          </div>

          <!-- Next Step Button -->
          <button
            type="button"
            @click="nextStep"
            :disabled="!canProceed"
            class="w-full py-3.5 rounded-xl bg-primary text-white font-semibold flex items-center justify-center gap-2 transition-all hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Pokračovat
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
        </Transition>

        <!-- Step 2: Heslo, věk, podmínky -->
        <Transition name="slide" mode="out-in">
        <div v-if="step === 2" key="step2" class="glass-card p-6 space-y-4">
          <h2 class="text-lg font-bold mb-1">Zabezpečení a ověření</h2>
          <p class="text-xs text-muted-foreground mb-3">Nastavte si heslo a potvrďte svůj věk</p>

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium mb-2">Heslo</label>
            <div class="relative">
              <Lock class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="w-full pl-11 pr-12 py-3 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20 transition-all"
                placeholder="Minimálně 8 znaků, velká + malá + číslo"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              >
                <Eye v-if="!showPassword" class="w-5 h-5" />
                <EyeOff v-else class="w-5 h-5" />
              </button>
            </div>
            <!-- Password Strength Bar -->
            <div v-if="form.password" class="mt-2">
              <div class="flex gap-1 mb-1">
                <div v-for="i in 4" :key="i" :class="['h-1 flex-1 rounded-full transition-all', i <= passwordStrength.level ? passwordStrength.color : 'bg-secondary/50']" />
              </div>
              <p :class="['text-xs', passwordStrength.level <= 1 ? 'text-destructive' : passwordStrength.level === 2 ? 'text-amber-500' : 'text-green-500']">
                {{ passwordStrength.label }}
              </p>
            </div>
            <p v-if="form.errors.password" class="text-xs text-destructive mt-1">{{ form.errors.password }}</p>
          </div>

          <!-- Confirm Password -->
          <div>
            <label class="block text-sm font-medium mb-2">Potvrdit heslo</label>
            <div class="relative">
              <Lock class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
              <input
                v-model="form.password_confirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                required
                class="w-full pl-11 pr-12 py-3 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20 transition-all"
                placeholder="Zopakujte heslo"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              >
                <Eye v-if="!showConfirmPassword" class="w-5 h-5" />
                <EyeOff v-else class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Date of Birth -->
          <div>
            <label class="block text-sm font-medium mb-2">Datum narození</label>
            <div class="relative">
              <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
              <input
                v-model="form.date_of_birth"
                type="date"
                :max="maxDate"
                required
                class="w-full pl-11 pr-4 py-3 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20 transition-all"
              />
            </div>
            <p class="text-xs text-muted-foreground mt-1">Musíte být starší 18 let</p>
            <p v-if="form.errors.date_of_birth" class="text-xs text-destructive mt-1">{{ form.errors.date_of_birth }}</p>
          </div>

          <!-- Age verification badge -->
          <div class="flex items-center gap-3 p-3 rounded-xl bg-primary/5 border border-primary/20">
            <ShieldCheck class="w-5 h-5 text-primary flex-shrink-0" />
            <p class="text-xs text-muted-foreground">Ověření věku je ze zákona vyžadováno pro přístup k obsahu platformy.</p>
          </div>

          <!-- Terms -->
          <label class="flex items-start gap-3 cursor-pointer group">
            <input
              v-model="form.terms"
              type="checkbox"
              class="w-5 h-5 mt-0.5 rounded border-border bg-secondary accent-primary flex-shrink-0"
            />
            <span class="text-sm text-muted-foreground leading-tight">
              Souhlasím s <a href="#" class="text-primary hover:underline">Podmínkami služby</a> a <a href="#" class="text-primary hover:underline">Zásadami ochrany soukromí</a>
            </span>
          </label>
          <p v-if="form.errors.terms" class="text-xs text-destructive">{{ form.errors.terms }}</p>

          <!-- Actions -->
          <div class="flex gap-3">
            <button
              type="button"
              @click="prevStep"
              class="px-6 py-3.5 rounded-xl bg-secondary/50 text-sm font-medium hover:bg-secondary transition-all"
            >
              Zpět
            </button>
            <button
              type="submit"
              :disabled="form.processing || !form.terms"
              class="flex-1 py-3.5 rounded-xl bg-gradient-to-r from-primary via-pink-500 to-accent text-white font-bold btn-premium glow-primary transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:hover:scale-100"
            >
              <span v-if="form.processing">Registrace...</span>
              <span v-else class="flex items-center justify-center gap-2">
                <ShieldCheck class="w-5 h-5" />
                Vytvořit účet
              </span>
            </button>
          </div>
        </div>
        </Transition>

        <!-- Login Link -->
        <p class="text-center text-sm text-muted-foreground">
          Již máte účet?
          <Link href="/login" class="text-primary hover:text-primary/80 font-medium ml-1">
            Přihlaste se
          </Link>
        </p>
      </form>
    </div>
  </div>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active {
  transition: all 0.25s ease;
}
.slide-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.slide-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
</style>

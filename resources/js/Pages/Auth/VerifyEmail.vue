<script setup>
import { useForm, Head, router } from '@inertiajs/vue3'
import { Mail } from 'lucide-vue-next'

const form = useForm({})
const resend = () => form.post('/email/resend')
const logout = () => router.post('/logout')
</script>

<template>
  <Head title="Ověřit email" />
  <div class="min-h-dvh bg-background flex items-center justify-center p-4">
    <div class="w-full max-w-[400px]">
      <div class="flex flex-col items-center mb-8">
        <h1 class="text-4xl font-black tracking-tight mb-2">
          <span class="text-gradient-premium">SOCLY</span>
        </h1>
      </div>

      <div class="bg-card border border-border rounded-2xl p-6 space-y-5 text-center">
        <div class="flex justify-center">
          <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
            <Mail class="w-8 h-8 text-primary" />
          </div>
        </div>

        <div>
          <h2 class="text-lg font-bold mb-2">Ověř svůj email</h2>
          <p class="text-sm text-muted-foreground">
            Poslali jsme ti ověřovací odkaz. Zkontroluj svou schránku (i spam).
          </p>
        </div>

        <div v-if="$page.props.flash?.success" class="bg-green-500/10 border border-green-500/30 rounded-xl p-3 text-sm text-green-400">
          {{ $page.props.flash.success }}
        </div>

        <button
          @click="resend"
          :disabled="form.processing"
          class="w-full bg-muted text-foreground rounded-xl py-3 text-sm font-medium disabled:opacity-50 transition"
        >
          {{ form.processing ? 'Odesílám...' : 'Znovu odeslat ověřovací email' }}
        </button>

        <button @click="logout" class="text-xs text-muted-foreground hover:text-foreground transition">
          Odhlásit se
        </button>
      </div>
    </div>
  </div>
</template>

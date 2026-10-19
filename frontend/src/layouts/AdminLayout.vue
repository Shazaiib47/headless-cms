<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)

async function logout() {
  loggingOut.value = true
  try {
    await auth.logout()
  } finally {
    loggingOut.value = false
    await router.push({ name: 'login' })
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-6">
          <span class="text-lg font-semibold">Headless CMS</span>
          <nav aria-label="Main" class="flex items-center gap-1">
            <RouterLink
              :to="{ name: 'admin-pages' }"
              class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
              active-class="bg-slate-100 text-slate-900"
            >
              Pages
            </RouterLink>
            <RouterLink
              :to="{ name: 'admin-media' }"
              class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
              active-class="bg-slate-100 text-slate-900"
            >
              Media
            </RouterLink>
            <RouterLink
              v-if="auth.isAdmin"
              :to="{ name: 'admin-users' }"
              class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
              active-class="bg-slate-100 text-slate-900"
            >
              Users
            </RouterLink>
          </nav>
        </div>

        <div class="flex items-center gap-3 text-sm">
          <span class="text-slate-700">
            {{ auth.user?.name }}
            <span
              class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-600"
            >
              {{ auth.user?.role }}
            </span>
          </span>
          <button
            type="button"
            :disabled="loggingOut"
            class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-60"
            @click="logout"
          >
            Log out
          </button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>

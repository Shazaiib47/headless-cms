<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const submitting = ref(false)
const errorMessage = ref('')

// Only follow redirects that stay inside the admin area.
const redirectTarget = computed(() => {
  const target = route.query.redirect
  if (typeof target === 'string' && target.startsWith('/admin') && !target.startsWith('//')) {
    return target
  }
  return { name: 'admin-pages' }
})

async function submit() {
  if (submitting.value) return

  submitting.value = true
  errorMessage.value = ''

  try {
    await auth.login(email.value, password.value)
    await router.push(redirectTarget.value)
  } catch (error) {
    if (error instanceof ApiError && error.status === 429) {
      errorMessage.value = 'Too many login attempts. Please wait a minute and try again.'
    } else if (error instanceof ApiError) {
      errorMessage.value = error.firstError()
    } else {
      errorMessage.value = 'Something went wrong. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-xl font-semibold text-slate-900">Sign in</h1>
      <p class="mt-1 text-sm text-slate-600">Headless CMS administration</p>

      <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
        <div>
          <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="username"
            required
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
          />
        </div>

        <p
          v-if="errorMessage"
          role="alert"
          class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {{ errorMessage }}
        </p>

        <button
          type="submit"
          :disabled="submitting"
          class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ submitting ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>
    </div>
  </main>
</template>

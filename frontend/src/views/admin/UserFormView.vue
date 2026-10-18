<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError, type ValidationErrors } from '@/api/http'
import { usersApi, type UserInput } from '@/api/users'
import { useAuthStore } from '@/stores/auth'
import { ROLES, type Role } from '@/types/api'

const inputClass =
  'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

// null on /admin/users/new, a number on /admin/users/:id/edit
const userId = computed<number | null>(() => {
  const raw = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id
  return raw === undefined ? null : Number(raw)
})
const isEdit = computed(() => userId.value !== null)
// The API forbids changing your own role; the form mirrors that.
const isSelf = computed(() => userId.value !== null && auth.user?.id === userId.value)

const form = reactive<{ name: string; email: string; role: Role; password: string }>({
  name: '',
  email: '',
  role: 'viewer',
  password: '',
})

const loading = ref(false)
const loadError = ref('')
const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<ValidationErrors>({})
const saved = ref(false)

function errorsFor(field: string): string[] {
  return fieldErrors.value[field] ?? []
}

function errorText(error: unknown): string {
  if (error instanceof ApiError) {
    return error.status === 403 ? 'You do not have permission to do that.' : error.firstError()
  }
  return 'Something went wrong. Please try again.'
}

async function load(): Promise<void> {
  form.name = ''
  form.email = ''
  form.role = 'viewer'
  form.password = ''
  fieldErrors.value = {}
  formError.value = ''
  loadError.value = ''
  saved.value = false

  const id = userId.value
  if (id === null) {
    loading.value = false
    return
  }

  loading.value = true
  try {
    const { data } = await usersApi.get(id)
    if (userId.value !== id) return // the route changed while loading

    form.name = data.name
    form.email = data.email
    form.role = data.role
  } catch (caught) {
    if (userId.value !== id) return
    loadError.value =
      caught instanceof ApiError && caught.status === 404
        ? 'This user does not exist.'
        : errorText(caught)
  } finally {
    if (userId.value === id) loading.value = false
  }
}

async function submit(): Promise<void> {
  if (submitting.value) return

  submitting.value = true
  formError.value = ''
  fieldErrors.value = {}
  saved.value = false

  try {
    const id = userId.value

    if (id !== null) {
      const input: Partial<UserInput> = { name: form.name, email: form.email }
      if (!isSelf.value) input.role = form.role
      if (form.password) input.password = form.password

      await usersApi.update(id, input)

      form.password = ''
      saved.value = true
      // Keep the header in sync when editing your own account.
      if (isSelf.value) await auth.fetchUser()
    } else {
      await usersApi.create({
        name: form.name,
        email: form.email,
        password: form.password,
        role: form.role,
      })
      await router.push({ name: 'admin-users' })
    }
  } catch (caught) {
    if (caught instanceof ApiError && caught.status === 422 && Object.keys(caught.errors).length) {
      fieldErrors.value = caught.errors
    } else {
      formError.value = errorText(caught)
    }
  } finally {
    submitting.value = false
  }
}

watch(userId, () => void load(), { immediate: true })
</script>

<template>
  <section class="max-w-xl">
    <RouterLink :to="{ name: 'admin-users' }" class="text-sm text-slate-600 hover:underline">
      ← Back to users
    </RouterLink>
    <h1 class="mt-2 text-2xl font-semibold">{{ isEdit ? 'Edit user' : 'New user' }}</h1>

    <p v-if="loading" aria-busy="true" class="mt-6 text-sm text-slate-600">Loading user…</p>

    <p
      v-else-if="loadError"
      role="alert"
      class="mt-6 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <form v-else class="mt-6 space-y-4" novalidate @submit.prevent="submit">
      <div>
        <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          autocomplete="off"
          maxlength="255"
          required
          :class="inputClass"
          :aria-invalid="errorsFor('name').length > 0"
          :aria-describedby="errorsFor('name').length ? 'name-error' : undefined"
        />
        <p
          v-for="message in errorsFor('name')"
          :key="message"
          id="name-error"
          class="mt-1 text-sm text-red-700"
        >
          {{ message }}
        </p>
      </div>

      <div>
        <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="off"
          maxlength="255"
          required
          :class="inputClass"
          :aria-invalid="errorsFor('email').length > 0"
          :aria-describedby="errorsFor('email').length ? 'email-error' : undefined"
        />
        <p
          v-for="message in errorsFor('email')"
          :key="message"
          id="email-error"
          class="mt-1 text-sm text-red-700"
        >
          {{ message }}
        </p>
      </div>

      <div>
        <label for="role" class="block text-sm font-medium text-slate-700">Role</label>
        <select
          id="role"
          v-model="form.role"
          :disabled="isSelf"
          :class="[inputClass, 'capitalize']"
          :aria-invalid="errorsFor('role').length > 0"
          aria-describedby="role-hint"
        >
          <option v-for="role in ROLES" :key="role" :value="role">{{ role }}</option>
        </select>
        <p id="role-hint" class="mt-1 text-xs text-slate-500">
          <template v-if="isSelf">You cannot change your own role.</template>
          <template v-else
            >Viewers can read, editors can also create and edit, admins can do everything.</template
          >
        </p>
        <p v-for="message in errorsFor('role')" :key="message" class="mt-1 text-sm text-red-700">
          {{ message }}
        </p>
      </div>

      <div>
        <label for="password" class="block text-sm font-medium text-slate-700">
          {{ isEdit ? 'New password (optional)' : 'Password' }}
        </label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          :required="!isEdit"
          :class="inputClass"
          :aria-invalid="errorsFor('password').length > 0"
          aria-describedby="password-hint"
        />
        <p id="password-hint" class="mt-1 text-xs text-slate-500">
          At least 12 characters, with upper and lower case letters and a number.
          <template v-if="isEdit">Leave blank to keep the current password.</template>
        </p>
        <p
          v-for="message in errorsFor('password')"
          :key="message"
          class="mt-1 text-sm text-red-700"
        >
          {{ message }}
        </p>
      </div>

      <p v-if="formError" role="alert" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ formError }}
      </p>
      <p v-if="saved" role="status" class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">
        Changes saved.
      </p>

      <div class="flex items-center gap-3">
        <button
          type="submit"
          :disabled="submitting"
          class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Create user' }}
        </button>
        <RouterLink
          :to="{ name: 'admin-users' }"
          class="text-sm font-medium text-slate-600 hover:underline"
        >
          Cancel
        </RouterLink>
      </div>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { usersApi } from '@/api/users'
import PaginationBar from '@/components/common/PaginationBar.vue'
import RoleBadge from '@/components/common/RoleBadge.vue'
import { useAuthStore } from '@/stores/auth'
import { ROLES, type ManagedUser, type PaginationMeta, type Role } from '@/types/api'

interface Filters {
  search: string
  role: Role | ''
  sort: string
  page: number
}

const PER_PAGE = 10
const DEFAULT_SORT = 'name'
const SORT_OPTIONS = [
  { value: 'name', label: 'Name A to Z' },
  { value: '-name', label: 'Name Z to A' },
  { value: 'email', label: 'Email A to Z' },
  { value: '-created_at', label: 'Newest first' },
]

// Query-string values are user-editable, so every one is validated.
function firstString(value: unknown): string {
  const first = Array.isArray(value) ? value[0] : value
  return typeof first === 'string' ? first : ''
}

function parseRole(value: unknown): Role | '' {
  const role = firstString(value)
  return ROLES.find((candidate) => candidate === role) ?? ''
}

function parseSort(value: unknown): string {
  const sort = firstString(value)
  return SORT_OPTIONS.some((option) => option.value === sort) ? sort : DEFAULT_SORT
}

function parsePage(value: unknown): number {
  const page = Number.parseInt(firstString(value), 10)
  return Number.isFinite(page) && page >= 1 ? page : 1
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function errorText(error: unknown): string {
  if (error instanceof ApiError) {
    return error.status === 403 ? 'You do not have permission to do that.' : error.firstError()
  }
  return 'Something went wrong. Please try again.'
}

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const filters = computed<Filters>(() => ({
  search: firstString(route.query.search).trim().slice(0, 100),
  role: parseRole(route.query.role),
  sort: parseSort(route.query.sort),
  page: parsePage(route.query.page),
}))
const hasFilters = computed(() => filters.value.search !== '' || filters.value.role !== '')

const searchInput = ref(filters.value.search)
const users = ref<ManagedUser[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const error = ref('')
const notice = ref('')
const actionError = ref('')
const deletingId = ref<number | null>(null)

let requestId = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined

const statusMessage = computed(() => {
  if (loading.value) return 'Loading users'
  if (error.value || !meta.value) return ''
  return `Showing ${users.value.length} of ${meta.value.total} users`
})

function isSelf(user: ManagedUser): boolean {
  return user.id === auth.user?.id
}

async function load(): Promise<void> {
  const id = ++requestId
  loading.value = true
  error.value = ''

  try {
    const result = await usersApi.list({ ...filters.value, per_page: PER_PAGE })
    if (id !== requestId) return // a newer request superseded this one

    // The requested page no longer exists (e.g. its last row was deleted).
    if (
      result.data.length === 0 &&
      result.meta.last_page > 0 &&
      result.meta.current_page > result.meta.last_page
    ) {
      await updateQuery({ page: result.meta.last_page }, true)
      return
    }

    users.value = result.data
    meta.value = result.meta
  } catch (caught) {
    if (id !== requestId) return
    error.value = errorText(caught)
  } finally {
    if (id === requestId) loading.value = false
  }
}

function updateQuery(changes: Partial<Filters>, replace = false) {
  const next = { ...filters.value, ...changes }
  const query: Record<string, string> = {}

  if (next.search) query.search = next.search
  if (next.role) query.role = next.role
  if (next.sort !== DEFAULT_SORT) query.sort = next.sort
  if (next.page > 1) query.page = String(next.page)

  return replace ? router.replace({ query }) : router.push({ query })
}

function onRoleChange(event: Event) {
  const role = parseRole((event.target as HTMLSelectElement).value)
  void updateQuery({ role, page: 1 })
}

function onSortChange(event: Event) {
  const sort = parseSort((event.target as HTMLSelectElement).value)
  void updateQuery({ sort, page: 1 })
}

function changePage(page: number) {
  void updateQuery({ page })
}

function clearFilters() {
  searchInput.value = ''
  void updateQuery({ search: '', role: '', page: 1 })
}

async function remove(user: ManagedUser): Promise<void> {
  if (!window.confirm(`Delete ${user.name} (${user.email})? This cannot be undone.`)) return

  deletingId.value = user.id
  actionError.value = ''
  notice.value = ''

  try {
    await usersApi.remove(user.id)
    notice.value = `Deleted ${user.name}.`
    await load()
  } catch (caught) {
    actionError.value = errorText(caught)
  } finally {
    deletingId.value = null
  }
}

// Reload whenever the effective filters change (not on unrelated route changes).
watch(
  () => JSON.stringify(filters.value),
  () => {
    if (route.name === 'admin-users') void load()
  },
  { immediate: true },
)

// Debounce typing in the search box before it reaches the URL.
watch(searchInput, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    const trimmed = value.trim()
    if (trimmed !== filters.value.search) void updateQuery({ search: trimmed, page: 1 }, true)
  }, 300)
})

// Keep the box in sync when the URL changes via back/forward.
watch(
  () => filters.value.search,
  (value) => {
    if (value !== searchInput.value.trim()) searchInput.value = value
  },
)

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  requestId++ // ignore any response still in flight
})
</script>

<template>
  <section>
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold">Users</h1>
        <p v-if="meta && !error" class="mt-1 text-sm text-slate-600">
          {{ meta.total }} {{ meta.total === 1 ? 'user' : 'users' }}
        </p>
      </div>
      <RouterLink
        :to="{ name: 'admin-user-new' }"
        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
      >
        New user
      </RouterLink>
    </div>

    <form class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]" role="search" @submit.prevent>
      <div>
        <label for="user-search" class="sr-only">Search users</label>
        <input
          id="user-search"
          v-model="searchInput"
          type="search"
          maxlength="100"
          autocomplete="off"
          placeholder="Search by name or email"
          class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
        />
      </div>
      <div>
        <label for="user-role" class="sr-only">Filter by role</label>
        <select
          id="user-role"
          :value="filters.role"
          class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm capitalize focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
          @change="onRoleChange"
        >
          <option value="">All roles</option>
          <option v-for="role in ROLES" :key="role" :value="role">{{ role }}</option>
        </select>
      </div>
      <div>
        <label for="user-sort" class="sr-only">Sort users</label>
        <select
          id="user-sort"
          :value="filters.sort"
          class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
          @change="onSortChange"
        >
          <option v-for="option in SORT_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </form>

    <p class="sr-only" aria-live="polite">{{ statusMessage }}</p>

    <p
      v-if="notice"
      role="status"
      class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
    >
      {{ notice }}
    </p>
    <p
      v-if="actionError"
      role="alert"
      class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ actionError }}
    </p>

    <div
      v-if="error"
      role="alert"
      class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <span>{{ error }}</span>
      <button
        type="button"
        data-test="retry"
        class="rounded-md border border-red-300 bg-white px-3 py-1.5 font-medium text-red-800 hover:bg-red-100"
        @click="load"
      >
        Try again
      </button>
    </div>

    <ul
      v-else-if="loading && !meta"
      aria-busy="true"
      aria-label="Loading users"
      class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white"
    >
      <li v-for="n in 3" :key="n" class="animate-pulse space-y-2 px-4 py-4">
        <div class="h-4 w-1/3 rounded bg-slate-200"></div>
        <div class="h-3 w-1/4 rounded bg-slate-100"></div>
      </li>
    </ul>

    <div
      v-else-if="users.length === 0"
      class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-600"
    >
      <template v-if="hasFilters">
        <p>No users match your filters.</p>
        <button
          type="button"
          data-test="clear-filters"
          class="mt-3 rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100"
          @click="clearFilters"
        >
          Clear filters
        </button>
      </template>
      <p v-else>No users yet.</p>
    </div>

    <ul
      v-else
      :aria-busy="loading"
      class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white"
      :class="{ 'opacity-60': loading }"
    >
      <li
        v-for="user in users"
        :key="user.id"
        class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="min-w-0">
          <p class="truncate font-medium text-slate-900">
            {{ user.name }}
            <span
              v-if="isSelf(user)"
              class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600"
            >
              You
            </span>
          </p>
          <p class="truncate text-sm text-slate-500">{{ user.email }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
          <RoleBadge :role="user.role" />
          <span class="text-slate-500">Joined {{ formatDate(user.created_at) }}</span>
          <RouterLink
            :to="{ name: 'admin-user-edit', params: { id: user.id } }"
            :aria-label="`Edit ${user.name}`"
            class="rounded-md border border-slate-300 px-3 py-1 font-medium text-slate-700 hover:bg-slate-100"
          >
            Edit
          </RouterLink>
          <button
            v-if="!isSelf(user)"
            type="button"
            :aria-label="`Delete ${user.name}`"
            :disabled="deletingId === user.id"
            class="rounded-md border border-red-200 px-3 py-1 font-medium text-red-700 hover:bg-red-50 disabled:opacity-60"
            @click="remove(user)"
          >
            {{ deletingId === user.id ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </li>
    </ul>

    <PaginationBar
      v-if="meta && !error && users.length > 0"
      :meta="meta"
      class="mt-4"
      @change="changePage"
    />
  </section>
</template>

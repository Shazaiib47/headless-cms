<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError, type ValidationErrors } from '@/api/http'
import { pagesApi } from '@/api/pages'
import { fromApiBlocks, serializeBlocks } from '@/blocks/model'
import BlockEditor from '@/components/blocks/BlockEditor.vue'
import FormField from '@/components/common/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { buttonClass, inputClass } from '@/components/common/formClasses'
import { useAuthStore } from '@/stores/auth'
import type { PageDetail, PageInput, PageStatus } from '@/types/api'
import type { Block } from '@/types/blocks'
import { slugify } from '@/utils/slug'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

// null on /admin/pages/new, a number on /admin/pages/:id/edit
const pageId = computed<number | null>(() => {
  const raw = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id
  return raw === undefined ? null : Number(raw)
})
const isEdit = computed(() => pageId.value !== null)
// Viewers may open a page, but only look at it. The API enforces this regardless.
const readonly = computed(() => isEdit.value && !auth.canEdit)

const heading = computed(() => {
  if (!isEdit.value) return 'New page'
  return readonly.value ? 'View page' : 'Edit page'
})

const form = reactive<{
  title: string
  slug: string
  meta_title: string
  meta_description: string
  blocks: Block[]
}>({ title: '', slug: '', meta_title: '', meta_description: '', blocks: [] })

const status = ref<PageStatus>('draft')
const originalSlug = ref('')
const updatedAt = ref<string | null>(null)
const slugTouched = ref(false)
const savedSnapshot = ref('')

const loading = ref(false)
const loadError = ref('')
const submitting = ref(false)
const publishing = ref(false)
const formError = ref('')
const fieldErrors = ref<ValidationErrors>({})
const conflict = ref(false)
const notice = ref('')
const publishNotice = ref('')
const pendingNotice = ref('')
const summaryRef = ref<HTMLElement | null>(null)

const busy = computed(() => submitting.value || publishing.value)
const canPublish = computed(() => isEdit.value && auth.canEdit)
const isLive = computed(() => isEdit.value && status.value === 'published')
const previewHref = computed(() =>
  pageId.value === null
    ? ''
    : router.resolve({ name: 'admin-page-preview', params: { id: pageId.value } }).href,
)

const errorCount = computed(() => Object.keys(fieldErrors.value).length)
const slugWarning = computed(
  () =>
    isEdit.value &&
    status.value === 'published' &&
    form.slug !== '' &&
    form.slug !== originalSlug.value,
)

function payload(): PageInput {
  return {
    title: form.title,
    slug: form.slug,
    meta_title: form.meta_title,
    meta_description: form.meta_description,
    content: serializeBlocks(form.blocks),
  }
}

const dirty = computed(() => JSON.stringify(payload()) !== savedSnapshot.value)

function errorText(error: unknown): string {
  if (error instanceof ApiError) {
    return error.status === 403 ? 'You do not have permission to do that.' : error.firstError()
  }
  return 'Something went wrong. Please try again.'
}

function resetState(): void {
  form.title = ''
  form.slug = ''
  form.meta_title = ''
  form.meta_description = ''
  form.blocks = []
  status.value = 'draft'
  originalSlug.value = ''
  updatedAt.value = null
  slugTouched.value = false
  fieldErrors.value = {}
  formError.value = ''
  conflict.value = false
  notice.value = ''
  publishNotice.value = ''
  loadError.value = ''
  savedSnapshot.value = JSON.stringify(payload())
}

function applyPage(data: PageDetail): void {
  form.title = data.title
  form.slug = data.slug
  form.meta_title = data.meta_title ?? ''
  form.meta_description = data.meta_description ?? ''
  form.blocks = fromApiBlocks(data.content)
  status.value = data.status
  originalSlug.value = data.slug
  updatedAt.value = data.updated_at
  slugTouched.value = true
  savedSnapshot.value = JSON.stringify(payload())
}

/** After publishing: take the new status and timestamp, but leave any unsaved edits alone. */
function applyStatus(data: PageDetail): void {
  status.value = data.status
  updatedAt.value = data.updated_at
}

async function load(): Promise<void> {
  resetState()

  const id = pageId.value
  if (id === null) return

  loading.value = true
  try {
    const { data } = await pagesApi.get(id)
    if (pageId.value !== id) return // the route changed while loading

    applyPage(data)
    if (pendingNotice.value) {
      notice.value = pendingNotice.value
      pendingNotice.value = ''
    }
  } catch (caught) {
    if (pageId.value !== id) return
    loadError.value =
      caught instanceof ApiError && caught.status === 404
        ? 'This page does not exist.'
        : errorText(caught)
  } finally {
    if (pageId.value === id) loading.value = false
  }
}

async function showProblems(): Promise<void> {
  await nextTick()
  summaryRef.value?.focus()
}

function handleFailure(caught: unknown): void {
  if (caught instanceof ApiError && caught.status === 409) {
    conflict.value = true
  } else if (
    caught instanceof ApiError &&
    caught.status === 422 &&
    Object.keys(caught.errors).length > 0
  ) {
    fieldErrors.value = caught.errors
  } else {
    formError.value = errorText(caught)
  }
}

async function submit(): Promise<void> {
  if (busy.value || readonly.value) return

  submitting.value = true
  formError.value = ''
  fieldErrors.value = {}
  conflict.value = false
  notice.value = ''
  publishNotice.value = ''

  try {
    const id = pageId.value

    if (id === null) {
      const { data } = await pagesApi.create(payload())

      // Saved: nothing to warn about when the route changes to the edit screen.
      savedSnapshot.value = JSON.stringify(payload())
      pendingNotice.value = 'Page created.'
      await router.replace({ name: 'admin-page-edit', params: { id: data.id } })
    } else {
      const input: PageInput = { ...payload() }
      if (updatedAt.value) input.updated_at = updatedAt.value

      const { data } = await pagesApi.update(id, input)

      // Show exactly what the server stored (it normalises content).
      applyPage(data)
      notice.value = 'Changes saved.'
    }
  } catch (caught) {
    handleFailure(caught)
    await showProblems()
  } finally {
    submitting.value = false
  }
}

async function changePublication(action: 'publish' | 'unpublish'): Promise<void> {
  const id = pageId.value
  if (id === null || busy.value || !auth.canEdit) return
  // Only saved content can be published.
  if (action === 'publish' && dirty.value) return

  const question =
    action === 'publish'
      ? `Publish "${form.title}"? It will be visible to everyone.`
      : `Unpublish "${form.title}"? It will no longer be visible to the public.`
  if (!window.confirm(question)) return

  publishing.value = true
  formError.value = ''
  fieldErrors.value = {}
  conflict.value = false
  notice.value = ''
  publishNotice.value = ''

  try {
    const { data } =
      action === 'publish'
        ? await pagesApi.publish(id, updatedAt.value ?? undefined)
        : await pagesApi.unpublish(id, updatedAt.value ?? undefined)

    applyStatus(data)
    publishNotice.value = action === 'publish' ? 'Page published.' : 'Page unpublished.'
  } catch (caught) {
    handleFailure(caught)
    await showProblems()
  } finally {
    publishing.value = false
  }
}

// New pages: keep the slug in step with the title until it is edited by hand.
watch(
  () => form.title,
  (title) => {
    if (!isEdit.value && !slugTouched.value) form.slug = slugify(title)
  },
)

watch(pageId, () => void load(), { immediate: true })

function confirmLeave(): boolean {
  return !dirty.value || window.confirm('You have unsaved changes. Leave without saving?')
}

// A router-level guard keeps working even if Vue reuses this component while
// moving from /pages/new to /pages/:id/edit after the first save.
const removeLeaveGuard = router.beforeEach(() => confirmLeave())
onBeforeUnmount(removeLeaveGuard)

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))
</script>

<template>
  <section class="max-w-3xl">
    <RouterLink :to="{ name: 'admin-pages' }" class="text-sm text-slate-600 hover:underline">
      ← Back to pages
    </RouterLink>

    <div class="mt-2 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-semibold">{{ heading }}</h1>
      <StatusBadge v-if="isEdit && !loading && !loadError" :status="status" />
    </div>

    <p v-if="loading" aria-busy="true" class="mt-6 text-sm text-slate-600">Loading page…</p>

    <div
      v-else-if="loadError"
      role="alert"
      class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <span>{{ loadError }}</span>
      <button
        type="button"
        data-test="retry"
        class="rounded-md border border-red-300 bg-white px-3 py-1.5 font-medium text-red-800 hover:bg-red-100"
        @click="load"
      >
        Try again
      </button>
    </div>

    <template v-else>
      <div
        v-if="isEdit"
        data-test="publishing-panel"
        class="mt-4 space-y-2 rounded-lg border border-slate-200 bg-white p-4"
      >
        <div class="flex flex-wrap items-center gap-3">
          <h2 class="text-sm font-semibold text-slate-900">Publishing</h2>

          <template v-if="canPublish">
            <button
              v-if="!isLive"
              type="button"
              data-test="publish"
              :disabled="busy || dirty"
              class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePublication('publish')"
            >
              {{ publishing ? 'Publishing…' : 'Publish' }}
            </button>
            <button
              v-else
              type="button"
              data-test="unpublish"
              :disabled="busy"
              :class="buttonClass"
              @click="changePublication('unpublish')"
            >
              {{ publishing ? 'Unpublishing…' : 'Unpublish' }}
            </button>
          </template>

          <a
            :href="previewHref"
            target="_blank"
            rel="noopener noreferrer"
            data-test="preview"
            class="text-sm font-medium text-slate-700 underline hover:text-slate-900"
          >
            Preview saved version<span class="sr-only"> (opens in a new tab)</span>
          </a>
        </div>

        <p
          v-if="canPublish && !isLive && dirty"
          data-test="save-first"
          class="text-sm text-amber-800"
        >
          Save your changes before publishing.
        </p>
        <p v-if="isLive" data-test="live-notice" class="text-sm text-green-800">
          This page is live. Saving changes updates the public page immediately.
          <template v-if="dirty">Your unsaved changes are not live yet.</template>
        </p>
        <p
          v-if="publishNotice"
          data-test="publish-notice"
          role="status"
          class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
        >
          {{ publishNotice }}
        </p>
      </div>

      <form class="mt-6 space-y-6" novalidate @submit.prevent="submit">
        <p
          v-if="readonly"
          role="status"
          class="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700"
        >
          You have read-only access to this page.
        </p>

        <div
          v-if="errorCount > 0"
          ref="summaryRef"
          tabindex="-1"
          role="alert"
          class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          Please fix the {{ errorCount }} highlighted
          {{ errorCount === 1 ? 'problem' : 'problems' }} and try again.
        </div>

        <div
          v-if="conflict"
          ref="summaryRef"
          tabindex="-1"
          data-test="conflict"
          role="alert"
          class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
          <p>
            This page was changed by someone else after you opened it. Your last action was not
            applied.
          </p>
          <button
            type="button"
            data-test="reload"
            class="mt-2 rounded-md border border-amber-400 bg-white px-3 py-1.5 font-medium hover:bg-amber-100"
            @click="load"
          >
            {{ dirty ? 'Discard my changes and reload' : 'Reload latest version' }}
          </button>
        </div>

        <p
          v-if="formError"
          ref="summaryRef"
          tabindex="-1"
          role="alert"
          class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {{ formError }}
        </p>

        <fieldset :disabled="readonly" class="min-w-0 space-y-6">
          <legend class="sr-only">Page details</legend>

          <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
            <FormField id="page-title" label="Title" :errors="fieldErrors.title" v-slot="{ field }">
              <input
                v-bind="field"
                v-model="form.title"
                type="text"
                maxlength="255"
                autocomplete="off"
                :class="inputClass"
              />
            </FormField>

            <FormField
              id="page-slug"
              label="Slug"
              hint="Lowercase letters, numbers and hyphens. This becomes the page address."
              :errors="fieldErrors.slug"
              v-slot="{ field }"
            >
              <input
                v-bind="field"
                v-model="form.slug"
                type="text"
                maxlength="255"
                autocomplete="off"
                spellcheck="false"
                :class="inputClass"
                @input="slugTouched = true"
              />
            </FormField>

            <p
              v-if="slugWarning"
              role="status"
              data-test="slug-warning"
              class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900"
            >
              This page is published. Changing its slug will break its current address.
            </p>
          </div>

          <div class="space-y-3">
            <h2 class="text-lg font-semibold">Content</h2>
            <BlockEditor v-model="form.blocks" :errors="fieldErrors" :readonly="readonly" />
          </div>

          <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold">Search engines</h2>

            <FormField
              id="page-meta-title"
              label="Meta title"
              optional
              hint="Shown in search results and browser tabs."
              :errors="fieldErrors.meta_title"
              v-slot="{ field }"
            >
              <input
                v-bind="field"
                v-model="form.meta_title"
                type="text"
                maxlength="255"
                :class="inputClass"
              />
            </FormField>

            <FormField
              id="page-meta-description"
              label="Meta description"
              optional
              hint="A short summary, up to 500 characters."
              :errors="fieldErrors.meta_description"
              v-slot="{ field }"
            >
              <textarea
                v-bind="field"
                v-model="form.meta_description"
                rows="3"
                maxlength="500"
                :class="inputClass"
              />
            </FormField>
          </div>
        </fieldset>

        <p
          v-if="notice"
          role="status"
          class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
        >
          {{ notice }}
        </p>

        <div v-if="!readonly" class="flex flex-wrap items-center gap-3">
          <button
            type="submit"
            :disabled="busy"
            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Create page' }}
          </button>
          <RouterLink
            :to="{ name: 'admin-pages' }"
            class="text-sm font-medium text-slate-600 hover:underline"
          >
            Cancel
          </RouterLink>
          <span v-if="dirty" class="text-sm text-amber-700">Unsaved changes</span>
        </div>
      </form>
    </template>
  </section>
</template>

import { computed, ref } from 'vue'
import { ApiError } from '@/api/http'
import { mediaApi } from '@/api/media'
import type { MediaItem } from '@/types/api'

/** Mirrors the server's limit (config/media.php). The server remains the authority. */
export const MAX_UPLOAD_BYTES = 5 * 1024 * 1024

export type UploadStatus = 'queued' | 'uploading' | 'done' | 'error'

export interface UploadItem {
  id: number
  name: string
  status: UploadStatus
  message: string
}

function messageFor(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 413) return 'The file is too large.'
    if (error.status === 429) return 'Too many uploads. Wait a minute and try again.'
    if (error.status === 403) return 'You do not have permission to upload images.'
    if (error.status === 0) return error.message

    return error.firstError()
  }
  return 'The upload failed.'
}

/** Uploads files one at a time, in order. A failure never stops the rest of the queue. */
export function useMediaUploads(onUploaded: (media: MediaItem) => void) {
  const items = ref<UploadItem[]>([])
  const files = new Map<number, File>()
  let nextId = 1
  let running = false

  const busy = computed(() =>
    items.value.some((item) => item.status === 'queued' || item.status === 'uploading'),
  )

  function nextQueued(): UploadItem | undefined {
    return items.value.find((item) => item.status === 'queued')
  }

  async function upload(item: UploadItem): Promise<void> {
    const file = files.get(item.id)
    files.delete(item.id)

    if (!file) {
      item.status = 'error'
      item.message = 'The file is no longer available.'
      return
    }

    item.status = 'uploading'

    try {
      const { data } = await mediaApi.upload(file)
      item.status = 'done'
      onUploaded(data)
    } catch (caught) {
      item.status = 'error'
      item.message = messageFor(caught)
    }
  }

  async function run(): Promise<void> {
    if (running) return
    running = true

    try {
      let item = nextQueued()
      while (item) {
        await upload(item)
        item = nextQueued()
      }
    } finally {
      running = false
    }
  }

  function add(selected: Iterable<File>): void {
    for (const file of selected) {
      const id = nextId++

      if (file.size > MAX_UPLOAD_BYTES) {
        items.value.push({
          id,
          name: file.name,
          status: 'error',
          message: 'This file is over the 5 MB limit.',
        })
        continue
      }

      files.set(id, file)
      items.value.push({ id, name: file.name, status: 'queued', message: '' })
    }

    void run()
  }

  /** Removes one finished item from the list. Waiting and active uploads are kept. */
  function dismiss(id: number): void {
    items.value = items.value.filter(
      (item) => item.id !== id || item.status === 'queued' || item.status === 'uploading',
    )
  }

  function clearFinished(): void {
    items.value = items.value.filter(
      (item) => item.status === 'queued' || item.status === 'uploading',
    )
  }

  return { items, busy, add, dismiss, clearFinished }
}

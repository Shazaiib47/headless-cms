import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
    // optimize: false avoids a duplicate Lightning CSS pass. With Vite 8 the
    // build otherwise warned about unknown at-rules and shipped unprocessed
    // Tailwind source. Vite's own CSS minifier still compresses the output.
    tailwindcss({ optimize: false }),
    tailwindcss({ optimize: false }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
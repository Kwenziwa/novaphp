import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const backend = process.env.NOVA_BACKEND_URL || 'http://127.0.0.1:8000'

export default defineConfig({
  base: '/build/',
  plugins: [vue()],
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: backend, changeOrigin: true },
      '/docs': { target: backend, changeOrigin: true },
      '/assets/docs.css': { target: backend, changeOrigin: true },
    },
  },
  build: { outDir: 'public/build', emptyOutDir: true },
})

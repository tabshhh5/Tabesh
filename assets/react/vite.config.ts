import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    outDir: '../dist/admin-dashboard',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: 'admin-dashboard.js',
        assetFileNames: 'admin-dashboard.[ext]',
      },
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/wp-json': {
        target: 'http://localhost:8080', // WordPress dev server
        changeOrigin: true,
      },
    },
  },
})

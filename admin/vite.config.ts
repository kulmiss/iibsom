import path from 'path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { tanstackRouter } from '@tanstack/router-plugin/vite'

// Iibsom Super Admin. Built into ../public/admin-app; Laravel serves the app at /admin.
// (Not public/admin: a real folder with that name confuses `php artisan serve` / Laragon.)
// `pnpm dev` runs on http://localhost:5174/admin/ and sends API calls to Laravel (port 8095).
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/admin-app/' : '/admin/',
  plugins: [
    tanstackRouter({ target: 'react', autoCodeSplitting: true }),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: { '@': path.resolve(__dirname, './src') },
  },
  build: {
    outDir: '../public/admin-app',
    emptyOutDir: true,
    // Never inline files as data: URLs (strict CSP allows fonts only from 'self').
    assetsInlineLimit: 0,
  },
  server: {
    port: 5174,
    proxy: {
      '/admin/api': { target: 'http://localhost:8095', changeOrigin: false },
    },
  },
}))

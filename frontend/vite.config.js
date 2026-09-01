import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate', // нова версія SW підхоплюється без «застряглого» кешу
      includeAssets: ['icons/apple-touch-icon.png'],
      manifest: {
        name: 'FamilyDiet',
        short_name: 'FamilyDiet',
        description: 'Сімейне меню, журнал здоров’я і список закупівель',
        lang: 'uk',
        start_url: '/',
        display: 'standalone',
        theme_color: '#2f6b4f',
        background_color: '#f6f7f9',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          {
            src: '/icons/maskable-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        // Precache — лише app shell; дані сім'ї живі, тому /api не кешується…
        navigateFallbackDenylist: [/^\/api/],
        runtimeCaching: [
          {
            // …окрім статичного довідника інгредієнтів: він великий і потрібен автокомпліту офлайн
            urlPattern: /\/api\/ingredients\/all$/,
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'ingredients-all',
              expiration: { maxEntries: 1, maxAgeSeconds: 60 * 60 * 24 * 30 },
            },
          },
        ],
      },
    }),
  ],
  server: {
    host: '0.0.0.0',
    port: 5173,
    watch: {
      usePolling: true,
    },
    proxy: {
      '/api': {
        target: 'http://nginx:80',
        changeOrigin: true,
      },
    },
  },
})

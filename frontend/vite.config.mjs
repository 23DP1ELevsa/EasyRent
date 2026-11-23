import { fileURLToPath, URL } from 'node:url'
import Vue from '@vitejs/plugin-vue'
// Plugins
import Components from 'unplugin-vue-components/vite'
import VueRouter from 'unplugin-vue-router/vite'
// Utilities
import { defineConfig, loadEnv } from 'vite'

import { VitePWA } from 'vite-plugin-pwa'
import Vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

const config = {
  plugins: [
    VueRouter(),
    Vue({
      template: { transformAssetUrls },
    }),
    // https://github.com/vuetifyjs/vuetify-loader/tree/master/packages/vite-plugin#readme
    Vuetify({
      autoImport: true,
      styles: {
        configFile: 'src/styles/settings.scss',
      },
    }),
    Components(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.ico', 'masked-icon.svg', 'pwa-192x192.png', 'pwa-512x512.png'],
      manifest: {
        name: 'EasyRent',
        short_name: 'EasyRent',
        description: 'Transporta nomas platforma ar karti, filtriem un rezervācijām.',
        theme_color: '#00a768',
        background_color: '#f8fbf9',
        display: 'standalone',
        orientation: 'portrait',
        start_url: '/',
        scope: '/',
        icons: [
          {
            src: 'pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png',
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,jpg,svg,woff2}'],
        navigateFallbackDenylist: [
          /^\/(?:api|oauth|sanctum)(?:\/|$)/,
          /^\/(?:auth|verify-email)(?:[/?]|$)/,
        ],
      },
      devOptions: {
        enabled: false,
      },
    }),
  ],
  optimizeDeps: {
    exclude: [
      'vuetify',
      'vue-router',
      'unplugin-vue-router/runtime',
      'unplugin-vue-router/data-loaders',
      'unplugin-vue-router/data-loaders/basic',
    ],
  },
  define: { 'process.env': {} },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('src', import.meta.url)),
    },
    extensions: [
      '.js',
      '.json',
      '.jsx',
      '.mjs',
      '.ts',
      '.tsx',
      '.vue',
    ],
  },
  server: {
    port: 3000,
  },
}

export default defineConfig(({ mode }) => {
  const { VITE_API_BASE_URL } = loadEnv(mode, process.cwd())
  const proxy = VITE_API_BASE_URL
    ? Object.fromEntries(['/api', '/oauth', '/sanctum'].map(prefix => [
        prefix, { target: VITE_API_BASE_URL, changeOrigin: true },
      ]))
    : undefined

  return {
    ...config,
    server: { ...config.server, proxy },
    preview: { proxy },
  }
})

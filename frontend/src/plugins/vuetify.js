/**
 * plugins/vuetify.js
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Styles
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

// Composables
import { createVuetify } from 'vuetify'

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  theme: {
    defaultTheme: 'easyRent',
    themes: {
      easyRent: {
        dark: false,
        colors: {
          primary: '#0f766e',
          secondary: '#c96b3b',
          accent: '#153047',
          background: '#f5efe7',
          surface: '#fffaf4',
          'surface-bright': '#ffffff',
          success: '#2f855a',
          warning: '#c67f18',
          error: '#c2410c',
          info: '#2563eb',
        },
      },
      easyRentDark: {
        dark: true,
        colors: {
          primary: '#58c7bc',
          secondary: '#f09a6d',
          accent: '#e7f0f4',
          background: '#0f1720',
          surface: '#15212d',
          'surface-bright': '#1b2a38',
          success: '#4ade80',
          warning: '#f59e0b',
          error: '#fb7185',
          info: '#60a5fa',
        },
      },
    },
  },
  defaults: {
    global: {
      ripple: false,
    },
    VBtn: {
      rounded: 'xl',
      elevation: 0,
    },
    VCard: {
      rounded: 'xl',
    },
    VTextField: {
      variant: 'outlined',
      color: 'primary',
    },
    VTextarea: {
      variant: 'outlined',
      color: 'primary',
    },
    VSelect: {
      variant: 'outlined',
      color: 'primary',
    },
    VDialog: {
      scrollable: true,
    },
  },
})

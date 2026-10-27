/**
 * plugins/vuetify.js
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Composables
import { createVuetify } from 'vuetify'
// Styles
import '@mdi/font/css/materialdesignicons.css'

import 'vuetify/styles'

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  theme: {
    defaultTheme: 'easyRent',
    themes: {
      easyRent: {
        dark: false,
        colors: {
          'primary': '#00a768',
          'secondary': '#205b40',
          'accent': '#0f171c',
          'background': '#f8fbf9',
          'surface': '#ffffff',
          'surface-bright': '#ffffff',
          'success': '#2f855a',
          'warning': '#c67f18',
          'error': '#c2410c',
          'info': '#2563eb',
        },
      },
      easyRentDark: {
        dark: true,
        colors: {
          'primary': '#15bb77',
          'secondary': '#b6ead1',
          'accent': '#f0f5f5',
          'background': '#0b1116',
          'surface': '#141d23',
          'surface-bright': '#202a30',
          'success': '#4ade80',
          'warning': '#f59e0b',
          'error': '#fb7185',
          'info': '#60a5fa',
        },
      },
    },
  },
  defaults: {
    global: {
      ripple: false,
    },
    VBtn: {
      rounded: 'lg',
      elevation: 0,
    },
    VCard: {
      rounded: 'xl',
    },
    VTextField: {
      variant: 'outlined',
      color: 'primary',
      density: 'comfortable',
    },
    VTextarea: {
      variant: 'outlined',
      color: 'primary',
    },
    VSelect: {
      variant: 'outlined',
      color: 'primary',
      density: 'comfortable',
    },
    VDialog: {
      scrollable: true,
    },
  },
})

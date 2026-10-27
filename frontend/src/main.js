/**
 * main.js
 *
 * Bootstraps Vuetify and other plugins then mounts the App`
 */

// Composables
import { createApp } from 'vue'

// Plugins
import { registerPlugins } from '@/plugins'
// Components
import App from './App.vue'

import { registerPwa } from './pwa/register'

// MDI Icons
import '@mdi/font/css/materialdesignicons.css'

// Styles
import './styles/design-system.css'

const app = createApp(App)

registerPlugins(app)

registerPwa()

app.mount('#app')

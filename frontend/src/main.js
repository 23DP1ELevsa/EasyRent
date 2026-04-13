/**
 * main.js
 *
 * Bootstraps Vuetify and other plugins then mounts the App`
 */

// Plugins
import { registerPlugins } from '@/plugins'

// Components
import App from './App.vue'
import { registerPwa } from './pwa/register'

// Composables
import { createApp } from 'vue'

// MDI Icons
import '@mdi/font/css/materialdesignicons.css'

// Styles
import 'unfonts.css'
import './styles/design-system.css'

const app = createApp(App)

registerPlugins(app)

registerPwa()

app.mount('#app')

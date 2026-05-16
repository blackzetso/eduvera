import './bootstrap'
import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import VueApexCharts from 'vue3-apexcharts'
import VueSweetalert2 from 'vue-sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'
import 'vue3-toastify/dist/index.css'

// Log uncaught errors so they appear in console (helps debug white screen on production)
window.addEventListener('error', (e) => {
  console.error('[App] Uncaught error:', e.error || e.message, e.filename, e.lineno, e.colno)
})
window.addEventListener('unhandledrejection', (e) => {
  console.error('[App] Unhandled promise rejection:', e.reason)
})

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

function initInertia() {
  createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
      resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
      const app = createApp({ render: () => h(App, props) })

      app.use(plugin)
         .use(ZiggyVue)
         .use(VueApexCharts)
      //    .use(VueSweetalert2)

      app.component('apexchart', VueApexCharts)

      app.mount(el)
      try { window.dispatchEvent(new CustomEvent('inertia-mounted')) } catch (_) {}
    },
    progress: {
      color: '#4B5563',
    },
  })
}

try {
  initInertia()
} catch (err) {
  console.error('[App] Inertia init failed:', err)
  throw err
}

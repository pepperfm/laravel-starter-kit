import '../css/app.css'

import type { DefineComponent } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import ui from '@nuxt/ui/vue-plugin'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'
import { createI18n } from 'vue-i18n'
import { ZiggyVue } from 'ziggy-js'
import en from './i18n/locales/en.json'
import ru from './i18n/locales/ru.json'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel Admin'

function makeI18n(locale: string) {
  return createI18n({
    legacy: false,
    locale: locale || 'en',
    fallbackLocale: 'en',
    messages: { en, ru },
  })
}

createInertiaApp({
  progress: { color: '#f97316' },
  title: title => (title ? `${title} - ${appName}` : appName),
  resolve: name =>
    resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    const initialPage = props.initialPage
    const initialLocale = String(initialPage?.props?.locale ?? 'en')

    if (initialPage?.props?.ziggy) {
      ;(globalThis as any).Ziggy = initialPage.props.ziggy
    }

    const app = createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ui)
      .use(ZiggyVue)
      .use(makeI18n(initialLocale))

    router.on('navigate', (event: any) => {
      const page = event.detail.page
      if (page?.props?.ziggy) {
        ;(globalThis as any).Ziggy = page.props.ziggy
      }
    })

    app.mount(el)
  },
})

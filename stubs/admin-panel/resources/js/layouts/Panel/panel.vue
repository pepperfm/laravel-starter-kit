<script setup lang="ts">
import type { DropdownMenuItem, NavigationMenuItem } from '@nuxt/ui'
import type { SharedData } from '@/types'
import { Head, router, usePage } from '@inertiajs/vue3'
import { useColorMode, useMounted, useStorage } from '@vueuse/core'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { route } from 'ziggy-js'

const sidebarCollapsed = useStorage<boolean>(
  'ui:panel:sidebar-collapsed',
  false,
  undefined,
  { initOnMounted: true },
)
const sidebarOpen = ref(false)
const userMenuOpen = ref(false)

router.on('finish', () => {
  if (typeof window === 'undefined') {
    return
  }

  if (window.matchMedia('(max-width: 640px)').matches) {
    sidebarOpen.value = false
  }
})

const page = usePage<SharedData>()
const { t } = useI18n()

const appName = computed(() => page.props.app?.name ?? t('panel.app.name'))
const currentUser = computed(() => page.props.auth?.user ?? null)
const userLabel = computed(() => currentUser.value?.name ?? currentUser.value?.email ?? 'Account')

const isMounted = useMounted()
const colorMode = useColorMode()
const safeColorMode = computed(() => (isMounted.value ? colorMode.value : 'auto'))

const navigationItems = computed<NavigationMenuItem[]>(() =>
  (page.props.panel?.navigation ?? []).map((item, index) => ({
    label: item.label,
    icon: item.icon ?? undefined,
    active: route().current(item.route),
    value: `panel-nav-${index}`,
    onSelect: (event: Event) => {
      event.preventDefault()
      router.get(route(item.route))
      sidebarOpen.value = false
    },
  })),
)

const userMenuItems = computed<DropdownMenuItem[][]>(() => [
  [
    {
      type: 'label',
      label: userLabel.value,
      icon: 'i-lucide-user',
    },
  ],
  [
    {
      label: t('panel.common.theme.light'),
      icon: 'i-lucide-sun',
      type: 'checkbox',
      checked: safeColorMode.value === 'light',
      onSelect: () => {
        colorMode.value = 'light'
        userMenuOpen.value = false
      },
    },
    {
      label: t('panel.common.theme.dark'),
      icon: 'i-lucide-moon',
      type: 'checkbox',
      checked: safeColorMode.value === 'dark',
      onSelect: () => {
        colorMode.value = 'dark'
        userMenuOpen.value = false
      },
    },
  ],
])
</script>

<template>
  <Head>
    <meta
      head-key="robots"
      name="robots"
      content="noindex,follow"
    >
  </Head>

  <UApp :toaster="{ expand: true }">
    <UDashboardGroup unit="rem">
      <UDashboardSidebar
        id="panel"
        v-model:collapsed="sidebarCollapsed"
        v-model:open="sidebarOpen"
        collapsible
        :size="5"
        :collapsed-size="5"
        class="
          bg-elevated/25
          sm:data-[collapsed=false]:!w-[17rem]
          sm:data-[collapsed=true]:!w-[5rem]
          max-sm:!w-[18rem]
        "
        :ui="{ footer: 'border-t border-default' }"
      >
        <template #header="{ collapsed }">
          <div class="flex w-full items-center justify-between gap-2 px-3 py-4">
            <UButton
              variant="ghost"
              class="flex items-center gap-2 text-left text-sm font-semibold uppercase"
              @click="router.get(route('panel.dashboard'))"
            >
              <UIcon name="i-lucide-layout-dashboard" class="size-5" />
              <span v-if="!collapsed">{{ appName }}</span>
            </UButton>
          </div>
        </template>

        <template #default="{ collapsed }">
          <div class="flex h-full flex-col">
            <div class="flex-1 overflow-y-auto">
              <UNavigationMenu
                :items="navigationItems"
                orientation="vertical"
                :collapsed="collapsed"
                :tooltip="collapsed"
                class="py-4"
                :ui="{
                  root: 'flex flex-col gap-2 px-2',
                  list: 'flex flex-col gap-2',
                  link: 'rounded-md text-sm font-medium px-3 py-2 hover:bg-primary-500/10 data-[active=true]:bg-primary-500/10 data-[active=true]:text-primary-500',
                }"
              />
            </div>

            <UButton
              variant="ghost"
              color="neutral"
              size="sm"
              icon="i-lucide-home"
              :label="collapsed ? undefined : t('panel.common.back_to_site')"
              :aria-label="t('panel.common.back_to_site')"
              :block="!collapsed"
              :square="collapsed"
              :ui="{ base: collapsed ? 'justify-center' : 'justify-start' }"
              @click="router.visit('/')"
            />
          </div>
        </template>

        <template #footer="{ collapsed }">
          <UDropdownMenu
            v-model:open="userMenuOpen"
            :items="userMenuItems"
            :content="{ align: 'center', collisionPadding: 12 }"
            :ui="{ content: collapsed ? 'w-48' : 'w-(--reka-dropdown-menu-trigger-width)' }"
          >
            <UButton
              color="neutral"
              variant="ghost"
              class="w-full data-[state=open]:bg-elevated"
              :block="!collapsed"
              :square="collapsed"
              icon="i-lucide-user"
              :label="collapsed ? undefined : userLabel"
              :trailing-icon="collapsed ? undefined : 'i-lucide-chevrons-up-down'"
              :ui="{ trailingIcon: 'text-[var(--ui-text-muted)]' }"
            />
          </UDropdownMenu>
        </template>
      </UDashboardSidebar>

      <slot />
    </UDashboardGroup>
  </UApp>
</template>

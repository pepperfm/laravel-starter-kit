import type { PageProps } from '@inertiajs/core'

export interface PanelNavigationItem {
  label: string
  route: string
  icon?: string | null
}

export interface SharedData extends PageProps {
  app?: {
    name?: string
    url?: string
  }
  auth?: {
    user?: {
      id: string | number
      name?: string | null
      email?: string | null
    } | null
  }
  locale?: string
  panel?: {
    navigation?: PanelNavigationItem[]
  }
}

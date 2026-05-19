<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import Panel from '@/layouts/Panel/panel.vue'
import PanelPageNavbar from './components/PanelPageNavbar.vue'

defineOptions({ layout: Panel })

const { t } = useI18n()
</script>

<template>
  <UDashboardPanel>
    <template #header>
      <PanelPageNavbar :title="t('panel.dashboard.title')" />
    </template>

    <template #body>
      <UPageSection>
        <UPageCard
          icon="i-lucide-layout-dashboard"
          :title="t('panel.dashboard.title')"
          :description="t('panel.dashboard.description')"
        />
      </UPageSection>
    </template>
  </UDashboardPanel>
</template>

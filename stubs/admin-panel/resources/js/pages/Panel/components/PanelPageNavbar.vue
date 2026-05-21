<script setup lang="ts">
defineProps<{
  title: string
}>()
</script>

<template>
  <UDashboardNavbar :title="title" toggle>
    <template #leading>
      <UDashboardSidebarCollapse variant="subtle" />
    </template>

    <template #right>
      <slot name="actions" />
    </template>
  </UDashboardNavbar>
</template>

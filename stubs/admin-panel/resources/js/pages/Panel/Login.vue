<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { route } from 'ziggy-js'

const { t } = useI18n()
const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post(route('panel.login.store'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <Head :title="t('panel.auth.title')">
    <meta head-key="robots" name="robots" content="noindex,nofollow">
  </Head>

  <UApp>
    <main class="bg-default flex min-h-screen items-center justify-center p-4">
      <UCard class="w-full max-w-md">
        <template #header>
          <div class="space-y-1">
            <h1 class="text-highlighted text-xl font-semibold">{{ t('panel.auth.title') }}</h1>
            <p class="text-muted text-sm">{{ t('panel.auth.subtitle') }}</p>
          </div>
        </template>

        <form class="space-y-4" @submit.prevent="submit">
          <UFormField :label="t('panel.auth.email')" :error="form.errors.email">
            <UInput
              v-model="form.email"
              type="email"
              autocomplete="username"
              name="email"
              required
              autofocus
              class="w-full"
            />
          </UFormField>

          <UFormField :label="t('panel.auth.password')" :error="form.errors.password">
            <UInput
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              name="password"
              required
              class="w-full"
            />
          </UFormField>

          <UCheckbox v-model="form.remember" :label="t('panel.auth.remember')" />

          <UButton
            type="submit"
            icon="i-lucide-log-in"
            block
            :loading="form.processing"
            :disabled="form.processing"
          >
            {{ t('panel.auth.submit') }}
          </UButton>
        </form>
      </UCard>
    </main>
  </UApp>
</template>

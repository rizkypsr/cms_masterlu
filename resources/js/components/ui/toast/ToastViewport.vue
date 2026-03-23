<script setup lang="ts">
import { ToastViewport } from 'reka-ui'
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <ToastViewport
    :class="cn(
      'fixed top-0 right-0 z-[100] flex max-h-screen w-full flex-col p-4 md:max-w-[420px]',
      $props.class,
    )"
  />
</template>

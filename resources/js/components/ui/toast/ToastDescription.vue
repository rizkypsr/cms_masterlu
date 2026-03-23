<script setup lang="ts">
import { ToastDescription } from 'reka-ui'
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <ToastDescription
    :class="cn('text-sm opacity-90', $props.class)"
  >
    <slot />
  </ToastDescription>
</template>

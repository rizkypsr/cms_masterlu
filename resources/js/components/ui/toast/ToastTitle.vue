<script setup lang="ts">
import { ToastTitle } from 'reka-ui'
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <ToastTitle
    :class="cn('text-sm font-semibold', $props.class)"
  >
    <slot />
  </ToastTitle>
</template>

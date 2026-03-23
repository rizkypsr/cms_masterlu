<script setup lang="ts">
import { ToastAction } from 'reka-ui'
import { cn } from '@/lib/utils'

defineProps<{ class?: string; altText?: string }>()
</script>

<template>
  <ToastAction
    :class="cn(
      'inline-flex h-8 shrink-0 items-center justify-center rounded-md border bg-transparent px-3 text-sm font-medium ring-offset-white transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-950 focus:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 group-[.destructive]:border-red-100/40 group-[.destructive]:hover:border-red-50/30 group-[.destructive]:hover:bg-red-500 group-[.destructive]:hover:text-red-50 group-[.destructive]:focus:ring-red-400 group-[.destructive]:focus:ring-offset-red-600',
      $props.class,
    )"
    v-bind="$attrs"
  >
    <slot />
  </ToastAction>
</template>

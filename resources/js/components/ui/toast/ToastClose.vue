<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { ToastClose } from 'reka-ui'
import { cn } from '@/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <ToastClose
    :class="cn(
      'absolute right-2 top-2 rounded-md p-1 text-gray-950/50 opacity-0 transition-opacity hover:text-gray-950 focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100 group-[.destructive]:text-red-300 group-[.destructive]:hover:text-red-50 group-[.destructive]:focus:ring-red-400 group-[.destructive]:focus:ring-offset-red-600',
      $props.class,
    )"
  >
    <Icon icon="mdi:close" class="h-4 w-4" />
  </ToastClose>
</template>

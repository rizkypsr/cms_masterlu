<script setup lang="ts">
import type { ToastRootEmits, ToastRootProps } from 'reka-ui'
import { ToastRoot, useForwardPropsEmits } from 'reka-ui'
import { computed } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<ToastRootProps & { class?: string; variant?: 'default' | 'destructive' }>()
const emits = defineEmits<ToastRootEmits>()

const delegatedProps = computed(() => {
  const { class: _, variant: __, ...delegated } = props
  return delegated
})

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <ToastRoot
    v-bind="forwarded"
    :class="cn(
      'group pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-md border p-6 pr-8 shadow-lg transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--radix-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--radix-toast-swipe-move-x)] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full',
      variant === 'destructive' 
        ? 'border-red-500 bg-red-500 text-white' 
        : 'bg-white border-gray-200 text-gray-900',
      props.class,
    )"
  >
    <slot />
  </ToastRoot>
</template>

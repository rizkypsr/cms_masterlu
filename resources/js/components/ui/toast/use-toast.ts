import { ref } from 'vue'

export interface Toast {
  id: string
  title?: string
  description?: string
  action?: any
  variant?: 'default' | 'destructive'
}

const toasts = ref<Toast[]>([])

let count = 0

export function useToast() {
  const toast = ({ title, description, variant = 'default' }: Omit<Toast, 'id'>) => {
    const id = `toast-${count++}`
    toasts.value.push({ id, title, description, variant })

    setTimeout(() => {
      toasts.value = toasts.value.filter(t => t.id !== id)
    }, 3000)

    return id
  }

  return {
    toast,
    toasts,
  }
}

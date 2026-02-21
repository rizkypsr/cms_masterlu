<script setup lang="ts">
import { Input } from '@/components/ui/input';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const handleUpdate = (value: string | number) => {
    emit('update:modelValue', String(value));
};
</script>

<template>
    <Input
        type="time"
        :model-value="modelValue"
        @update:model-value="handleUpdate"
        step="1"
        class="bg-background appearance-none [&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-calendar-picker-indicator]:appearance-none"
    />
</template>

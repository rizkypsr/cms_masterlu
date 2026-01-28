<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh w-full">
        <!-- Left side - Background Image -->
        <div class="hidden lg:block lg:w-1/2 relative">
            <img
                src="/bg.jpg"
                alt="Background"
                class="absolute inset-0 h-full w-full object-cover"
            />
        </div>

        <!-- Right side - Form -->
        <div
            class="flex w-full flex-col items-center justify-center bg-background px-6 py-12 lg:w-1/2"
        >
            <div class="w-full max-w-md">
                <div class="flex flex-col gap-8">
                    <div class="space-y-2 text-center">
                        <h1 class="text-3xl font-bold text-teal-700">
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

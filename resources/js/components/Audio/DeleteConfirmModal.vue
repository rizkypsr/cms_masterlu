<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Props {
    open: boolean;
    itemName: string;
    deleteUrl: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const handleDelete = () => {
    router.delete(props.deleteUrl, {
        onSuccess: () => {
            emit('update:open', false);
            emit('success');
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="top-[10%] translate-y-0 sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Hapus</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <p class="text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus <strong>{{ itemName }}</strong>?
                </p>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="emit('update:open', false)">Batal</Button>
                    <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">
                        Hapus
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

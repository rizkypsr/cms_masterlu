<script setup lang="ts">
import { ref } from 'vue';
import { Icon } from '@iconify/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import CategoryFormModal from './CategoryFormModal.vue';

interface Category {
    id: number;
    title: string;
    type: string;
    seq: number;
}

interface Props {
    open: boolean;
    categories: Category[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const formModalOpen = ref(false);
const formModalType = ref<'add' | 'edit' | 'delete'>('add');
const selectedCategory = ref<Category | null>(null);

const openFormModal = (type: 'add' | 'edit' | 'delete', category?: Category) => {
    formModalType.value = type;
    selectedCategory.value = category || null;
    formModalOpen.value = true;
};

const handleSuccess = () => {
    emit('success');
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Kelola Kategori Keyword</DialogTitle>
            </DialogHeader>

            <div class="py-4">
                <!-- Add Category Button -->
                <div class="mb-4 flex justify-end">
                    <Button 
                        @click="openFormModal('add')"
                        class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        size="sm"
                    >
                        <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                        Tambah Kategori
                    </Button>
                </div>

                <!-- Category Table -->
                <div class="max-h-96 overflow-auto rounded border">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr class="border-b border-gray-200">
                                <th class="w-20 px-4 py-2 text-left text-sm font-medium text-gray-600"></th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Kategori</th>
                                <th class="w-24 px-4 py-2 text-left text-sm font-medium text-gray-600">Urutan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="category in categories" 
                                :key="category.id"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openFormModal('edit', category)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-3 w-3" />
                                        </button>
                                        <button
                                            @click="openFormModal('delete', category)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ category.title }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ category.seq }}</td>
                            </tr>
                            <tr v-if="categories.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada kategori
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Category Form Modal -->
    <CategoryFormModal
        :open="formModalOpen"
        :type="formModalType"
        :category="selectedCategory"
        :categories="categories"
        @update:open="formModalOpen = $event"
        @success="handleSuccess"
    />
</template>

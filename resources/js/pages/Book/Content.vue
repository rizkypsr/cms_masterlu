<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface BookChapter {
    id: number;
    title: string;
    book_id: number;
    parent_id: number | null;
    parent?: BookChapter | null;
    book?: Book | null;
}

interface Book {
    id: number;
    title: string;
    book_category_id: number;
}

interface BookContent {
    id: number;
    book_chapters_id: number;
    content: string;
    page: number;
}

const props = defineProps<{
    chapter: BookChapter;
    contents: BookContent[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Page title
const pageTitle = computed(() => {
    const parts = [];
    if (props.chapter.book) {
        parts.push(props.chapter.book.title);
    }
    if (props.chapter.parent) {
        parts.push(props.chapter.parent.title);
    }
    parts.push(props.chapter.title);
    return parts.join(' / ');
});

// Search only (no pagination)
const searchQuery = ref('');

// Filtered contents
const filteredContents = computed(() => {
    if (!searchQuery.value) return props.contents;
    const query = searchQuery.value.toLowerCase();
    return props.contents.filter(c => 
        c.content.toLowerCase().includes(query) ||
        c.page.toString().includes(query)
    );
});

// Bulk delete
const selectedContentIds = ref<number[]>([]);

// Toggle content selection
const toggleContentSelection = (contentId: number) => {
    const index = selectedContentIds.value.indexOf(contentId);
    if (index > -1) {
        selectedContentIds.value.splice(index, 1);
    } else {
        selectedContentIds.value.push(contentId);
    }
};

// Select all contents
const selectAllContents = () => {
    const allIds = filteredContents.value.map(c => c.id);
    
    if (selectedContentIds.value.length === allIds.length) {
        // Deselect all
        selectedContentIds.value = [];
    } else {
        // Select all
        selectedContentIds.value = allIds;
    }
};

// Bulk delete handler
const handleBulkDelete = () => {
    if (selectedContentIds.value.length === 0) {
        alert('Pilih minimal satu konten untuk dihapus');
        return;
    }
    
    if (confirm(`Apakah Anda yakin ingin menghapus ${selectedContentIds.value.length} konten?`)) {
        router.post(`/book/content/bulk-delete`, {
            content_ids: selectedContentIds.value,
        }, {
            onSuccess: () => {
                selectedContentIds.value = [];
            },
        });
    }
};

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedContent = ref<BookContent | null>(null);
const comboboxOpen = ref(false);

// Form
const form = useForm({
    page: 1 as number,
    content: '',
});

// Halaman options
const halamanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    // Sort contents by page number
    const sortedContents = [...props.contents].sort((a, b) => a.page - b.page);
    
    sortedContents.forEach((content) => {
        options.push({
            position: content.page,
            label: `${content.page} - Halaman ${content.page}`,
        });
    });
    
    // Add next page option
    const lastPage = sortedContents.length > 0 ? Math.max(...sortedContents.map(c => c.page)) : 0;
    const nextPage = lastPage + 1;
    options.push({
        position: nextPage,
        label: `${nextPage} - (Terakhir)`,
    });
    
    return options;
});

const selectedHalamanLabel = computed(() => {
    const option = halamanOptions.value.find(opt => opt.position === form.page);
    return option?.label || 'Pilih halaman...';
});

const selectHalaman = (position: number) => {
    form.page = position;
    comboboxOpen.value = false;
};

// Open modal functions
const openModal = (type: 'add' | 'edit' | 'delete', content?: BookContent) => {
    modalType.value = type;
    selectedContent.value = content || null;

    switch (type) {
        case 'add':
            modalTitle.value = 'Tambah Konten';
            form.reset();
            form.page = (props.contents.length > 0 ? Math.max(...props.contents.map(c => c.page)) : 0) + 1;
            break;
        case 'edit':
            modalTitle.value = 'Edit Konten';
            form.page = content?.page || 1;
            form.content = content?.content || '';
            break;
        case 'delete':
            modalTitle.value = 'Hapus Data';
            break;
    }

    modalOpen.value = true;
};

// Handle submit
const handleSubmit = () => {
    if (modalType.value === 'add') {
        form.post(`/book/chapter/${props.chapter.id}/content`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedContent.value) {
        form.put(`/book/content/${selectedContent.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedContent.value) {
        router.delete(`/book/content/${selectedContent.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const goBack = () => {
    router.visit(`/book/${props.chapter.book_id}`);
};
</script>

<template>
    <Head :title="pageTitle" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <button
                            @click="goBack"
                            class="flex h-7 w-7 items-center justify-center rounded bg-gray-500 text-white hover:bg-gray-600"
                            title="Kembali"
                        >
                            <Icon icon="mdi:arrow-left" class="h-4 w-4" />
                        </button>
                        <span class="text-sm text-gray-600">Konten Buku</span>
                        <span class="text-sm font-medium text-red-500">{{ pageTitle }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            v-if="selectedContentIds.length > 0"
                            @click="handleBulkDelete"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                            size="sm"
                        >
                            <Icon icon="mdi:delete" class="mr-1 h-4 w-4" />
                            Hapus ({{ selectedContentIds.length }})
                        </Button>
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                            size="sm"
                        >
                            <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                            Tambah Data
                        </Button>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center justify-end border-b border-gray-200 px-4 py-2">
                    <div>
                        <Input 
                            v-model="searchQuery"
                            placeholder="Search..."
                            class="h-8 w-48"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-auto">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr class="border-b border-gray-200">
                                <th class="w-12 px-4 py-2 text-left">
                                    <input 
                                        type="checkbox"
                                        @change="selectAllContents"
                                        :checked="selectedContentIds.length > 0 && selectedContentIds.length === filteredContents.length"
                                        :indeterminate="selectedContentIds.length > 0 && selectedContentIds.length < filteredContents.length"
                                        class="h-4 w-4 rounded border-gray-300"
                                        title="Pilih semua"
                                    />
                                </th>
                                <th class="w-20 px-4 py-2 text-left text-sm font-medium text-gray-600"></th>
                                <th class="w-32 px-4 py-2 text-left text-sm font-medium text-gray-600">
                                    <div class="flex items-center gap-1">
                                        Halaman
                                        <Icon icon="mdi:arrow-down" class="h-3 w-3" />
                                    </div>
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Konten</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="content in filteredContents" 
                                :key="content.id"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="px-4 py-2">
                                    <input 
                                        type="checkbox"
                                        :checked="selectedContentIds.includes(content.id)"
                                        @change="toggleContentSelection(content.id)"
                                        class="h-4 w-4 rounded border-gray-300"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openModal('edit', content)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-3 w-3" />
                                        </button>
                                        <button
                                            @click="openModal('delete', content)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ content.page }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <div class="prose prose-sm max-w-none" v-html="content.content"></div>
                                </td>
                            </tr>
                            <tr v-if="filteredContents.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada data
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2">
                    <div class="text-sm text-gray-600">
                        Showing {{ filteredContents.length }} entries
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] max-h-[85vh] translate-y-0 overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="py-4">
                    <!-- Add/Edit Modal -->
                    <form v-if="modalType === 'add' || modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Halaman</label>
                            <Popover :open="comboboxOpen" @update:open="comboboxOpen = $event">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="comboboxOpen"
                                        class="w-full justify-between"
                                    >
                                        {{ selectedHalamanLabel }}
                                        <Icon icon="mdi:unfold-more-horizontal" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                    <Command>
                                        <CommandInput placeholder="Cari halaman..." />
                                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="option in halamanOptions"
                                                    :key="option.position"
                                                    :value="option.label"
                                                    @select="selectHalaman(option.position)"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="cn('mr-2 h-4 w-4', form.page === option.position ? 'opacity-100' : 'opacity-0')"
                                                    />
                                                    {{ option.label }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Konten</label>
                            <TiptapEditor v-model="form.content" height="300px"/>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'" 
                                :disabled="form.processing"
                            >
                                {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Delete Modal -->
                    <div v-else-if="modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus konten halaman <strong>{{ selectedContent?.page }}</strong>?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">
                                Hapus
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

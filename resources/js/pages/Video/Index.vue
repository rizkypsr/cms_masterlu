<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import NestedCategoryPanel, { type NestedPanelColumn } from '@/components/NestedCategoryPanel.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { cn } from '@/lib/utils';

interface Category {
    id: number;
    title: string;
    seq: number;
    parent_id: number | null;
    parent?: Category | null;
    children?: Category[];
}

interface VideoCategoryChild {
    id: number;
    title: string;
    seq: number;
}

interface VideoCategory {
    id: number;
    title: string;
    seq: number;
    parent_id: number | null;
    children?: VideoCategoryChild[];
}

const props = defineProps<{
    categories: Category[];
    lang: string;
    storeUrl: string;
    videoCategories?: VideoCategory[];
    selectedCategory?: Category | null;
    videoCategoryStoreUrl?: string | null;
}>();

const page = usePage();
const user = page.props.auth?.user;

// Page title based on language
const pageTitle = computed(() => props.lang === 'CH' ? 'Video Mandarin - Daftar Isi' : 'Video Indonesia - Topik');

// Video category panel title
const videoCategoryTitle = computed(() => {
    if (props.selectedCategory?.parent) {
        return `${props.selectedCategory.parent.title}/${props.selectedCategory.title}`;
    }
    return props.selectedCategory?.title || '';
});

// Base URL for navigation
const baseUrl = computed(() => props.lang === 'CH' ? '/video/daftar-isi' : '/video/topik');

// Modal states
const modalOpen = ref(false);
const modalType = ref<'view' | 'add' | 'edit' | 'delete'>('view');
const modalTitle = ref('');
const selectedItem = ref<Category | null>(null);
const selectedParent = ref<Category | null>(null);
const isAddingSubCategory = ref(false);
const modalContext = ref<'category' | 'videoCategory'>('category');

// Video Category modal states
const selectedVideoCategory = ref<VideoCategory | VideoCategoryChild | null>(null);
const selectedVideoCategoryParent = ref<VideoCategory | null>(null);
const isAddingVideoChild = ref(false);

// Combobox state
const comboboxOpen = ref(false);

// Form
const form = useForm({
    title: '',
    parent_id: null as number | null,
    seq: null as number | null,
});

// Combobox options for "Urutan" - position based (1, 2, 3...)
const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    if (modalContext.value === 'videoCategory') {
        // Video Category context
        let items: (VideoCategory | VideoCategoryChild)[] = [];
        
        if (isAddingVideoChild.value && selectedVideoCategoryParent.value) {
            items = selectedVideoCategoryParent.value.children || [];
        } else {
            items = props.videoCategories || [];
        }
        
        items.forEach((item, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${item.title}`,
            });
        });
        
        const lastPosition = items.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    } else {
        // Category context
        let items: Category[] = [];
        
        if (isAddingSubCategory.value && selectedParent.value) {
            items = selectedParent.value.children || [];
        } else if (modalType.value === 'edit' && selectedItem.value?.parent_id) {
            const parent = props.categories.find(c => c.id === selectedItem.value?.parent_id);
            items = parent?.children || [];
        } else {
            items = props.categories;
        }
        
        items.forEach((cat, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${cat.title}`,
            });
        });
        
        const lastPosition = items.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    }
    
    return options;
});

const selectedUrutanLabel = computed(() => {
    const option = urutanOptions.value.find(opt => opt.position === form.seq);
    return option?.label || 'Pilih urutan...';
});

// Category modal functions
const openModal = (type: 'view' | 'add' | 'edit' | 'delete', item?: Category, isNewCategory = false) => {
    modalContext.value = 'category';
    modalType.value = type;
    selectedItem.value = item || null;
    isAddingSubCategory.value = !isNewCategory && type === 'add';
    selectedParent.value = isAddingSubCategory.value ? (item || null) : null;

    switch (type) {
        case 'view':
            modalTitle.value = 'Lihat Detail';
            break;
        case 'add':
            modalTitle.value = isNewCategory ? 'Tambah Kategori' : 'Tambah Sub Kategori';
            form.reset();
            form.parent_id = isNewCategory ? null : (item?.id || null);
            if (isAddingSubCategory.value && item) {
                form.seq = (item.children?.length || 0) + 1;
            } else {
                form.seq = props.categories.length + 1;
            }
            break;
        case 'edit':
            modalTitle.value = 'Edit';
            form.title = item?.title || '';
            if (item?.parent_id) {
                const parent = props.categories.find(c => c.id === item.parent_id);
                const currentPosition = (parent?.children?.findIndex(c => c.id === item.id) ?? -1) + 1;
                form.seq = currentPosition || null;
            } else {
                const currentPosition = props.categories.findIndex(c => c.id === item?.id) + 1;
                form.seq = currentPosition || null;
            }
            break;
        case 'delete':
            modalTitle.value = 'Hapus';
            break;
    }

    modalOpen.value = true;
};

// Video Category modal functions
const openVideoCategoryModal = (type: 'view' | 'add' | 'edit' | 'delete', item?: VideoCategory | VideoCategoryChild, isNew = false, parent?: VideoCategory) => {
    modalContext.value = 'videoCategory';
    modalType.value = type;
    selectedVideoCategory.value = item || null;
    isAddingVideoChild.value = !isNew && type === 'add';
    selectedVideoCategoryParent.value = parent || null;

    switch (type) {
        case 'view':
            modalTitle.value = 'Lihat Detail';
            break;
        case 'add':
            modalTitle.value = isNew ? 'Tambah Kategori Video' : 'Tambah Sub Kategori';
            form.reset();
            if (isAddingVideoChild.value && parent) {
                form.seq = (parent.children?.length || 0) + 1;
            } else {
                form.seq = (props.videoCategories?.length || 0) + 1;
            }
            break;
        case 'edit':
            modalTitle.value = 'Edit';
            form.title = item?.title || '';
            if (isAddingVideoChild.value && parent) {
                const pos = (parent.children?.findIndex(c => c.id === item?.id) ?? -1) + 1;
                form.seq = pos || null;
            } else {
                const pos = (props.videoCategories?.findIndex(c => c.id === item?.id) ?? -1) + 1;
                form.seq = pos || null;
            }
            break;
        case 'delete':
            modalTitle.value = 'Hapus';
            break;
    }

    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalContext.value === 'videoCategory') {
        // Video Category submit
        if (modalType.value === 'add') {
            if (isAddingVideoChild.value && selectedVideoCategoryParent.value) {
                form.transform(data => ({
                    ...data,
                    parent_id: selectedVideoCategoryParent.value?.id,
                })).post(props.videoCategoryStoreUrl!, {
                    onSuccess: () => {
                        modalOpen.value = false;
                        form.reset();
                    },
                });
            } else {
                form.post(props.videoCategoryStoreUrl!, {
                    onSuccess: () => {
                        modalOpen.value = false;
                        form.reset();
                    },
                });
            }
        } else if (modalType.value === 'edit' && selectedVideoCategory.value) {
            form.put(`/video/video-category/${selectedVideoCategory.value.id}`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    form.reset();
                },
            });
        }
    } else {
        // Category submit
        if (modalType.value === 'add') {
            form.post(props.storeUrl, {
                onSuccess: () => {
                    modalOpen.value = false;
                    form.reset();
                },
            });
        } else if (modalType.value === 'edit' && selectedItem.value) {
            form.put(`/video/category/${selectedItem.value.id}`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    form.reset();
                },
            });
        }
    }
};

const handleDelete = () => {
    if (modalContext.value === 'videoCategory' && selectedVideoCategory.value) {
        router.delete(`/video/video-category/${selectedVideoCategory.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    } else if (selectedItem.value) {
        router.delete(`/video/category/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};

const selectUrutan = (position: number) => {
    form.seq = position;
    comboboxOpen.value = false;
};

const selectChildCategory = (childId: number) => {
    router.visit(`${baseUrl.value}/${childId}`);
};

// Left Panel Column Configuration
const leftPanelColumns = computed<NestedPanelColumn>(() => ({
    header: {
        accessor: 'title',
        actions: [
            {
                icon: 'mdi:navigation-variant',
                color: 'blue',
                title: 'View',
                onClick: (item) => openModal('view', item),
            },
            {
                icon: 'mdi:plus',
                color: 'green',
                title: 'Add Sub Category',
                onClick: (item) => openModal('add', item),
            },
            {
                icon: 'mdi:pencil',
                color: 'orange',
                title: 'Edit',
                onClick: (item) => openModal('edit', item),
            },
            {
                icon: 'mdi:delete',
                color: 'red',
                title: 'Delete',
                onClick: (item) => openModal('delete', item),
            },
        ],
    },
    children: {
        accessor: 'children',
        itemAccessor: 'title',
        onClick: (child) => selectChildCategory(child.id),
        actions: [
            {
                icon: 'mdi:navigation-variant',
                color: 'blue',
                title: 'View',
                onClick: (child) => openModal('view', child),
            },
            {
                icon: 'mdi:pencil',
                color: 'orange',
                title: 'Edit',
                onClick: (child) => openModal('edit', child),
            },
            {
                icon: 'mdi:delete',
                color: 'red',
                title: 'Delete',
                onClick: (child) => openModal('delete', child),
            },
        ],
    },
}));

// Right Panel Column Configuration
const rightPanelColumns = computed<NestedPanelColumn>(() => ({
    header: {
        accessor: 'title',
        actions: [
            {
                icon: 'mdi:navigation-variant',
                color: 'blue',
                title: 'View',
                onClick: (item) => openVideoCategoryModal('view', item),
            },
            {
                icon: 'mdi:plus',
                color: 'green',
                title: 'Add Sub Category',
                onClick: (item) => openVideoCategoryModal('add', undefined, false, item),
            },
            {
                icon: 'mdi:pencil',
                color: 'orange',
                title: 'Edit',
                onClick: (item) => openVideoCategoryModal('edit', item),
            },
            {
                icon: 'mdi:delete',
                color: 'red',
                title: 'Delete',
                onClick: (item) => openVideoCategoryModal('delete', item),
            },
        ],
    },
    children: {
        accessor: 'children',
        itemAccessor: 'title',
        onClick: (child) => router.visit(`/video/video-category/${child.id}`),
        actions: [
            {
                icon: 'mdi:navigation-variant',
                color: 'blue',
                title: 'View',
                onClick: (child, parent) => {
                    isAddingVideoChild.value = true;
                    openVideoCategoryModal('view', child, false, parent);
                },
            },
            {
                icon: 'mdi:pencil',
                color: 'orange',
                title: 'Edit',
                onClick: (child, parent) => {
                    isAddingVideoChild.value = true;
                    openVideoCategoryModal('edit', child, false, parent);
                },
            },
            {
                icon: 'mdi:delete',
                color: 'red',
                title: 'Delete',
                onClick: (child, parent) => {
                    isAddingVideoChild.value = true;
                    openVideoCategoryModal('delete', child, false, parent);
                },
            },
        ],
    },
}));
</script>

<template>
    <Head :title="pageTitle" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#f5f6f8] p-6">
            <h1 class="mb-6 text-xl text-gray-600">{{ pageTitle }}</h1>

            <div class="flex flex-1 gap-6 overflow-hidden">
                <!-- Left Panel: Category List -->
                <div class="flex flex-col overflow-hidden" :class="selectedCategory ? 'w-1/2' : 'w-full max-w-3xl mx-auto'">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">Kategori</span>
                        <button
                            @click="openModal('add', undefined, true)"
                            class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Category List -->
                    <NestedCategoryPanel
                        :data="categories"
                        :columns="leftPanelColumns"
                        empty-message="Belum ada kategori"
                    />
                </div>

                <!-- Right Panel: Video Category List (shown when child category is selected) -->
                <div v-if="selectedCategory" class="flex w-1/2 flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">{{ videoCategoryTitle }}</span>
                        <button
                            @click="openVideoCategoryModal('add', undefined, true)"
                            class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Video Category List -->
                    <NestedCategoryPanel
                        :data="videoCategories || []"
                        :columns="rightPanelColumns"
                        empty-message="Belum ada kategori video"
                    />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] translate-y-0 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="py-4">
                    <!-- View Modal -->
                    <div v-if="modalType === 'view'">
                        <p class="text-sm text-gray-600">
                            Detail: <strong>{{ modalContext === 'videoCategory' ? selectedVideoCategory?.title : selectedItem?.title }}</strong>
                        </p>
                    </div>

                    <!-- Add/Edit Modal -->
                    <form v-else-if="modalType === 'add' || modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                {{ modalContext === 'videoCategory' ? 'Nama' : 'Nama Kategori' }}
                            </label>
                            <Input v-model="form.title" :placeholder="modalContext === 'videoCategory' ? 'Masukkan nama' : 'Masukkan nama kategori'" />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                            <Popover :open="comboboxOpen" @update:open="comboboxOpen = $event">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="comboboxOpen"
                                        class="w-full justify-between"
                                    >
                                        {{ selectedUrutanLabel }}
                                        <Icon icon="mdi:unfold-more-horizontal" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                    <Command>
                                        <CommandInput placeholder="Cari urutan..." />
                                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="option in urutanOptions"
                                                    :key="option.position"
                                                    :value="option.label"
                                                    @select="selectUrutan(option.position)"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="cn('mr-2 h-4 w-4', form.seq === option.position ? 'opacity-100' : 'opacity-0')"
                                                    />
                                                    {{ option.label }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
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
                            Apakah Anda yakin ingin menghapus <strong>{{ modalContext === 'videoCategory' ? selectedVideoCategory?.title : selectedItem?.title }}</strong>?
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

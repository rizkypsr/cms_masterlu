<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import TimePicker from '@/components/TimePicker.vue';
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

interface AudioItem {
    id: number;
    title: string;
    url: string | null;
    duration: string | null;
    seq: number;
}

interface SubGroup {
    id: number;
    name: string;
    seq: number;
    have_child?: number;
    audios?: AudioItem[];
}

const props = defineProps<{
    categories: Category[];
    lang: string;
    storeUrl: string;
    subGroups?: SubGroup[];
    selectedCategory?: Category | null;
}>();

const page = usePage();
const user = page.props.auth?.user;

// Page title based on language
const pageTitle = computed(() => props.lang === 'CH' ? 'Audio Mandarin - Daftar Isi' : 'Audio Indonesia - Topik');

// Sub-group panel title
const subGroupTitle = computed(() => {
    if (props.selectedCategory?.parent) {
        return `${props.selectedCategory.parent.title}/${props.selectedCategory.title}`;
    }
    return props.selectedCategory?.title || '';
});

// Base URL for navigation
const baseUrl = computed(() => props.lang === 'CH' ? '/audio/daftar-isi' : '/audio/topik');

// Modal states
const modalOpen = ref(false);
const modalType = ref<'view' | 'add' | 'edit' | 'delete'>('view');
const modalTitle = ref('');
const selectedItem = ref<Category | null>(null);
const selectedParent = ref<Category | null>(null);
const isAddingSubCategory = ref(false);
const modalContext = ref<'category' | 'subGroup'>('category');

// Sub-Group modal states
const selectedSubGroup = ref<SubGroup | AudioItem | null>(null);
const selectedSubGroupParent = ref<SubGroup | null>(null);
const isAddingAudioItem = ref(false);
const subGroupModalType = ref<'group' | 'noChild' | 'editGroup' | 'editItem'>('group');

// Combobox state
const comboboxOpen = ref(false);
const groupComboboxOpen = ref(false);

// Form
const form = useForm({
    title: '',
    parent_id: null as number | null,
    seq: null as number | null,
});

// Sub-group form
const subGroupForm = useForm({
    title: '',
    url: '',
    duration: '',
    seq: null as number | null,
    group_id: null as number | null,
    new_group_name: '',
    have_child: 1 as number,
});

// Combobox options for "Urutan" - position based (1, 2, 3...)
const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    if (modalContext.value === 'subGroup') {
        // Sub-Group context
        let items: (SubGroup | AudioItem)[] = [];
        
        if (isAddingAudioItem.value && selectedSubGroupParent.value) {
            items = selectedSubGroupParent.value.audios || [];
        } else {
            items = props.subGroups || [];
        }
        
        items.forEach((item, index) => {
            const position = index + 1;
            const label = 'name' in item ? item.name : item.title;
            options.push({
                position,
                label: `${position} - ${label}`,
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

// Sub-group urutan label
const selectedSubGroupUrutanLabel = computed(() => {
    const option = urutanOptions.value.find(opt => opt.position === subGroupForm.seq);
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

const handleSubmit = () => {
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
};

const handleDelete = () => {
    if (selectedItem.value) {
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

const selectSubGroupUrutan = (position: number) => {
    subGroupForm.seq = position;
    comboboxOpen.value = false;
};

const selectChildCategory = (childId: number) => {
    router.visit(`${baseUrl.value}/${childId}`, {
        preserveScroll: true,
    });
};

// Scroll preservation
const leftPanelScroll = ref<HTMLElement | null>(null);

onMounted(() => {
    if (leftPanelScroll.value) {
        const savedScroll = sessionStorage.getItem('audio-category-left-panel');
        if (savedScroll) {
            leftPanelScroll.value.scrollTop = parseInt(savedScroll);
        }
    }
});

const saveLeftPanelScroll = () => {
    if (leftPanelScroll.value) {
        sessionStorage.setItem('audio-category-left-panel', leftPanelScroll.value.scrollTop.toString());
    }
};

// Sub-Group modal functions
const openSubGroupModal = (type: 'add' | 'edit' | 'delete', formType?: 'group' | 'noChild', item?: SubGroup | AudioItem, parent?: SubGroup) => {
    modalContext.value = 'subGroup';
    modalType.value = type;
    selectedSubGroup.value = item || null;
    selectedSubGroupParent.value = parent || null;
    
    if (type === 'add') {
        subGroupModalType.value = formType || 'group';
        if (formType === 'group') {
            modalTitle.value = 'Form Audio Group';
            subGroupForm.reset();
            subGroupForm.seq = (props.subGroups?.length || 0) + 1;
            subGroupForm.have_child = 1; // Has children
            isAddingAudioItem.value = false;
        } else if (formType === 'noChild') {
            subGroupForm.reset();
            subGroupForm.new_group_name = '';
            // If parent is provided, adding to existing group (Form Audio)
            if (parent) {
                modalTitle.value = 'Form Audio';
                subGroupForm.group_id = parent.id;
                subGroupForm.seq = (parent.audios?.length || 0) + 1;
                subGroupForm.have_child = 1; // Has children
                isAddingAudioItem.value = true;
            } else {
                // No parent - creating standalone audio (Form Audio No Child)
                modalTitle.value = 'Form Audio No Child';
                subGroupForm.seq = (props.subGroups?.length || 0) + 1;
                subGroupForm.have_child = 0; // No children
                isAddingAudioItem.value = false;
            }
        }
    } else if (type === 'edit') {
        if ('name' in item!) {
            // Editing a sub-group
            subGroupModalType.value = 'editGroup';
            modalTitle.value = 'Edit Audio Group';
            subGroupForm.title = item.name;
            const pos = (props.subGroups?.findIndex(sg => sg.id === item.id) ?? -1) + 1;
            subGroupForm.seq = pos || null;
        } else {
            // Editing an audio item
            subGroupModalType.value = 'editItem';
            modalTitle.value = 'Edit Audio';
            subGroupForm.title = item!.title;
            subGroupForm.url = item!.url || '';
            subGroupForm.duration = item!.duration || '';
            if (parent) {
                const pos = (parent.audios?.findIndex(a => a.id === item!.id) ?? -1) + 1;
                subGroupForm.seq = pos || null;
            }
        }
    } else if (type === 'delete') {
        modalTitle.value = 'Hapus';
    }
    
    modalOpen.value = true;
};

const handleSubGroupSubmit = () => {
    const categoryId = props.selectedCategory?.id;
    if (!categoryId) return;
    
    const baseUrl = props.lang === 'CH' ? '/audio/daftar-isi' : '/audio/topik';
    
    if (modalType.value === 'add') {
        if (subGroupModalType.value === 'group') {
            // Create new group only
            subGroupForm.transform(data => ({
                new_group_name: data.title,
                title: '',
                seq: data.seq,
                have_child: data.have_child,
            })).post(`${baseUrl}/category/${categoryId}/sub-group`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    subGroupForm.reset();
                },
            });
        } else if (subGroupModalType.value === 'noChild') {
            // Check if adding to existing group or creating new group without child
            if (selectedSubGroupParent.value) {
                // Adding audio to existing group (Form Audio)
                subGroupForm.transform(data => ({
                    title: data.title,
                    url: data.url,
                    duration: data.duration,
                    seq: data.seq,
                    group_id: data.group_id,
                })).post(`${baseUrl}/category/${categoryId}/sub-group`, {
                    onSuccess: () => {
                        modalOpen.value = false;
                        subGroupForm.reset();
                    },
                });
            } else {
                // Creating new group without child audio (Form Audio No Child from header)
                subGroupForm.transform(data => ({
                    new_group_name: data.title, // Use title as group name
                    title: '', // No audio item
                    seq: data.seq,
                    have_child: data.have_child, // Set to 0
                })).post(`${baseUrl}/category/${categoryId}/sub-group`, {
                    onSuccess: () => {
                        modalOpen.value = false;
                        subGroupForm.reset();
                    },
                });
            }
        }
    } else if (modalType.value === 'edit') {
        if (subGroupModalType.value === 'editGroup' && selectedSubGroup.value) {
            // Update sub-group
            subGroupForm.put(`/audio/sub-group/${selectedSubGroup.value.id}`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    subGroupForm.reset();
                },
            });
        } else if (subGroupModalType.value === 'editItem' && selectedSubGroup.value) {
            // Update audio item
            subGroupForm.put(`/audio/audio-child/${selectedSubGroup.value.id}`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    subGroupForm.reset();
                },
            });
        }
    }
};

const handleSubGroupDelete = () => {
    if (!selectedSubGroup.value) return;
    
    if ('name' in selectedSubGroup.value) {
        // Delete sub-group
        router.delete(`/audio/sub-group/${selectedSubGroup.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    } else {
        // Delete audio item
        router.delete(`/audio/audio-child/${selectedSubGroup.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};
</script>

<template>
    <Head :title="pageTitle" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
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
                    <div ref="leftPanelScroll" class="flex-1 space-y-4 overflow-y-auto bg-white p-4" @scroll="saveLeftPanelScroll">
                        <template v-if="categories.length">
                            <Card
                                v-for="category in categories"
                                :key="category.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                            >
                                <!-- Category Header -->
                                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                    <span class="font-medium text-gray-700">{{ category.title }}</span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openModal('view', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                        >
                                            <Icon icon="mdi:navigation-variant" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('add', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                        >
                                            <Icon icon="mdi:plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('edit', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('delete', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Children -->
                                <div v-if="category.children?.length">
                                    <div
                                        v-for="child in category.children"
                                        :key="child.id"
                                        class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0 cursor-pointer hover:bg-gray-50"
                                        :class="{ 'bg-blue-50': selectedCategory?.id === child.id }"
                                        @click="selectChildCategory(child.id)"
                                    >
                                        <span class="text-sm text-gray-600">{{ child.title }}</span>
                                        <div class="flex items-center gap-1" @click.stop>
                                            <button
                                                @click="openModal('view', child)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                            >
                                                <Icon icon="mdi:navigation-variant" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openModal('edit', child)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openModal('delete', child)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            >
                                                <Icon icon="mdi:delete" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </template>
                        <div v-else class="py-8 text-center text-gray-500">
                            Belum ada kategori
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Sub-Group List (shown when child category is selected) -->
                <div v-if="selectedCategory" class="flex w-1/2 flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">{{ subGroupTitle }}</span>
                        <div class="flex items-center gap-2">
                            <button
                                @click="openSubGroupModal('add', 'group')"
                                class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                title="Form Audio Group"
                            >
                                <Icon icon="mdi:plus" class="h-4 w-4" />
                            </button>
                            <button
                                @click="openSubGroupModal('add', 'noChild')"
                                class="flex h-6 w-6 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                title="Form Audio No Child"
                            >
                                <Icon icon="mdi:plus" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Sub-Group List -->
                    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                        <template v-if="subGroups?.length">
                            <Card
                                v-for="sg in subGroups"
                                :key="sg.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                            >
                                <!-- Sub-Group Header -->
                                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                    <span class="font-medium text-gray-700">{{ sg.name }}</span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            v-if="sg.have_child !== 0"
                                            @click="openSubGroupModal('add', 'noChild', undefined, sg)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Tambah Audio"
                                        >
                                            <Icon icon="mdi:plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openSubGroupModal('edit', undefined, sg)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            title="Edit Group"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openSubGroupModal('delete', undefined, sg)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            title="Hapus Group"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Audio Items -->
                                <div v-if="sg.audios?.length">
                                    <div
                                        v-for="audio in sg.audios"
                                        :key="audio.id"
                                        class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0"
                                    >
                                        <span class="text-sm text-gray-600">{{ audio.title }}</span>
                                        <div class="flex items-center gap-1">
                                            <a
                                                :href="`/audio/subtitle/${audio.id}`"
                                                target="_blank"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                                title="Subtitle"
                                            >
                                                <Icon icon="mdi:closed-caption" class="h-4 w-4" />
                                            </a>
                                            <button
                                                @click="openSubGroupModal('edit', undefined, audio, sg)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                title="Edit Audio"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openSubGroupModal('delete', undefined, audio)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                title="Hapus Audio"
                                            >
                                                <Icon icon="mdi:delete" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </template>
                        <div v-else class="py-8 text-center text-gray-500">
                            Belum ada audio
                        </div>
                    </div>
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
                            Detail: <strong>{{ selectedItem?.title }}</strong>
                        </p>
                    </div>

                    <!-- Category Add/Edit Modal -->
                    <form v-else-if="(modalType === 'add' || modalType === 'edit') && modalContext === 'category'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nama Kategori</label>
                            <Input v-model="form.title" placeholder="Masukkan nama kategori" />
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

                    <!-- Sub-Group Add/Edit Modal -->
                    <form v-else-if="(modalType === 'add' || modalType === 'edit') && modalContext === 'subGroup'" @submit.prevent="handleSubGroupSubmit" class="space-y-4">
                        <!-- Title/Name field -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                {{ subGroupModalType === 'editItem' ? 'Title' : 'Nama Audio Group' }}
                            </label>
                            <Input v-model="subGroupForm.title" :placeholder="subGroupModalType === 'editItem' ? 'Masukkan title' : 'Masukkan nama audio group'" />
                        </div>

                        <!-- URL field (only for audio items when editing or adding to existing group) -->
                        <div v-if="subGroupModalType === 'editItem' || (subGroupModalType === 'noChild' && selectedSubGroupParent)">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url</label>
                            <Input v-model="subGroupForm.url" placeholder="Masukkan url" />
                        </div>

                        <!-- Duration field (only for audio items when editing or adding to existing group) -->
                        <div v-if="subGroupModalType === 'editItem' || (subGroupModalType === 'noChild' && selectedSubGroupParent)">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                            <TimePicker v-model="subGroupForm.duration" />
                        </div>

                        <!-- Urutan field - always show for all types -->
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
                                        {{ selectedSubGroupUrutanLabel }}
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
                                                    @select="selectSubGroupUrutan(option.position)"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="cn('mr-2 h-4 w-4', subGroupForm.seq === option.position ? 'opacity-100' : 'opacity-0')"
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
                                :disabled="subGroupForm.processing"
                            >
                                {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Delete Modal -->
                    <div v-else-if="modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <strong>{{ modalContext === 'subGroup' ? (selectedSubGroup && 'name' in selectedSubGroup ? selectedSubGroup.name : selectedSubGroup?.title) : selectedItem?.title }}</strong>?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="modalContext === 'subGroup' ? handleSubGroupDelete() : handleDelete()">
                                Hapus
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

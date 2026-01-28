<script setup lang="ts">
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Icon } from '@iconify/vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface Video {
    id: number;
    title: string;
    synopsis: string | null;
    url: string | null;
    url_audio: string | null;
    seq: number;
}

interface VideoChild {
    id: number;
    title: string;
    synopsis: string | null;
    url: string | null;
    url_audio: string | null;
    seq: number;
}

interface SubVideo {
    id: number;
    name: string;
    seq: number;
    videos?: VideoChild[];
}

interface VideoCategory {
    id: number;
    title: string;
    parent?: {
        id: number;
        title: string;
    } | null;
}

const props = defineProps<{
    video: Video | null;
    videoCategory: VideoCategory;
    subVideos: SubVideo[];
    lang: string;
}>();

const page = usePage();
const user = page.props.auth?.user;

// Page title
const pageTitle = computed(() => {
    if (props.videoCategory.parent) {
        return `${props.videoCategory.parent.title}/${props.videoCategory.title}`;
    }
    return props.videoCategory.title;
});

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedItem = ref<SubVideo | VideoChild | null>(null);
const selectedParent = ref<SubVideo | null>(null);
const isAddingChild = ref(false);
const isChildItem = ref(false);

// Combobox state
const comboboxOpen = ref(false);
const groupComboboxOpen = ref(false);

// Video form
const videoForm = useForm({
    title: props.video?.title || '',
    synopsis: props.video?.synopsis || '',
    url: props.video?.url || '',
    url_audio: props.video?.url_audio || '',
});

// Sub-video form (for adding video child)
const form = useForm({
    group_id: null as number | null, // existing group id or null for new group
    new_group_name: '', // new group name if creating new
    title: '',
    synopsis: '',
    url: '',
    url_audio: '',
    seq: null as number | null,
});

// Edit sub-video group form
const groupForm = useForm({
    title: '',
    seq: null as number | null,
});

// Check if creating new group
const isCreatingNewGroup = computed(() => form.group_id === null || form.group_id === 0);

// Combobox options for "Urutan"
const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    // For editing child items or adding to existing group
    if (isChildItem.value && selectedParent.value) {
        const items = selectedParent.value.videos || [];
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
    } else if (modalType.value === 'add' && !isAddingChild.value) {
        // Adding new sub-video - urutan based on selected group or new group
        if (form.group_id && form.group_id > 0) {
            const selectedGroup = props.subVideos.find(s => s.id === form.group_id);
            const items = selectedGroup?.videos || [];
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
            // New group - just show position 1
            options.push({
                position: 1,
                label: `1 - (Terakhir)`,
            });
        }
    } else if (isAddingChild.value && selectedParent.value) {
        // Adding child to existing group via + button
        const items = selectedParent.value.videos || [];
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
        // Editing sub-video group
        props.subVideos.forEach((item, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${item.name}`,
            });
        });
        const lastPosition = props.subVideos.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    }
    
    return options;
});

// Group options for dropdown
const groupOptions = computed(() => {
    const options: { id: number; label: string }[] = [
        { id: 0, label: '- Buat group video -' },
    ];
    
    props.subVideos.forEach(group => {
        options.push({
            id: group.id,
            label: group.name,
        });
    });
    
    return options;
});

const selectedGroupLabel = computed(() => {
    if (form.group_id === null || form.group_id === 0) {
        return '- Buat group video -';
    }
    const group = props.subVideos.find(g => g.id === form.group_id);
    return group?.name || '- Buat group video -';
});

const selectedUrutanLabel = computed(() => {
    const option = urutanOptions.value.find(opt => opt.position === form.seq);
    return option?.label || 'Pilih urutan...';
});

const openModal = (type: 'add' | 'edit' | 'delete', item?: SubVideo | VideoChild, isNew = false, parent?: SubVideo, isChild = false) => {
    modalType.value = type;
    selectedItem.value = item || null;
    isAddingChild.value = !isNew && type === 'add' && parent !== undefined;
    selectedParent.value = parent || null;
    isChildItem.value = isChild;

    switch (type) {
        case 'add':
            modalTitle.value = 'Tambah Sub-Video';
            form.reset();
            groupForm.reset();
            if (isAddingChild.value && parent) {
                // Adding child to existing group via + button on group header
                form.group_id = parent.id;
                form.seq = (parent.videos?.length || 0) + 1;
            } else {
                // Adding new sub-video via main + button
                form.group_id = 0; // Default to create new group
                form.seq = 1;
            }
            break;
        case 'edit':
            if (isChild) {
                // Editing video child
                modalTitle.value = 'Edit Sub-Video';
                form.reset();
                const childItem = item as VideoChild;
                form.title = childItem?.title || '';
                form.synopsis = childItem?.synopsis || '';
                form.url = childItem?.url || '';
                form.url_audio = childItem?.url_audio || '';
                if (parent) {
                    form.group_id = parent.id;
                    const pos = (parent.videos?.findIndex(c => c.id === childItem?.id) ?? -1) + 1;
                    form.seq = pos || null;
                }
            } else {
                // Editing sub-video group
                modalTitle.value = 'Edit Group Video';
                groupForm.reset();
                const groupItem = item as SubVideo;
                groupForm.title = groupItem?.name || '';
                const pos = props.subVideos.findIndex(s => s.id === groupItem?.id) + 1;
                groupForm.seq = pos || null;
            }
            break;
        case 'delete':
            modalTitle.value = isChild ? 'Hapus Sub-Video' : 'Hapus Group Video';
            break;
    }

    modalOpen.value = true;
};

const handleVideoSubmit = () => {
    if (props.video) {
        videoForm.put(`/video/item/${props.video.id}`, {
            preserveScroll: true,
        });
    } else {
        videoForm.post(`/video/video-category/${props.videoCategory.id}/video`, {
            preserveScroll: true,
        });
    }
};

const handleSubVideoSubmit = () => {
    if (modalType.value === 'add') {
        // Adding new sub-video (video child)
        form.transform(data => ({
            title: data.title,
            synopsis: data.synopsis,
            url: data.url,
            url_audio: data.url_audio,
            seq: data.seq,
            group_id: data.group_id && data.group_id > 0 ? data.group_id : null,
            new_group_name: data.group_id === 0 || data.group_id === null ? data.new_group_name : null,
        })).post(`/video/video-category/${props.videoCategory.id}/sub-video`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        if (isChildItem.value) {
            // Update video child
            form.transform(data => ({
                title: data.title,
                synopsis: data.synopsis,
                url: data.url,
                url_audio: data.url_audio,
                seq: data.seq,
            })).put(`/video/sub-video-child/${selectedItem.value.id}`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    form.reset();
                },
            });
        } else {
            // Update sub-video group
            groupForm.put(`/video/sub-video/${selectedItem.value.id}`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    groupForm.reset();
                },
            });
        }
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        const url = isChildItem.value 
            ? `/video/sub-video-child/${selectedItem.value.id}`
            : `/video/sub-video/${selectedItem.value.id}`;
        router.delete(url, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const selectUrutan = (position: number) => {
    form.seq = position;
    comboboxOpen.value = false;
};

const selectGroupUrutan = (position: number) => {
    groupForm.seq = position;
    comboboxOpen.value = false;
};

const selectGroup = (groupId: number) => {
    form.group_id = groupId;
    groupComboboxOpen.value = false;
    // Reset urutan when group changes
    if (groupId > 0) {
        const selectedGroup = props.subVideos.find(s => s.id === groupId);
        form.seq = (selectedGroup?.videos?.length || 0) + 1;
    } else {
        form.seq = 1;
    }
};
</script>

<template>
    <Head :title="pageTitle" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] gap-6 overflow-hidden bg-[#d3dce6] p-6">
            <!-- Left Panel: Video Detail -->
            <div class="flex w-1/2 flex-col overflow-hidden">
                <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                    <span class="font-medium">Detail Video <span class="text-red-200">{{ videoCategory.title }}</span></span>
                    <button class="flex h-6 w-6 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]">
                        <Icon icon="mdi:close" class="h-4 w-4" />
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto bg-white p-4">
                    <form @submit.prevent="handleVideoSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <Input v-model="videoForm.title" placeholder="Masukkan title" />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Sinopsis</label>
                            <textarea
                                v-model="videoForm.synopsis"
                                rows="4"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                                placeholder="Masukkan sinopsis"
                            ></textarea>
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url</label>
                            <Input v-model="videoForm.url" placeholder="https://youtube.be/..." />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url Audio</label>
                            <Input v-model="videoForm.url_audio" placeholder="Masukan Url Audio" />
                        </div>
                        
                        <div class="flex justify-end pt-2">
                            <Button type="submit" class="bg-[#5bc0de] hover:bg-[#46b8da]" :disabled="videoForm.processing">
                                <Icon icon="mdi:content-save" class="mr-1 h-4 w-4" />
                                Simpan
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Panel: Sub-Video List -->
            <div class="flex w-1/2 flex-col overflow-hidden">
                <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                    <span class="font-medium text-blue-900 underline">Sub-Video</span>
                    <button
                        @click="openModal('add', undefined, true)"
                        class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                    >
                        <Icon icon="mdi:plus" class="h-4 w-4" />
                    </button>
                </div>
                
                <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                    <template v-if="subVideos.length">
                        <Card
                            v-for="subVideo in subVideos"
                            :key="subVideo.id"
                            class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                        >
                            <!-- Sub-Video Header -->
                            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                <span class="font-medium text-gray-700">{{ subVideo.name }}</span>
                                <div class="flex items-center gap-1">
                                    <button
                                        @click="openModal('add', undefined, false, subVideo)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                    >
                                        <Icon icon="mdi:plus" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('edit', subVideo)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                    >
                                        <Icon icon="mdi:pencil" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('delete', subVideo)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                    >
                                        <Icon icon="mdi:delete" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Children (Videos) -->
                            <div v-if="subVideo.videos?.length">
                                <div
                                    v-for="child in subVideo.videos"
                                    :key="child.id"
                                    class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0"
                                >
                                    <span class="text-sm text-gray-600">{{ child.title }}</span>
                                    <div class="flex items-center gap-1">
                                        <a
                                            :href="`/video/subtitle/${child.id}`"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                            title="Subtitle"
                                        >
                                            <Icon icon="mdi:closed-caption" class="h-4 w-4" />
                                        </a>
                                        <button
                                            @click="openModal('edit', child, false, subVideo, true)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('delete', child, false, subVideo, true)"
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
                        Belum ada sub-video
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] max-h-[80vh] translate-y-0 overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="py-4">
                    <!-- Add Sub-Video Modal -->
                    <form v-if="modalType === 'add'" @submit.prevent="handleSubVideoSubmit" class="space-y-4">
                        <!-- Group Video Selector -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Group Video</label>
                            <Popover :open="groupComboboxOpen" @update:open="groupComboboxOpen = $event">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="groupComboboxOpen"
                                        class="w-full justify-between"
                                    >
                                        {{ selectedGroupLabel }}
                                        <Icon icon="mdi:chevron-down" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                    <Command>
                                        <CommandInput placeholder="Cari group..." />
                                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="option in groupOptions"
                                                    :key="option.id"
                                                    :value="option.label"
                                                    @select="selectGroup(option.id)"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="cn('mr-2 h-4 w-4', form.group_id === option.id ? 'opacity-100' : 'opacity-0')"
                                                    />
                                                    {{ option.label }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- New Group Name (only shown when creating new group) -->
                        <div v-if="isCreatingNewGroup">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Group Video Baru</label>
                            <Input v-model="form.new_group_name" placeholder="Masukkan nama group baru" />
                        </div>

                        <!-- Title -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <textarea
                                v-model="form.title"
                                rows="2"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Masukkan title"
                            ></textarea>
                        </div>

                        <!-- Sinopsis -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Sinopsis</label>
                            <textarea
                                v-model="form.synopsis"
                                rows="3"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Masukkan sinopsis"
                            ></textarea>
                        </div>

                        <!-- Link / Url -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url</label>
                            <Input v-model="form.url" placeholder="https://youtu.be/x0ZNQ0YXyfE" />
                        </div>

                        <!-- Link / Url Audio -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url Audio</label>
                            <Input v-model="form.url_audio" placeholder="Masukan Url Audio" />
                        </div>
                        
                        <!-- Urutan -->
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
                                        <Icon icon="mdi:chevron-down" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
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
                                class="bg-[#337ab7] hover:bg-[#286090]" 
                                :disabled="form.processing"
                            >
                                Simpan
                            </Button>
                        </div>
                    </form>

                    <!-- Edit Modal -->
                    <form v-else-if="modalType === 'edit'" @submit.prevent="handleSubVideoSubmit" class="space-y-4">
                        <!-- Edit Video Child -->
                        <template v-if="isChildItem">
                            <!-- Title -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                                <textarea
                                    v-model="form.title"
                                    rows="2"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Masukkan title"
                                ></textarea>
                            </div>

                            <!-- Sinopsis -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Sinopsis</label>
                                <textarea
                                    v-model="form.synopsis"
                                    rows="3"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Masukkan sinopsis"
                                ></textarea>
                            </div>

                            <!-- Link / Url -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url</label>
                                <Input v-model="form.url" placeholder="https://youtu.be/x0ZNQ0YXyfE" />
                            </div>

                            <!-- Link / Url Audio -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url Audio</label>
                                <Input v-model="form.url_audio" placeholder="Masukan Url Audio" />
                            </div>
                            
                            <!-- Urutan -->
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
                                            <Icon icon="mdi:chevron-down" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
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
                                    class="bg-[#f0ad4e] hover:bg-[#eea236]" 
                                    :disabled="form.processing"
                                >
                                    Update
                                </Button>
                            </div>
                        </template>

                        <!-- Edit Group -->
                        <template v-else>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nama Group</label>
                                <Input v-model="groupForm.title" placeholder="Masukkan nama group" />
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
                                            {{ groupForm.seq ? `${groupForm.seq} -` : 'Pilih urutan...' }}
                                            <Icon icon="mdi:chevron-down" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                        <Command>
                                            <CommandInput placeholder="Cari urutan..." />
                                            <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                            <CommandList>
                                                <CommandGroup>
                                                    <CommandItem
                                                        v-for="(group, index) in subVideos"
                                                        :key="group.id"
                                                        :value="`${index + 1} - ${group.name}`"
                                                        @select="selectGroupUrutan(index + 1)"
                                                    >
                                                        <Icon
                                                            icon="mdi:check"
                                                            :class="cn('mr-2 h-4 w-4', groupForm.seq === index + 1 ? 'opacity-100' : 'opacity-0')"
                                                        />
                                                        {{ index + 1 }} - {{ group.name }}
                                                    </CommandItem>
                                                    <CommandItem
                                                        :value="`${subVideos.length + 1} - (Terakhir)`"
                                                        @select="selectGroupUrutan(subVideos.length + 1)"
                                                    >
                                                        <Icon
                                                            icon="mdi:check"
                                                            :class="cn('mr-2 h-4 w-4', groupForm.seq === subVideos.length + 1 ? 'opacity-100' : 'opacity-0')"
                                                        />
                                                        {{ subVideos.length + 1 }} - (Terakhir)
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
                                    class="bg-[#f0ad4e] hover:bg-[#eea236]" 
                                    :disabled="groupForm.processing"
                                >
                                    Update
                                </Button>
                            </div>
                        </template>
                    </form>

                    <!-- Delete Modal -->
                    <div v-else-if="modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <strong>{{ isChildItem ? (selectedItem as VideoChild)?.title : (selectedItem as SubVideo)?.name }}</strong>?
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

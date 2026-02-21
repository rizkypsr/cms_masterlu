<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

interface Audio {
    id: number;
    title: string;
    duration: string | null;
    url: string | null;
    duration: string | null;
    seq: number;
}

interface AudioItem {
    id: number;
    title: string;
    duration: string | null;
    url: string | null;
    duration: string | null;
    seq: number;
}

interface SubGroup {
    id: number;
    name: string;
    seq: number;
    audios?: AudioItem[];
}

interface AudioCategory {
    id: number;
    title: string;
    parent?: {
        id: number;
        title: string;
    } | null;
}

const props = defineProps<{
    audio: Audio | null;
    audioCategory: AudioCategory;
    subGroups: SubGroup[];
    lang: string;
}>();

const page = usePage();
const user = page.props.auth?.user;

// Page title
const pageTitle = computed(() => {
    if (props.audioCategory.parent) {
        return `${props.audioCategory.parent.title}/${props.audioCategory.title}`;
    }
    return props.audioCategory.title;
});

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedItem = ref<SubGroup | AudioItem | null>(null);
const selectedParent = ref<SubGroup | null>(null);
const isAddingChild = ref(false);
const isChildItem = ref(false);

// Combobox state
const comboboxOpen = ref(false);
const groupComboboxOpen = ref(false);

// Audio form
const audioForm = useForm({
    title: props.audio?.title || '',
    duration: props.audio?.duration || '',
    url: props.audio?.url || '',
    duration: props.audio?.duration || '',
});

// Sub-audio form (for adding audio child)
const form = useForm({
    group_id: null as number | null, // existing group id or null for new group
    new_group_name: '', // new group name if creating new
    title: '',
    duration: '',
    url: '',
    duration: '',
    seq: null as number | null,
});

// Edit sub-audio group form
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
        const items = selectedParent.value.audios || [];
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
        // Adding new sub-audio - urutan based on selected group or new group
        if (form.group_id && form.group_id > 0) {
            const selectedGroup = props.subGroups.find(s => s.id === form.group_id);
            const items = selectedGroup?.audios || [];
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
        const items = selectedParent.value.audios || [];
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
        // Editing sub-audio group
        props.subGroups.forEach((item, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${item.name}`,
            });
        });
        const lastPosition = props.subGroups.length + 1;
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
        { id: 0, label: '- Buat group audio -' },
    ];
    
    props.subGroups.forEach(group => {
        options.push({
            id: group.id,
            label: group.name,
        });
    });
    
    return options;
});

const selectedGroupLabel = computed(() => {
    if (form.group_id === null || form.group_id === 0) {
        return '- Buat group audio -';
    }
    const group = props.subGroups.find(g => g.id === form.group_id);
    return group?.name || '- Buat group audio -';
});

const selectedUrutanLabel = computed(() => {
    const option = urutanOptions.value.find(opt => opt.position === form.seq);
    return option?.label || 'Pilih urutan...';
});

const openModal = (type: 'add' | 'edit' | 'delete', item?: SubGroup | AudioItem, isNew = false, parent?: SubGroup, isChild = false) => {
    modalType.value = type;
    selectedItem.value = item || null;
    isAddingChild.value = !isNew && type === 'add' && parent !== undefined;
    selectedParent.value = parent || null;
    isChildItem.value = isChild;

    switch (type) {
        case 'add':
            modalTitle.value = 'Tambah Sub-Audio';
            form.reset();
            groupForm.reset();
            if (isAddingChild.value && parent) {
                // Adding child to existing group via + button on group header
                form.group_id = parent.id;
                form.seq = (parent.audios?.length || 0) + 1;
            } else {
                // Adding new sub-audio via main + button
                form.group_id = 0; // Default to create new group
                form.seq = 1;
            }
            break;
        case 'edit':
            if (isChild) {
                // Editing audio child
                modalTitle.value = 'Edit Sub-Audio';
                form.reset();
                const childItem = item as AudioItem;
                form.title = childItem?.title || '';
                form.duration = childItem?.duration || '';
                form.url = childItem?.url || '';
                form.duration = childItem?.duration || '';
                if (parent) {
                    form.group_id = parent.id;
                    const pos = (parent.audios?.findIndex(c => c.id === childItem?.id) ?? -1) + 1;
                    form.seq = pos || null;
                }
            } else {
                // Editing sub-audio group
                modalTitle.value = 'Edit Group Audio';
                groupForm.reset();
                const groupItem = item as SubGroup;
                groupForm.title = groupItem?.name || '';
                const pos = props.subGroups.findIndex(s => s.id === groupItem?.id) + 1;
                groupForm.seq = pos || null;
            }
            break;
        case 'delete':
            modalTitle.value = isChild ? 'Hapus Sub-Audio' : 'Hapus Group Audio';
            break;
    }

    modalOpen.value = true;
};

const handleAudioSubmit = () => {
    if (props.audio) {
        audioForm.put(`/audio/item/${props.audio.id}`, {
            preserveScroll: true,
        });
    } else {
        audioForm.post(`/audio/audio-category/${props.audioCategory.id}/audio`, {
            preserveScroll: true,
        });
    }
};

const handleSubGroupSubmit = () => {
    if (modalType.value === 'add') {
        // Adding new sub-audio (audio child)
        form.transform(data => ({
            title: data.title,
            duration: data.duration,
            url: data.url,
            duration: data.duration,
            seq: data.seq,
            group_id: data.group_id && data.group_id > 0 ? data.group_id : null,
            new_group_name: data.group_id === 0 || data.group_id === null ? data.new_group_name : null,
        })).post(`/audio/audio-category/${props.audioCategory.id}/sub-audio`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        if (isChildItem.value) {
            // Update audio child
            form.transform(data => ({
                title: data.title,
                duration: data.duration,
                url: data.url,
                duration: data.duration,
                seq: data.seq,
            })).put(`/audio/sub-audio-child/${selectedItem.value.id}`, {
                onSuccess: () => {
                    modalOpen.value = false;
                    form.reset();
                },
            });
        } else {
            // Update sub-audio group
            groupForm.put(`/audio/sub-audio/${selectedItem.value.id}`, {
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
            ? `/audio/sub-audio-child/${selectedItem.value.id}`
            : `/audio/sub-audio/${selectedItem.value.id}`;
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
        const selectedGroup = props.subGroups.find(s => s.id === groupId);
        form.seq = (selectedGroup?.audios?.length || 0) + 1;
    } else {
        form.seq = 1;
    }
};
</script>

<template>
    <Head :title="pageTitle" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] gap-6 overflow-hidden bg-[#d3dce6] p-6">
            <!-- Left Panel: Audio Detail -->
            <div class="flex w-1/2 flex-col overflow-hidden">
                <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                    <span class="font-medium">Detail Audio <span class="text-red-200">{{ audioCategory.title }}</span></span>
                    <button class="flex h-6 w-6 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]">
                        <Icon icon="mdi:close" class="h-4 w-4" />
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto bg-white p-4">
                    <form @submit.prevent="handleAudioSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <Input v-model="audioForm.title" placeholder="Masukkan title" />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                            <textarea
                                v-model="audioForm.duration"
                                rows="4"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                                placeholder="Masukkan sinopsis"
                            ></textarea>
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url</label>
                            <Input v-model="audioForm.url" placeholder="https://youtube.be/..." />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                            <Input v-model="audioForm.duration" placeholder="Masukan Url Audio" />
                        </div>
                        
                        <div class="flex justify-end pt-2">
                            <Button type="submit" class="bg-[#5bc0de] hover:bg-[#46b8da]" :disabled="audioForm.processing">
                                <Icon icon="mdi:content-save" class="mr-1 h-4 w-4" />
                                Simpan
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Panel: Sub-Audio List -->
            <div class="flex w-1/2 flex-col overflow-hidden">
                <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                    <span class="font-medium text-blue-900 underline">Sub-Audio</span>
                    <button
                        @click="openModal('add', undefined, true)"
                        class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                    >
                        <Icon icon="mdi:plus" class="h-4 w-4" />
                    </button>
                </div>
                
                <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                    <template v-if="subGroups.length">
                        <Card
                            v-for="subAudio in subGroups"
                            :key="subAudio.id"
                            class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                        >
                            <!-- Sub-Audio Header -->
                            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                <span class="font-medium text-gray-700">{{ subAudio.name }}</span>
                                <div class="flex items-center gap-1">
                                    <button
                                        @click="openModal('add', undefined, false, subAudio)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                    >
                                        <Icon icon="mdi:plus" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('edit', subAudio)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                    >
                                        <Icon icon="mdi:pencil" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('delete', subAudio)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                    >
                                        <Icon icon="mdi:delete" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Children (Audios) -->
                            <div v-if="subAudio.audios?.length">
                                <div
                                    v-for="child in subAudio.audios"
                                    :key="child.id"
                                    class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0"
                                >
                                    <span class="text-sm text-gray-600">{{ child.title }}</span>
                                    <div class="flex items-center gap-1">
                                        <a
                                            :href="`/audio/subtitle/${child.id}`"
                                            target="_blank"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                            title="Subtitle"
                                        >
                                            <Icon icon="mdi:closed-caption" class="h-4 w-4" />
                                        </a>
                                        <button
                                            @click="openModal('edit', child, false, subAudio, true)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('delete', child, false, subAudio, true)"
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
                        Belum ada sub-audio
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
                    <!-- Add Sub-Audio Modal -->
                    <form v-if="modalType === 'add'" @submit.prevent="handleSubGroupSubmit" class="space-y-4">
                        <!-- Group Audio Selector -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Group Audio</label>
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
                            <label class="mb-1 block text-sm font-medium text-gray-700">Group Audio Baru</label>
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

                        <!-- Durasi -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                            <textarea
                                v-model="form.duration"
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

                        <!-- Durasi -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                            <Input v-model="form.duration" placeholder="Masukan Url Audio" />
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
                    <form v-else-if="modalType === 'edit'" @submit.prevent="handleSubGroupSubmit" class="space-y-4">
                        <!-- Edit Audio Child -->
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

                            <!-- Durasi -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                                <textarea
                                    v-model="form.duration"
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

                            <!-- Durasi -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                                <Input v-model="form.duration" placeholder="Masukan Url Audio" />
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
                                                        v-for="(group, index) in subGroups"
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
                                                        :value="`${subGroups.length + 1} - (Terakhir)`"
                                                        @select="selectGroupUrutan(subGroups.length + 1)"
                                                    >
                                                        <Icon
                                                            icon="mdi:check"
                                                            :class="cn('mr-2 h-4 w-4', groupForm.seq === subGroups.length + 1 ? 'opacity-100' : 'opacity-0')"
                                                        />
                                                        {{ subGroups.length + 1 }} - (Terakhir)
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
                            Apakah Anda yakin ingin menghapus <strong>{{ isChildItem ? (selectedItem as AudioItem)?.title : (selectedItem as SubGroup)?.name }}</strong>?
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

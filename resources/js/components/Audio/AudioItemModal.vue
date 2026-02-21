<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

interface SubGroup {
    id: number;
    name: string;
    seq: number;
    audios?: AudioItem[];
}

interface AudioItem {
    id: number;
    title: string;
    url: string | null;
    duration: string | null;
    seq: number;
}

interface Props {
    open: boolean;
    subGroups: SubGroup[];
    selectedAudio?: AudioItem | null;
    selectedGroup?: SubGroup | null;
    categoryId: number;
    lang: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const isEdit = computed(() => !!props.selectedAudio);
const modalTitle = computed(() => isEdit.value ? 'Edit Audio' : 'Form Audio No Child');

const groupComboboxOpen = ref(false);
const urutanComboboxOpen = ref(false);

const form = useForm({
    title: '',
    url: '',
    duration: '',
    seq: 1 as number | null, // Default to last position (will be updated based on group)
    group_id: null as number | null,
    new_group_name: '',
});

// Watch for modal open/close and selectedAudio changes
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        if (props.selectedAudio) {
            // Editing existing audio
            form.title = props.selectedAudio.title;
            form.url = props.selectedAudio.url || '';
            form.duration = props.selectedAudio.duration || '';
            if (props.selectedGroup) {
                form.group_id = props.selectedGroup.id;
                const pos = (props.selectedGroup.audios?.findIndex(a => a.id === props.selectedAudio!.id) ?? -1) + 1;
                form.seq = pos || null;
            }
        } else {
            // Adding new audio - set to last position
            form.title = '';
            form.url = '';
            form.duration = '';
            form.group_id = props.selectedGroup?.id || null;
            form.new_group_name = '';
            // Default to last position
            if (props.selectedGroup) {
                form.seq = (props.selectedGroup.audios?.length || 0) + 1;
            } else {
                form.seq = 1;
            }
        }
    }
}, { immediate: true });

const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    if (form.group_id) {
        const group = props.subGroups.find(sg => sg.id === form.group_id);
        const audios = group?.audios || [];
        
        audios.forEach((audio, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${audio.title}`,
            });
        });
        
        const lastPosition = audios.length + 1;
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

const selectedGroupLabel = computed(() => {
    if (form.group_id) {
        const group = props.subGroups.find(sg => sg.id === form.group_id);
        return group?.name || 'Pilih group...';
    }
    if (form.new_group_name) {
        return '- Buat group audio -';
    }
    return 'Pilih group...';
});

const selectGroup = (groupId: number | null, isNew: boolean = false) => {
    if (isNew) {
        form.group_id = null;
        form.new_group_name = 'new';
        form.seq = 1; // First item in new group
    } else {
        form.group_id = groupId;
        form.new_group_name = '';
        // Set to last position in selected group
        const group = props.subGroups.find(sg => sg.id === groupId);
        form.seq = (group?.audios?.length || 0) + 1;
    }
    groupComboboxOpen.value = false;
};

const selectUrutan = (position: number) => {
    form.seq = position;
    urutanComboboxOpen.value = false;
};

const handleSubmit = () => {
    const baseUrl = props.lang === 'CH' ? '/audio/daftar-isi' : '/audio/topik';
    
    if (isEdit.value && props.selectedAudio) {
        // Update existing audio
        form.put(`/audio/audio-child/${props.selectedAudio.id}`, {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
                form.reset();
            },
        });
    } else {
        // Create new audio
        form.post(`${baseUrl}/category/${props.categoryId}/sub-group`, {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
                form.reset();
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="top-[10%] translate-y-0 sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ modalTitle }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-4">
                <div v-if="!isEdit">
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
                                <Icon icon="mdi:unfold-more-horizontal" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                            <Command>
                                <CommandInput placeholder="Cari group..." />
                                <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                <CommandList>
                                    <CommandGroup>
                                        <CommandItem
                                            value="new"
                                            @select="selectGroup(null, true)"
                                        >
                                            <Icon
                                                icon="mdi:check"
                                                :class="cn('mr-2 h-4 w-4', form.new_group_name ? 'opacity-100' : 'opacity-0')"
                                            />
                                            - Buat group audio -
                                        </CommandItem>
                                        <CommandItem
                                            v-for="sg in subGroups"
                                            :key="sg.id"
                                            :value="sg.name"
                                            @select="selectGroup(sg.id)"
                                        >
                                            <Icon
                                                icon="mdi:check"
                                                :class="cn('mr-2 h-4 w-4', form.group_id === sg.id ? 'opacity-100' : 'opacity-0')"
                                            />
                                            {{ sg.name }}
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                <div v-if="form.new_group_name && !isEdit">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Group Audio Baru</label>
                    <Input v-model="form.new_group_name" placeholder="Masukkan nama group baru" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                    <Input v-model="form.title" placeholder="Masukkan title" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Link / Url</label>
                    <Input v-model="form.url" placeholder="Masukkan url" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                    <Input v-model="form.duration" placeholder="Masukkan durasi" />
                </div>

                <div v-if="form.group_id && urutanOptions.length > 0">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                    <Popover :open="urutanComboboxOpen" @update:open="urutanComboboxOpen = $event">
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                role="combobox"
                                :aria-expanded="urutanComboboxOpen"
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
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Batal</Button>
                    <Button 
                        type="submit" 
                        :class="isEdit ? 'bg-[#f0ad4e] hover:bg-[#eea236]' : 'bg-[#5cb85c] hover:bg-[#4cae4c]'" 
                        :disabled="form.processing"
                    >
                        {{ isEdit ? 'Update' : 'Simpan' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

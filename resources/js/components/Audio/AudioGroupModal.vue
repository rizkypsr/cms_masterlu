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
}

interface Props {
    open: boolean;
    subGroups: SubGroup[];
    selectedGroup?: SubGroup | null;
    categoryId: number;
    lang: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const isEdit = computed(() => !!props.selectedGroup);
const modalTitle = computed(() => isEdit.value ? 'Edit Audio Group' : 'Form Audio Group');

const comboboxOpen = ref(false);

const form = useForm({
    title: props.selectedGroup?.name || '',
    seq: (props.subGroups.length || 0) + 1 as number | null, // Default to last position
});

// Watch for modal open/close and selectedGroup changes
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        if (props.selectedGroup) {
            // Editing existing group
            form.title = props.selectedGroup.name;
            const pos = (props.subGroups.findIndex(sg => sg.id === props.selectedGroup!.id) ?? -1) + 1;
            form.seq = pos || null;
        } else {
            // Adding new group - set to last position
            form.title = '';
            form.seq = (props.subGroups.length || 0) + 1;
        }
    }
}, { immediate: true });

const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    props.subGroups.forEach((sg, index) => {
        const position = index + 1;
        options.push({
            position,
            label: `${position} - ${sg.name}`,
        });
    });
    
    const lastPosition = props.subGroups.length + 1;
    options.push({
        position: lastPosition,
        label: `${lastPosition} - (Terakhir)`,
    });
    
    return options;
});

const selectedUrutanLabel = computed(() => {
    const option = urutanOptions.value.find(opt => opt.position === form.seq);
    return option?.label || 'Pilih urutan...';
});

const selectUrutan = (position: number) => {
    form.seq = position;
    comboboxOpen.value = false;
};

const handleSubmit = () => {
    const baseUrl = props.lang === 'CH' ? '/audio/daftar-isi' : '/audio/topik';
    
    if (isEdit.value && props.selectedGroup) {
        // Update existing group
        form.put(`/audio/sub-group/${props.selectedGroup.id}`, {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
                form.reset();
            },
        });
    } else {
        // Create new group
        form.transform(data => ({
            new_group_name: data.title,
            title: '',
            seq: data.seq,
        })).post(`${baseUrl}/category/${props.categoryId}/sub-group`, {
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
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nama Audio Group</label>
                    <Input v-model="form.title" placeholder="Masukkan nama audio group" />
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

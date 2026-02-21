<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch, computed, ref } from 'vue';
import { Icon } from '@iconify/vue';
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

interface Category {
    id: number;
    title: string;
    type: string;
    seq: number;
}

interface Props {
    open: boolean;
    type: 'add' | 'edit' | 'delete';
    category: Category | null;
    categories: Category[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const form = useForm({
    title: '',
    seq: 1,
});

// Combobox state
const comboboxOpen = ref(false);

// Watch for category changes to update form
watch(() => props.category, (newCategory) => {
    if (newCategory && props.type === 'edit') {
        form.title = newCategory.title;
        form.seq = newCategory.seq;
    } else if (props.type === 'add') {
        form.reset();
        // Get next sequence number
        const maxSeq = props.categories.length > 0 ? Math.max(...props.categories.map(c => c.seq)) : 0;
        form.seq = maxSeq + 1;
    }
}, { immediate: true });

const modalTitle = computed(() => {
    switch (props.type) {
        case 'add': return 'Tambah Kategori';
        case 'edit': return 'Edit Kategori';
        case 'delete': return 'Hapus Kategori';
        default: return '';
    }
});

// Urutan options
const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    // Helper function to truncate text
    const truncateText = (text: string, maxLength: number = 50) => {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    };
    
    props.categories.forEach((cat, index) => {
        const position = index + 1;
        options.push({
            position,
            label: `${position} - ${truncateText(cat.title)}`,
        });
    });
    
    const lastPosition = props.categories.length + 1;
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
    if (props.type === 'add') {
        form.post('/keyword/category', {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
                form.reset();
            },
        });
    } else if (props.type === 'edit' && props.category) {
        form.put(`/keyword/category/${props.category.id}`, {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (props.category) {
        form.delete(`/keyword/category/${props.category.id}`, {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ modalTitle }}</DialogTitle>
            </DialogHeader>

            <div class="py-4">
                <!-- Add/Edit Category Form -->
                <form v-if="type === 'add' || type === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Kategori</label>
                        <Input v-model="form.title" placeholder="Masukkan nama kategori" required />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                        <Popover :open="comboboxOpen" @update:open="comboboxOpen = $event">
                            <PopoverTrigger as-child>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    :aria-expanded="comboboxOpen"
                                    class="w-full justify-between overflow-hidden"
                                >
                                    <span class="truncate">{{ selectedUrutanLabel }}</span>
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
                                                class="overflow-hidden"
                                            >
                                                <Icon
                                                    icon="mdi:check"
                                                    :class="cn('mr-2 h-4 w-4', form.seq === option.position ? 'opacity-100' : 'opacity-0')"
                                                />
                                                <span class="truncate">{{ option.label }}</span>
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
                            :class="type === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'" 
                            :disabled="form.processing"
                        >
                            {{ type === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>

                <!-- Delete Category Confirmation -->
                <div v-else-if="type === 'delete'" class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus kategori <strong>{{ category?.title }}</strong>?
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="emit('update:open', false)">Batal</Button>
                        <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">
                            Hapus
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

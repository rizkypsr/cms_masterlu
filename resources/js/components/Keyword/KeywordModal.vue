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

interface Keyword {
    id: number;
    keyword_category_id: number;
    kata_kunci: string;
    category?: Category;
}

interface Props {
    open: boolean;
    type: 'add' | 'edit' | 'delete';
    keyword: Keyword | null;
    categories: Category[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const form = useForm({
    keyword_category_id: null as number | null,
    kata_kunci: '',
});

// Combobox state
const comboboxOpen = ref(false);

// Watch for keyword changes to update form
watch(() => props.keyword, (newKeyword) => {
    if (newKeyword && props.type === 'edit') {
        form.keyword_category_id = newKeyword.keyword_category_id;
        form.kata_kunci = newKeyword.kata_kunci;
    } else {
        form.reset();
    }
}, { immediate: true });

const modalTitle = computed(() => {
    switch (props.type) {
        case 'add': return 'Tambah Keyword';
        case 'edit': return 'Edit Keyword';
        case 'delete': return 'Hapus Data';
        default: return '';
    }
});

const selectedCategoryLabel = computed(() => {
    const category = props.categories.find(c => c.id === form.keyword_category_id);
    return category?.title || 'Pilih Kategori';
});

const selectCategory = (categoryId: number) => {
    form.keyword_category_id = categoryId;
    comboboxOpen.value = false;
};

const handleSubmit = () => {
    if (props.type === 'add') {
        form.post('/keyword', {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
                form.reset();
            },
        });
    } else if (props.type === 'edit' && props.keyword) {
        form.put(`/keyword/${props.keyword.id}`, {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (props.keyword) {
        form.delete(`/keyword/${props.keyword.id}`, {
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
                <!-- Add/Edit Modal -->
                <form v-if="type === 'add' || type === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Kategori</label>
                        <Popover :open="comboboxOpen" @update:open="comboboxOpen = $event">
                            <PopoverTrigger as-child>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    :aria-expanded="comboboxOpen"
                                    class="w-full justify-between overflow-hidden"
                                >
                                    <span class="truncate">{{ selectedCategoryLabel }}</span>
                                    <Icon icon="mdi:unfold-more-horizontal" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                <Command>
                                    <CommandInput placeholder="Cari kategori..." />
                                    <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                    <CommandList>
                                        <CommandGroup>
                                            <CommandItem
                                                v-for="category in categories"
                                                :key="category.id"
                                                :value="category.title"
                                                @select="selectCategory(category.id)"
                                                class="overflow-hidden"
                                            >
                                                <Icon
                                                    icon="mdi:check"
                                                    :class="cn('mr-2 h-4 w-4', form.keyword_category_id === category.id ? 'opacity-100' : 'opacity-0')"
                                                />
                                                <span class="truncate">{{ category.title }}</span>
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Kata Kunci</label>
                        <Input v-model="form.kata_kunci" placeholder="Masukkan kata kunci" required />
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

                <!-- Delete Modal -->
                <div v-else-if="type === 'delete'" class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus keyword <strong>{{ keyword?.kata_kunci }}</strong>?
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

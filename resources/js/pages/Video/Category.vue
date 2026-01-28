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

interface Category {
    id: number;
    title: string;
    parent?: Category | null;
}

const props = defineProps<{
    videoCategories: VideoCategory[];
    category: Category;
    lang: string;
    storeUrl: string;
}>();

const page = usePage();
const user = page.props.auth?.user;

// Page title: "Parent/Child" format
const pageTitle = computed(() => {
    if (props.category.parent) {
        return `${props.category.parent.title}/${props.category.title}`;
    }
    return props.category.title;
});

// Modal states
const modalOpen = ref(false);
const modalType = ref<'view' | 'add' | 'edit' | 'delete'>('view');
const modalTitle = ref('');
const selectedItem = ref<VideoCategory | VideoCategoryChild | null>(null);
const isAddingVideo = ref(false);
const selectedParent = ref<VideoCategory | null>(null);

// Combobox state
const comboboxOpen = ref(false);

// Form
const form = useForm({
    title: '',
    parent_id: null as number | null,
    seq: null as number | null,
});

// Combobox options for "Urutan"
const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    let items: (VideoCategory | VideoCategoryChild)[] = [];
    
    if (isAddingVideo.value && selectedParent.value) {
        items = selectedParent.value.children || [];
    } else {
        items = props.videoCategories;
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
    
    return options;
});

const selectedUrutanLabel = computed(() => {
    const option = urutanOptions.value.find(opt => opt.position === form.seq);
    return option?.label || 'Pilih urutan...';
});

const openModal = (type: 'view' | 'add' | 'edit' | 'delete', item?: VideoCategory | VideoCategoryChild, isNew = false, parent?: VideoCategory) => {
    modalType.value = type;
    selectedItem.value = item || null;
    isAddingVideo.value = !isNew && type === 'add';
    selectedParent.value = parent || null;

    switch (type) {
        case 'view':
            modalTitle.value = 'Lihat Detail';
            break;
        case 'add':
            modalTitle.value = isNew ? 'Tambah Kategori Video' : 'Tambah Sub Kategori';
            form.reset();
            if (isAddingVideo.value && parent) {
                form.seq = (parent.children?.length || 0) + 1;
            } else {
                form.seq = props.videoCategories.length + 1;
            }
            break;
        case 'edit':
            modalTitle.value = 'Edit';
            form.title = item?.title || '';
            // Find position
            if (isAddingVideo.value && parent) {
                const pos = (parent.children?.findIndex(c => c.id === item?.id) ?? -1) + 1;
                form.seq = pos || null;
            } else {
                const pos = props.videoCategories.findIndex(c => c.id === item?.id) + 1;
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
    if (modalType.value === 'add') {
        if (isAddingVideo.value && selectedParent.value) {
            // Add sub video category (child)
            form.transform(data => ({
                ...data,
                parent_id: selectedParent.value?.id,
            })).post(props.storeUrl, {
                onSuccess: () => {
                    modalOpen.value = false;
                    form.reset();
                },
            });
        } else {
            // Add video category (root)
            form.post(props.storeUrl, {
                onSuccess: () => {
                    modalOpen.value = false;
                    form.reset();
                },
            });
        }
    } else if (modalType.value === 'edit' && selectedItem.value) {
        form.put(`/video/video-category/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/video/video-category/${selectedItem.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const selectUrutan = (position: number) => {
    form.seq = position;
    comboboxOpen.value = false;
};
</script>

<template>
    <Head :title="pageTitle" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <h1 class="mb-6 text-xl text-gray-600">{{ pageTitle }}</h1>

            <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                    <span class="font-medium">{{ pageTitle }}</span>
                    <button
                        @click="openModal('add', undefined, true)"
                        class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                    >
                        <Icon icon="mdi:plus" class="h-4 w-4" />
                    </button>
                </div>

                <!-- Video Category List -->
                <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                    <template v-if="videoCategories.length">
                        <Card
                            v-for="vc in videoCategories"
                            :key="vc.id"
                            class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                        >
                            <!-- Video Category Header -->
                            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                <span class="font-medium text-gray-700">{{ vc.title }}</span>
                                <div class="flex items-center gap-1">
                                    <button
                                        @click="openModal('view', vc)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                    >
                                        <Icon icon="mdi:navigation-variant" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('add', undefined, false, vc)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                    >
                                        <Icon icon="mdi:plus" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('edit', vc)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                    >
                                        <Icon icon="mdi:pencil" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('delete', vc)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                    >
                                        <Icon icon="mdi:delete" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Children (sub video categories) -->
                            <div v-if="vc.children?.length">
                                <div
                                    v-for="child in vc.children"
                                    :key="child.id"
                                    class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0 cursor-pointer hover:bg-gray-50"
                                    @click="router.visit(`/video/video-category/${child.id}`)"
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
                                            @click="isAddingVideo = true; openModal('edit', child, false, vc)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="isAddingVideo = true; openModal('delete', child, false, vc)"
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
                        Belum ada kategori video
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

                    <!-- Add/Edit Modal -->
                    <form v-else-if="modalType === 'add' || modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nama</label>
                            <Input v-model="form.title" placeholder="Masukkan nama" />
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
                            Apakah Anda yakin ingin menghapus <strong>{{ selectedItem?.title }}</strong>?
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

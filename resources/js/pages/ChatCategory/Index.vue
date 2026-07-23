<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
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

interface ChatCategory {
    id: number;
    name: string;
    seq: number;
    is_active: boolean;
    parent_id: number | null;
    description: string | null;
    items_count?: number;
    children?: ChatCategory[];
}

const props = defineProps<{
    categories: ChatCategory[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedItem = ref<ChatCategory | null>(null);
// Parent the new/edited row sits under (null = top-level).
const parentContext = ref<ChatCategory | null>(null);
const comboboxOpen = ref(false);

const form = useForm({
    name: '',
    parent_id: null as number | null,
    seq: null as number | null,
    is_active: true as boolean,
    description: '' as string,
});

// A row is a leaf (selectable) only when it has no active children.
const isLeaf = (cat: ChatCategory) =>
    !cat.children?.some((c) => c.is_active);

const openScope = (cat: ChatCategory) => {
    router.get(`/chatbot/kategori/${cat.id}/scope`);
};

// Siblings the row is ordered against (within its parent level).
const siblings = computed<ChatCategory[]>(() => {
    if (parentContext.value) {
        return parentContext.value.children || [];
    }
    return props.categories;
});

const urutanOptions = computed(() => {
    const truncate = (text: string, max = 50) =>
        text.length <= max ? text : text.substring(0, max) + '...';

    const options = siblings.value.map((item, index) => ({
        position: index + 1,
        label: `${index + 1} - ${truncate(item.name)}`,
    }));

    const last = siblings.value.length + 1;
    options.push({ position: last, label: `${last} - (Terakhir)` });
    return options;
});

const selectedUrutanLabel = computed(() => {
    const option = urutanOptions.value.find((opt) => opt.position === form.seq);
    return option?.label || 'Pilih urutan...';
});

const selectUrutan = (position: number) => {
    form.seq = position;
    comboboxOpen.value = false;
};

const openAdd = (parent?: ChatCategory) => {
    modalType.value = 'add';
    modalTitle.value = parent ? `Tambah Sub-Kategori (${parent.name})` : 'Tambah Kategori';
    parentContext.value = parent || null;
    selectedItem.value = null;
    form.reset();
    form.parent_id = parent?.id ?? null;
    form.is_active = true;
    form.seq = (parent ? parent.children?.length || 0 : props.categories.length) + 1;
    modalOpen.value = true;
};

const openEdit = (item: ChatCategory, parent?: ChatCategory) => {
    modalType.value = 'edit';
    modalTitle.value = 'Edit Kategori';
    parentContext.value = parent || null;
    selectedItem.value = item;
    form.reset();
    form.name = item.name;
    form.parent_id = item.parent_id;
    form.is_active = item.is_active;
    form.description = item.description ?? '';
    const list = parent ? parent.children || [] : props.categories;
    form.seq = list.findIndex((c) => c.id === item.id) + 1 || null;
    modalOpen.value = true;
};

const openDelete = (item: ChatCategory) => {
    modalType.value = 'delete';
    modalTitle.value = 'Hapus';
    selectedItem.value = item;
    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        form.post('/chatbot/kategori', {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (selectedItem.value) {
        form.put(`/chatbot/kategori/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/chatbot/kategori/${selectedItem.value.id}`, {
            onSuccess: () => (modalOpen.value = false),
        });
    }
};
</script>

<template>
    <Head title="Kategori Chatbot" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <h1 class="mb-6 text-xl text-gray-600">Kategori Chatbot</h1>

            <div class="flex flex-1 gap-6 overflow-hidden">
                <div class="mx-auto flex w-full max-w-3xl flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">Kategori</span>
                        <button
                            @click="openAdd()"
                            class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                            title="Tambah Kategori"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Category List -->
                    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                        <template v-if="categories.length">
                            <Card
                                v-for="category in categories"
                                :key="category.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                            >
                                <!-- Top-level Header -->
                                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-700">{{ category.name }}</span>
                                        <span
                                            v-if="!category.is_active"
                                            class="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-500"
                                        >
                                            Nonaktif
                                        </span>
                                        <span
                                            v-if="!isLeaf(category)"
                                            class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-700"
                                        >
                                            Grup
                                        </span>
                                        <span
                                            v-else-if="category.items_count"
                                            class="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-700"
                                        >
                                            {{ category.items_count }} konten
                                        </span>
                                        <span
                                            v-else
                                            class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500"
                                        >
                                            Belum ada konten
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button
                                            v-if="isLeaf(category)"
                                            @click="openScope(category)"
                                            class="flex h-7 items-center gap-1 rounded bg-[#337ab7] px-2 text-xs text-white hover:bg-[#286090]"
                                            title="Kelola Konten"
                                        >
                                            <Icon icon="mdi:folder-cog" class="h-4 w-4" />
                                            Kelola Konten
                                        </button>
                                        <button
                                            @click="openAdd(category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Tambah Sub-Kategori"
                                        >
                                            <Icon icon="mdi:plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openEdit(category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            title="Edit"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openDelete(category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            title="Hapus"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Children (leaves) -->
                                <div v-if="category.children?.length">
                                    <div
                                        v-for="child in category.children"
                                        :key="child.id"
                                        class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm text-gray-600">{{ child.name }}</span>
                                            <Popover>
                                                <PopoverTrigger as-child>
                                                    <button
                                                        type="button"
                                                        class="flex h-5 w-5 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-[#337ab7]"
                                                        title="Deskripsi"
                                                    >
                                                        <Icon icon="mdi:information-outline" class="h-4 w-4" />
                                                    </button>
                                                </PopoverTrigger>
                                                <PopoverContent class="w-72 text-sm text-gray-600">
                                                    <div
                                                        v-if="child.description"
                                                        class="prose prose-sm max-w-none [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                                                        v-html="child.description"
                                                    />
                                                    <span v-else>Belum ada deskripsi.</span>
                                                </PopoverContent>
                                            </Popover>
                                            <span
                                                v-if="!child.is_active"
                                                class="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-500"
                                            >
                                                Nonaktif
                                            </span>
                                            <span
                                                v-if="child.items_count"
                                                class="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-700"
                                            >
                                                {{ child.items_count }} konten
                                            </span>
                                            <span
                                                v-else
                                                class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500"
                                            >
                                                Belum ada konten
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                @click="openScope(child)"
                                                class="flex h-7 items-center gap-1 rounded bg-[#337ab7] px-2 text-xs text-white hover:bg-[#286090]"
                                                title="Kelola Konten"
                                            >
                                                <Icon icon="mdi:folder-cog" class="h-4 w-4" />
                                                Kelola
                                            </button>
                                            <button
                                                @click="openEdit(child, category)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                title="Edit"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openDelete(child)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                title="Hapus"
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
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] max-w-[95vw] translate-y-0 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="truncate">{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="w-full overflow-hidden py-4">
                    <!-- Add/Edit -->
                    <form v-if="modalType === 'add' || modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Nama <span class="text-red-500">*</span>
                            </label>
                            <Input v-model="form.name" placeholder="Masukkan nama kategori" />
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

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Deskripsi</label>
                            <TiptapEditor v-model="form.description" height="150px" />
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                v-model="form.is_active"
                                class="h-4 w-4 rounded border-gray-300"
                            />
                            Aktif (tampil ke pengguna)
                        </label>

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

                    <!-- Delete -->
                    <div v-else-if="modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <strong>{{ selectedItem?.name }}</strong>?
                        </p>
                        <p class="text-xs text-gray-500">
                            Sub-kategori akan menjadi kategori tingkat atas, bukan ikut terhapus.
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">Hapus</Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

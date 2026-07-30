<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';
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
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { cn } from '@/lib/utils';
import CategoryNode from './CategoryNode.vue';
import type { ChatCategoryNode, NodeActions, ScopeItem } from './types';

const props = defineProps<{
    categories: ChatCategoryNode[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedItem = ref<ChatCategoryNode | null>(null);
const comboboxOpen = ref(false);
const parentPickerOpen = ref(false);

const form = useForm({
    name: '',
    parent_id: null as number | null,
    seq: null as number | null,
    is_active: true as boolean,
    description: '' as string,
});

// How many levels the CMS lets admins build. The table and API accept deeper
// nesting, and rows already deeper than this still render — this only stops new
// levels being created here.
const MAX_LEVELS = 3;
const MAX_DEPTH = MAX_LEVELS - 1;

provide('maxLevels', MAX_LEVELS);

// ---- Tree helpers (render stays recursive so existing deep rows stay visible) ----

interface FlatNode {
    node: ChatCategoryNode;
    depth: number;
}

const flatten = (nodes: ChatCategoryNode[], depth = 0): FlatNode[] =>
    nodes.flatMap((node) => [{ node, depth }, ...flatten(node.children, depth + 1)]);

const flatNodes = computed(() => flatten(props.categories));

const findNode = (id: number | null): ChatCategoryNode | null =>
    id === null ? null : (flatNodes.value.find((f) => f.node.id === id)?.node ?? null);

/** Ids of a node and everything beneath it — invalid parent targets. */
const subtreeIds = (node: ChatCategoryNode): number[] => [
    node.id,
    ...node.children.flatMap(subtreeIds),
];

/** Levels below a node: 0 when it has no children. */
const subtreeHeight = (node: ChatCategoryNode): number =>
    node.children.length === 0 ? 0 : 1 + Math.max(...node.children.map(subtreeHeight));

// ---- Parent picker ----

const parentOptions = computed(() => {
    const editing = modalType.value === 'edit' ? selectedItem.value : null;

    // Editing: moving a node under itself or its own descendant would detach
    // the branch from the root, so those targets are excluded.
    const blocked = editing ? subtreeIds(editing) : [];

    // A moved branch keeps its own depth, so a parent only works if the whole
    // subtree still fits: parentDepth + 1 + height <= MAX_DEPTH.
    const height = editing ? subtreeHeight(editing) : 0;
    const maxParentDepth = MAX_DEPTH - 1 - height;

    // The current position stays selectable no matter what, otherwise a row
    // already deeper than the limit could never be edited at all.
    const currentParentId = editing ? editing.parent_id : undefined;

    const options: { id: number | null; label: string; depth: number }[] = [];

    if (height <= MAX_DEPTH || currentParentId === null) {
        options.push({ id: null, label: '— Tanpa parent (level atas)', depth: 0 });
    }

    for (const { node, depth } of flatNodes.value) {
        if (blocked.includes(node.id)) continue;
        if (depth > maxParentDepth && node.id !== currentParentId) continue;

        options.push({
            id: node.id,
            label: `${'\u00a0\u00a0'.repeat(depth)}${depth > 0 ? '└ ' : ''}${node.name}`,
            depth,
        });
    }

    return options;
});

const selectedParentLabel = computed(() => {
    const parent = findNode(form.parent_id);
    if (!parent) return '— Tanpa parent (level atas)';

    // Show the full path so a name repeated at different depths stays readable.
    const path: string[] = [];
    let cursor: ChatCategoryNode | null = parent;
    while (cursor) {
        path.unshift(cursor.name);
        cursor = findNode(cursor.parent_id);
    }
    return path.join(' › ');
});

const selectParent = (id: number | null) => {
    form.parent_id = id;
    parentPickerOpen.value = false;
    // Sibling set changed, so the previous position no longer means anything.
    form.seq = siblings.value.length + (modalType.value === 'add' ? 1 : 0) || 1;
};

// ---- Ordering ----

// Siblings the row is ranked against: the chosen parent's children, or roots.
const siblings = computed<ChatCategoryNode[]>(() => {
    const parent = findNode(form.parent_id);
    return parent ? parent.children : props.categories;
});

const urutanOptions = computed(() => {
    const truncate = (text: string, max = 50) =>
        text.length <= max ? text : text.substring(0, max) + '...';

    const list = siblings.value.filter((s) => s.id !== selectedItem.value?.id);

    const options = list.map((item, index) => ({
        position: index + 1,
        label: `${index + 1} - ${truncate(item.name)}`,
    }));

    const last = list.length + 1;
    options.push({ position: last, label: `${last} - (Terakhir)` });
    return options;
});

const selectedUrutanLabel = computed(
    () => urutanOptions.value.find((opt) => opt.position === form.seq)?.label ?? 'Pilih urutan...',
);

const selectUrutan = (position: number) => {
    form.seq = position;
    comboboxOpen.value = false;
};

// ---- Modal actions ----

const openAdd = (parent?: ChatCategoryNode) => {
    modalType.value = 'add';
    modalTitle.value = parent ? `Tambah Sub-Kategori (${parent.name})` : 'Tambah Kategori';
    selectedItem.value = null;
    form.reset();
    form.clearErrors();
    form.parent_id = parent?.id ?? null;
    form.is_active = true;
    form.seq = (parent ? parent.children.length : props.categories.length) + 1;
    modalOpen.value = true;
};

const openEdit = (item: ChatCategoryNode) => {
    modalType.value = 'edit';
    modalTitle.value = `Edit Kategori (${item.name})`;
    selectedItem.value = item;
    form.reset();
    form.clearErrors();
    form.name = item.name;
    form.parent_id = item.parent_id;
    form.is_active = item.is_active;
    form.description = item.description ?? '';

    const parent = findNode(item.parent_id);
    const list = parent ? parent.children : props.categories;
    form.seq = list.findIndex((c) => c.id === item.id) + 1 || null;
    modalOpen.value = true;
};

const openDelete = (item: ChatCategoryNode) => {
    modalType.value = 'delete';
    modalTitle.value = 'Hapus';
    selectedItem.value = item;
    modalOpen.value = true;
};

const openScope = (cat: ChatCategoryNode) => {
    router.get(`/chatbot/kategori/${cat.id}/scope`);
};

// ---- Stale content on a group category ----
const staleOpen = ref(false);
const staleDeleting = ref(false);
const staleNodeId = ref<number | null>(null);

// Read off the page props, so the list refreshes with the Inertia response
// after a delete instead of going stale in local state.
const staleNode = computed(() => findNode(staleNodeId.value));
const staleItems = computed<ScopeItem[]>(() => staleNode.value?.stale_items ?? []);

const openStaleItems = (node: ChatCategoryNode) => {
    staleNodeId.value = node.id;
    staleOpen.value = true;
};

const deleteStaleItems = () => {
    if (staleNodeId.value === null) return;
    staleDeleting.value = true;
    router.delete(`/chatbot/kategori/${staleNodeId.value}/stale-items`, {
        preserveScroll: true,
        onFinish: () => (staleDeleting.value = false),
        onSuccess: () => (staleOpen.value = false),
    });
};

const actions: NodeActions = {
    add: openAdd,
    edit: openEdit,
    remove: openDelete,
    scope: openScope,
    staleItems: openStaleItems,
};

provide('categoryActions', actions);

const handleSubmit = () => {
    const done = {
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    };

    if (modalType.value === 'add') {
        form.post('/chatbot/kategori', done);
    } else if (selectedItem.value) {
        form.put(`/chatbot/kategori/${selectedItem.value.id}`, done);
    }
};

const handleDelete = () => {
    if (!selectedItem.value) return;
    router.delete(`/chatbot/kategori/${selectedItem.value.id}`, {
        onSuccess: () => (modalOpen.value = false),
    });
};

// A leaf about to gain its first active child stops serving its own content,
// so that content is handed down to the new child instead of stranding.
const parentHandsDownItems = computed(() => {
    if (modalType.value !== 'add' || !form.is_active) return null;
    const parent = findNode(form.parent_id);
    if (!parent || !parent.items_count) return null;
    return parent.children.some((c) => c.is_active) ? null : parent;
});
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
                            class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                            title="Tambah Kategori"
                            @click="openAdd()"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                        <template v-if="categories.length">
                            <Card
                                v-for="category in categories"
                                :key="category.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                            >
                                <CategoryNode :node="category" :depth="0" />
                            </Card>
                        </template>
                        <div v-else class="py-8 text-center text-gray-500">Belum ada kategori</div>
                    </div>

                    <p class="mt-2 text-xs text-gray-500">
                        Maksimal {{ MAX_LEVELS }} level. Hanya kategori terdalam di tiap cabang yang menyimpan konten —
                        kategori yang punya sub-kategori otomatis jadi pengelompokan saja.
                    </p>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] max-h-[85vh] max-w-[95vw] translate-y-0 overflow-x-hidden overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="pr-6 break-words">{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="w-full min-w-0 py-4">
                    <!-- Add/Edit -->
                    <form
                        v-if="modalType === 'add' || modalType === 'edit'"
                        class="space-y-4"
                        @submit.prevent="handleSubmit"
                    >
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Nama <span class="text-red-500">*</span>
                            </label>
                            <Input v-model="form.name" placeholder="Masukkan nama kategori" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Parent</label>
                            <Popover :open="parentPickerOpen" @update:open="parentPickerOpen = $event">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="parentPickerOpen"
                                        class="w-full justify-between overflow-hidden"
                                    >
                                        <span class="min-w-0 flex-1 truncate text-left">{{ selectedParentLabel }}</span>
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
                                                    v-for="option in parentOptions"
                                                    :key="option.id ?? 'root'"
                                                    :value="option.label"
                                                    class="overflow-hidden"
                                                    @select="selectParent(option.id)"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4 shrink-0',
                                                                form.parent_id === option.id ? 'opacity-100' : 'opacity-0',
                                                            )
                                                        "
                                                    />
                                                    <span class="truncate whitespace-pre">{{ option.label }}</span>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                            <p class="mt-1 text-xs text-gray-500">
                                Hanya kategori yang masih menyisakan ruang dalam batas {{ MAX_LEVELS }} level yang
                                muncul di daftar.
                                <span v-if="modalType === 'edit'">
                                    Kategori ini dan sub-kategorinya juga disembunyikan, karena akan membuat lingkaran.
                                </span>
                            </p>
                            <p v-if="form.errors.parent_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.parent_id }}
                            </p>
                        </div>

                        <div
                            v-if="parentHandsDownItems"
                            class="flex items-start gap-2 rounded border border-blue-200 bg-blue-50 p-3 text-xs break-words text-blue-700"
                        >
                            <Icon icon="mdi:transfer-down" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span class="min-w-0">
                                <strong>{{ parentHandsDownItems.items_count }} konten</strong> milik
                                <strong>{{ parentHandsDownItems.name }}</strong> akan dipindahkan ke sub-kategori baru
                                ini. Setelah punya sub-kategori, induknya jadi pengelompokan saja dan tidak lagi
                                menyajikan konten sendiri.
                            </span>
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
                                        <span class="min-w-0 flex-1 truncate text-left">{{ selectedUrutanLabel }}</span>
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
                                                    class="overflow-hidden"
                                                    @select="selectUrutan(option.position)"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                form.seq === option.position ? 'opacity-100' : 'opacity-0',
                                                            )
                                                        "
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
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
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
                            Sub-kategori langsungnya akan menjadi kategori tingkat atas, bukan ikut terhapus.
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">Hapus</Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Stale content on a group category -->
        <Dialog :open="staleOpen" @update:open="staleOpen = $event">
            <DialogContent class="top-[10%] max-h-[85vh] max-w-[95vw] translate-y-0 overflow-x-hidden overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="pr-6 break-words">Konten Tidak Terpakai — {{ staleNode?.name }}</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <p class="rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">
                        <Icon icon="mdi:information-outline" class="mr-1 inline h-3.5 w-3.5" />
                        <strong>{{ staleNode?.name }}</strong> punya sub-kategori, jadi dia hanya pengelompokan — konten
                        di bawah ini <strong>tidak disajikan ke pengguna</strong> dan tidak akan pernah dicari chatbot.
                        Kalau masih dibutuhkan, pasang ulang lewat <em>Kelola Konten</em> di salah satu sub-kategorinya
                        dulu sebelum dihapus dari sini.
                    </p>

                    <ul v-if="staleItems.length" class="max-h-64 space-y-2 overflow-y-auto">
                        <li
                            v-for="item in staleItems"
                            :key="`${item.domain}:${item.level}:${item.node_id}`"
                            class="rounded border border-gray-100 bg-gray-50 p-2 text-xs"
                        >
                            <div class="flex items-center gap-1">
                                <span class="rounded bg-[#337ab7] px-1.5 py-0.5 text-[10px] text-white">
                                    {{ item.domain }}
                                </span>
                                <span
                                    v-if="item.missing"
                                    class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] text-red-600"
                                    title="Node sudah tidak ada di database"
                                >
                                    hilang
                                </span>
                            </div>
                            <p class="mt-1 font-medium text-gray-700">{{ item.label }}</p>
                            <p v-if="item.path.length > 1" class="truncate text-[11px] text-gray-400">
                                {{ item.path.slice(0, -1).join(' › ') }}
                            </p>
                        </li>
                    </ul>

                    <p v-else class="py-4 text-center text-sm text-gray-500">Tidak ada konten nyangkut.</p>

                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="staleOpen = false">Batal</Button>
                        <Button
                            v-if="staleItems.length"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                            :disabled="staleDeleting"
                            @click="deleteStaleItems"
                        >
                            <Icon icon="mdi:broom" class="mr-1 h-4 w-4" />
                            Hapus {{ staleItems.length }} konten
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

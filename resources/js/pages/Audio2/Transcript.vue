<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface Segment {
    id: number;
    audio2_id: number;
    caller: number;
    code: string | null;
    timestamp: number;
    title: string | null;
    content: string | null;
    seq: number;
}

interface Episode {
    id: number;
    title: string;
    year: number | null;
    audio2_category_id: number;
    category: { id: number; title: string } | null;
}

interface PreviewSegment {
    caller: number;
    code: string;
    time: string;
    title: string;
    excerpt: string;
}

const props = defineProps<{
    episode: Episode;
    segments: Segment[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const callerLabel = (caller: number) => (caller === 0 ? 'Pembuka' : `Penelepon ${caller}`);

const formatTime = (seconds: number) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const mm = String(m).padStart(2, '0');
    const ss = String(s).padStart(2, '0');
    return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
};

const stripHtml = (html: string | null) => (html ?? '').replace(/<br\s*\/?>/gi, ' ').replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim();

// Working copy in display order; reordering edits this, then saves.
const rows = ref<Segment[]>([]);
watch(
    () => props.segments,
    (value) => {
        rows.value = value.map((s) => ({ ...s }));
    },
    { immediate: true },
);

const callerCount = computed(() => new Set(rows.value.map((r) => r.caller).filter((c) => c > 0)).size);

/** Consecutive runs of the same caller, with each row's index in the flat list. */
const groups = computed(() => {
    const result: { caller: number; key: string; items: { row: Segment; index: number }[] }[] = [];
    rows.value.forEach((row, index) => {
        const last = result[result.length - 1];
        if (last && last.caller === row.caller) {
            last.items.push({ row, index });
        } else {
            result.push({ caller: row.caller, key: `${row.caller}-${index}`, items: [{ row, index }] });
        }
    });
    return result;
});

const saveOrder = () => {
    router.post(
        `/audio2/episode/${props.episode.id}/segment/reorder`,
        { segments: rows.value.map((r) => ({ id: r.id, caller: r.caller })) },
        { preserveScroll: true, preserveState: true },
    );
};

/**
 * Move up: swap with the row above, or join the previous caller group when at the top of a group.
 * The very first row steps back one caller number (e.g. Penelepon 1 → Pembuka); the last row moving down starts the next caller.
 */
const moveUp = (index: number) => {
    const list = rows.value;
    if (index === 0) {
        if (list[0].caller === 0) return;
        list[0].caller -= 1;
    } else if (list[index - 1].caller === list[index].caller) {
        [list[index - 1], list[index]] = [list[index], list[index - 1]];
    } else {
        list[index].caller = list[index - 1].caller;
    }
    saveOrder();
};

const moveDown = (index: number) => {
    const list = rows.value;
    if (index === list.length - 1) {
        list[index].caller += 1;
    } else if (list[index + 1].caller === list[index].caller) {
        [list[index + 1], list[index]] = [list[index], list[index + 1]];
    } else {
        list[index].caller = list[index + 1].caller;
    }
    saveOrder();
};

// Drag and drop (native HTML5): dropping on a row puts the dragged row above it, in that row's caller group.
const dragIndex = ref<number | null>(null);
const dropIndex = ref<number | null>(null);

const onDragStart = (index: number, event: DragEvent) => {
    dragIndex.value = index;
    event.dataTransfer?.setData('text/plain', String(index));
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
};

const onDrop = (targetIndex: number) => {
    const from = dragIndex.value;
    dragIndex.value = null;
    dropIndex.value = null;
    if (from === null || from === targetIndex) return;

    const list = rows.value;
    const targetCaller = list[targetIndex].caller;
    const [moved] = list.splice(from, 1);
    moved.caller = targetCaller;
    list.splice(from < targetIndex ? targetIndex - 1 : targetIndex, 0, moved);
    saveOrder();
};

const onDropEnd = (groupLastIndex: number) => {
    const from = dragIndex.value;
    dragIndex.value = null;
    dropIndex.value = null;
    if (from === null) return;

    const list = rows.value;
    const targetCaller = list[groupLastIndex].caller;
    const [moved] = list.splice(from, 1);
    moved.caller = targetCaller;
    list.splice(from <= groupLastIndex ? groupLastIndex : groupLastIndex + 1, 0, moved);
    saveOrder();
};

// Selection
const selectedIds = ref<number[]>([]);
const toggleSelect = (id: number) => {
    const i = selectedIds.value.indexOf(id);
    if (i > -1) selectedIds.value.splice(i, 1);
    else selectedIds.value.push(id);
};
const allSelected = computed(() => rows.value.length > 0 && selectedIds.value.length === rows.value.length);
const toggleSelectAll = () => {
    selectedIds.value = allSelected.value ? [] : rows.value.map((r) => r.id);
};

// Modals
type ModalType = 'full' | 'add' | 'edit' | 'delete' | 'bulk-delete' | 'delete-all';
const modalOpen = ref(false);
const modalType = ref<ModalType>('full');
const selected = ref<Segment | null>(null);

const modalTitle = computed(
    () =>
        ({
            full: 'Input Semua (Teks Full)',
            add: 'Tambah Satuan',
            edit: 'Edit Segmen',
            delete: 'Hapus Segmen',
            'bulk-delete': 'Hapus Terpilih',
            'delete-all': 'Hapus Semua',
        })[modalType.value],
);

// Full text input with preview
const fullForm = useForm({ text: '' });
const preview = ref<PreviewSegment[] | null>(null);
const previewLoading = ref(false);
const previewError = ref('');

watch(
    () => fullForm.text,
    () => {
        preview.value = null;
    },
);

const runPreview = async () => {
    previewLoading.value = true;
    previewError.value = '';
    try {
        const { data } = await axios.post('/audio2/transcript/preview', {
            text: fullForm.text,
            start_caller: rows.value.at(-1)?.caller ?? 0,
        });
        preview.value = data.segments;
    } catch {
        previewError.value = 'Pratinjau gagal. Pastikan teks tidak kosong.';
    } finally {
        previewLoading.value = false;
    }
};

const saveFull = () => {
    fullForm.post(`/audio2/episode/${props.episode.id}/transcript`, {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            fullForm.reset();
            preview.value = null;
        },
    });
};

// Single segment form
const segmentForm = useForm({
    caller: 0,
    code: '',
    time: '',
    title: '',
    content: '',
});

const lastCaller = computed(() => (rows.value.length ? rows.value[rows.value.length - 1].caller : 0));
const callerOptions = computed(() => {
    const max = Math.max(lastCaller.value + 1, ...rows.value.map((r) => r.caller + 1), 3);
    return Array.from({ length: max + 1 }, (_, i) => i);
});

const openModal = (type: ModalType, segment?: Segment) => {
    modalType.value = type;
    selected.value = segment ?? null;
    segmentForm.clearErrors();
    fullForm.clearErrors();

    if (type === 'add') {
        segmentForm.reset();
        segmentForm.caller = lastCaller.value;
        segmentForm.code = rows.value.length ? (rows.value[rows.value.length - 1].code ?? props.episode.title) : props.episode.title;
    } else if (type === 'edit' && segment) {
        segmentForm.caller = segment.caller;
        segmentForm.code = segment.code ?? '';
        segmentForm.time = formatTime(segment.timestamp);
        segmentForm.title = segment.title ?? '';
        segmentForm.content = segment.content ?? '';
    } else if (type === 'full') {
        preview.value = null;
        previewError.value = '';
    }
    modalOpen.value = true;
};

const saveSegment = () => {
    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };
    if (modalType.value === 'add') {
        segmentForm.post(`/audio2/episode/${props.episode.id}/segment`, options);
    } else if (selected.value) {
        segmentForm.put(`/audio2/segment/${selected.value.id}`, options);
    }
};

const confirmDelete = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            selectedIds.value = [];
        },
    };
    if (modalType.value === 'delete' && selected.value) {
        router.delete(`/audio2/segment/${selected.value.id}`, options);
    } else if (modalType.value === 'bulk-delete') {
        router.post('/audio2/segment/bulk-delete', { segment_ids: selectedIds.value }, options);
    } else if (modalType.value === 'delete-all') {
        router.delete(`/audio2/episode/${props.episode.id}/segment`, options);
    }
};

const backHref = computed(() => `/audio2?category=${props.episode.audio2_category_id}&year=${props.episode.year ?? 'none'}`);
</script>

<template>
    <Head :title="`Transkrip - ${episode.title}`" />

    <DashboardLayout :user="user">
        <div class="bg-[#d3dce6] p-4 lg:h-[calc(100vh-48px)] lg:overflow-hidden lg:p-6">
            <div class="flex min-h-[80vh] flex-col overflow-hidden rounded bg-white shadow lg:h-full lg:min-h-0">
                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <Link :href="backHref" class="flex h-7 w-7 items-center justify-center rounded bg-gray-500 text-white hover:bg-gray-600" title="Kembali">
                            <Icon icon="mdi:arrow-left" class="h-4 w-4" />
                        </Link>
                        <span class="text-sm text-gray-600">{{ episode.category?.title }} {{ episode.year ?? '' }}</span>
                        <span class="text-sm font-medium text-red-500">{{ episode.title }}</span>
                        <span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                            {{ rows.length }} segmen · {{ callerCount }} penelepon
                        </span>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <Button @click="openModal('full')" class="bg-[#337ab7] hover:bg-[#286090]" size="sm" title="Input Semua (Teks Full)">
                            <Icon icon="mdi:text-box-plus-outline" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Input Semua (Teks Full)</span>
                        </Button>
                        <Button @click="openModal('add')" class="bg-[#5cb85c] hover:bg-[#4cae4c]" size="sm" title="Tambah Satuan">
                            <Icon icon="mdi:plus" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Tambah Satuan</span>
                        </Button>
                        <Button v-if="selectedIds.length > 0" @click="openModal('bulk-delete')" class="bg-[#d9534f] hover:bg-[#d43f3a]" size="sm" :title="`Hapus Terpilih (${selectedIds.length})`">
                            <Icon icon="mdi:delete" class="h-4 w-4 lg:mr-1" />
                            <span class="lg:hidden">{{ selectedIds.length }}</span>
                            <span class="hidden lg:inline">Hapus Terpilih ({{ selectedIds.length }})</span>
                        </Button>
                        <Button v-if="rows.length > 0" @click="openModal('delete-all')" class="bg-[#d9534f] hover:bg-[#d43f3a]" size="sm" title="Hapus Semua">
                            <Icon icon="mdi:delete-sweep" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Hapus Semua</span>
                        </Button>
                    </div>
                </div>

                <div v-if="rows.length > 0" class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500">
                    <span class="sm:hidden">Ubah urutan pakai tombol panah; di ujung kelompok, panah memindahkan segmen ke penelepon sebelah.</span>
                    <span class="hidden sm:inline">
                        Ubah urutan: seret ikon <Icon icon="mdi:drag" class="inline h-4 w-4 align-text-bottom" /> ke baris lain (bisa pindah ke penelepon lain), atau pakai tombol panah.
                    </span>
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-auto">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10 bg-gray-50">
                            <tr class="border-b border-gray-200 text-left text-sm font-medium text-gray-600">
                                <th class="hidden w-10 px-2 py-2 sm:table-cell"></th>
                                <th class="w-10 px-2 py-2">
                                    <input v-if="rows.length > 0" type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="h-4 w-4 rounded border-gray-300" title="Pilih semua" />
                                </th>
                                <th class="w-16 px-2 py-2 sm:w-28"></th>
                                <th class="hidden w-44 px-3 py-2 md:table-cell">Kode</th>
                                <th class="hidden w-20 px-3 py-2 sm:table-cell">Waktu</th>
                                <th class="px-3 py-2">Judul</th>
                                <th class="hidden px-3 py-2 lg:table-cell">Isi</th>
                            </tr>
                        </thead>
                        <tbody v-for="group in groups" :key="group.key">
                            <tr
                                class="border-b border-gray-200 bg-gray-100"
                                @dragover.prevent="dropIndex = group.items[0].index"
                                @drop.prevent="onDrop(group.items[0].index)"
                            >
                                <td colspan="7" class="px-3 py-1.5 text-sm font-semibold text-gray-700">
                                    {{ callerLabel(group.caller) }}
                                    <span class="font-normal text-gray-500">· {{ group.items.length }} segmen</span>
                                </td>
                            </tr>
                            <tr
                                v-for="{ row, index } in group.items"
                                :key="row.id"
                                class="border-b border-gray-100 hover:bg-gray-50"
                                :class="[
                                    selectedIds.includes(row.id) ? 'bg-blue-50' : '',
                                    dropIndex === index && dragIndex !== null && dragIndex !== index ? 'border-t-2 border-t-[#337ab7]' : '',
                                    dragIndex === index ? 'opacity-40' : '',
                                ]"
                                @dragover.prevent="dropIndex = index"
                                @drop.prevent="onDrop(index)"
                            >
                                <td class="hidden px-2 py-2 sm:table-cell">
                                    <span
                                        draggable="true"
                                        @dragstart="onDragStart(index, $event)"
                                        @dragend="dragIndex = null; dropIndex = null"
                                        class="flex h-6 w-6 cursor-grab items-center justify-center text-gray-500 hover:text-gray-800"
                                        title="Seret untuk ubah urutan"
                                    >
                                        <Icon icon="mdi:drag" class="h-5 w-5" />
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    <input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleSelect(row.id)" class="h-4 w-4 rounded border-gray-300" />
                                </td>
                                <td class="px-2 py-2">
                                    <div class="grid grid-cols-2 gap-1 sm:flex sm:items-center">
                                        <button @click="moveUp(index)" :disabled="index === 0 && row.caller === 0" title="Naik" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 disabled:opacity-40">
                                            <Icon icon="mdi:arrow-up" class="h-3 w-3" />
                                        </button>
                                        <button @click="moveDown(index)" title="Turun" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 disabled:opacity-40">
                                            <Icon icon="mdi:arrow-down" class="h-3 w-3" />
                                        </button>
                                        <button @click="openModal('edit', row)" title="Edit" class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]">
                                            <Icon icon="mdi:pencil" class="h-3 w-3" />
                                        </button>
                                        <button @click="openModal('delete', row)" title="Hapus" class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]">
                                            <Icon icon="mdi:delete" class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-2 text-sm text-gray-600 md:table-cell">{{ row.code }}</td>
                                <td class="hidden px-3 py-2 text-sm tabular-nums text-gray-600 sm:table-cell">{{ formatTime(row.timestamp) }}</td>
                                <td class="px-2 py-2 text-sm text-gray-800 sm:px-3">
                                    {{ row.title }}
                                    <div class="text-xs text-gray-500 md:hidden">
                                        <span class="tabular-nums sm:hidden">{{ formatTime(row.timestamp) }} · </span>{{ row.code }}
                                    </div>
                                </td>
                                <td class="hidden max-w-md truncate px-3 py-2 text-sm text-gray-500 lg:table-cell">{{ stripHtml(row.content) || '-' }}</td>
                            </tr>
                            <tr
                                v-if="dragIndex !== null"
                                class="h-2"
                                @dragover.prevent="dropIndex = null"
                                @drop.prevent="onDropEnd(group.items[group.items.length - 1].index)"
                            >
                                <td colspan="7"></td>
                            </tr>
                        </tbody>
                        <tbody v-if="rows.length === 0">
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                    Transkrip masih kosong. Klik <strong>Input Semua (Teks Full)</strong> untuk menempel teks lengkap.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-4 py-2 text-sm text-gray-600">Total {{ rows.length }} segmen</div>
            </div>
        </div>

        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent
                class="top-[5%] flex max-h-[90vh] translate-y-0 flex-col p-0"
                :class="modalType === 'full' ? 'sm:max-w-6xl' : modalType === 'add' || modalType === 'edit' ? 'sm:max-w-2xl' : 'sm:max-w-md'"
            >
                <DialogHeader class="px-6 pt-6 pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-3 pr-8">
                        <DialogTitle>{{ modalTitle }}</DialogTitle>
                        <!-- Full input: actions sit up top so they stay next to the text, not below a long paste -->
                        <div v-if="modalType === 'full'" class="flex items-center gap-2">
                            <Button type="button" variant="outline" size="sm" @click="modalOpen = false">Batal</Button>
                            <Button
                                type="button"
                                size="sm"
                                @click="saveFull"
                                :disabled="!preview || preview.length === 0 || fullForm.processing"
                                class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                            >
                                Simpan {{ preview?.length ?? 0 }} Segmen
                            </Button>
                        </div>
                    </div>
                </DialogHeader>

                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <!-- Full text -->
                    <div v-if="modalType === 'full'" class="grid gap-4 lg:grid-cols-5">
                        <div class="flex flex-col gap-2 lg:col-span-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-sm font-medium text-gray-700">1. Tempel teks full</label>
                                <Button
                                    type="button"
                                    size="sm"
                                    @click="runPreview"
                                    :disabled="!fullForm.text.trim() || previewLoading"
                                    class="bg-[#337ab7] hover:bg-[#286090]"
                                >
                                    {{ previewLoading ? 'Memproses...' : 'Pratinjau' }}
                                </Button>
                            </div>
                            <p class="text-xs text-gray-500">
                                Format: baris kode + waktu (contoh <code>{{ episode.title }} 01:09</code>), baris [judul], lalu isi. Baris
                                <code>Penelepon N.</code> memulai kelompok penelepon. Segmen ditambahkan di akhir daftar; tanpa baris penelepon, segmen ikut kelompok terakhir.
                            </p>
                            <textarea
                                v-model="fullForm.text"
                                rows="18"
                                class="w-full rounded-md border border-gray-300 p-3 font-mono text-xs leading-relaxed"
                                :placeholder="`${episode.title} 00:00\n[Kata pembuka]\n\nMaster: ...\n\nPenelepon 1.\n\n${episode.title} 01:09\n[Judul]\n\nPenelepon: ...`"
                            ></textarea>
                            <p v-if="fullForm.errors.text" class="text-sm text-red-500">{{ fullForm.errors.text }}</p>
                        </div>
                        <div class="flex min-w-0 flex-col gap-2 lg:col-span-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700">2. Pratinjau hasil pemecahan</span>
                                <span v-if="preview" class="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    {{ preview.length }} segmen · {{ new Set(preview.filter((p) => p.caller > 0).map((p) => p.caller)).size }} penelepon
                                </span>
                            </div>
                            <p v-if="previewError" class="text-sm text-red-500">{{ previewError }}</p>
                            <div class="overflow-auto rounded border border-gray-200">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-600">
                                        <tr>
                                            <th class="px-2 py-2">Penelepon</th>
                                            <th class="px-2 py-2">Kode</th>
                                            <th class="px-2 py-2">Waktu</th>
                                            <th class="min-w-[12rem] px-2 py-2">Judul</th>
                                            <th class="px-2 py-2">Isi (awal)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(p, i) in preview ?? []" :key="i" class="border-t border-gray-100 align-top">
                                            <td class="px-2 py-1.5 font-medium whitespace-nowrap">{{ callerLabel(p.caller) }}</td>
                                            <td class="px-2 py-1.5 whitespace-nowrap text-gray-600">{{ p.code }}</td>
                                            <td class="px-2 py-1.5 tabular-nums">{{ p.time }}</td>
                                            <td class="px-2 py-1.5">{{ p.title || '-' }}</td>
                                            <td class="max-w-[14rem] truncate px-2 py-1.5 text-gray-500">{{ p.excerpt || '-' }}</td>
                                        </tr>
                                        <tr v-if="!preview">
                                            <td colspan="5" class="px-2 py-8 text-center text-gray-500">Tempel teks lalu klik Pratinjau.</td>
                                        </tr>
                                        <tr v-else-if="preview.length === 0">
                                            <td colspan="5" class="px-2 py-8 text-center text-red-500">Tidak ada segmen terbaca. Periksa baris kode + waktu.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Single segment -->
                    <form v-else-if="modalType === 'add' || modalType === 'edit'" @submit.prevent="saveSegment" class="space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kelompok</label>
                                <select v-model.number="segmentForm.caller" class="h-9 w-full rounded-md border border-gray-300 px-2 text-sm">
                                    <option v-for="c in callerOptions" :key="c" :value="c">{{ callerLabel(c) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kode</label>
                                <Input v-model="segmentForm.code" :placeholder="episode.title" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Waktu</label>
                                <Input v-model="segmentForm.time" placeholder="01:09" />
                                <p v-if="segmentForm.errors.time" class="mt-1 text-xs text-red-500">Format mm:ss atau j:mm:ss</p>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Judul</label>
                            <Input v-model="segmentForm.title" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Isi</label>
                            <TiptapEditor v-model="segmentForm.content" height="300px" />
                        </div>
                    </form>

                    <!-- Deletes -->
                    <p v-else-if="modalType === 'delete'" class="text-sm text-gray-600">
                        Hapus segmen <strong>{{ selected?.code }} {{ selected ? formatTime(selected.timestamp) : '' }}</strong>?
                    </p>
                    <p v-else-if="modalType === 'bulk-delete'" class="text-sm text-gray-600">Hapus {{ selectedIds.length }} segmen terpilih?</p>
                    <p v-else-if="modalType === 'delete-all'" class="text-sm text-gray-600">
                        Hapus <strong>semua {{ rows.length }} segmen</strong> transkrip episode {{ episode.title }}? Tindakan ini tidak bisa dibatalkan.
                    </p>
                </div>

                <div v-if="modalType !== 'full'" class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4">
                    <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                    <Button
                        v-if="modalType === 'add' || modalType === 'edit'"
                        type="button"
                        @click="saveSegment"
                        :disabled="segmentForm.processing"
                        :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'"
                    >
                        {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                    </Button>
                    <Button v-else type="button" @click="confirmDelete" class="bg-[#d9534f] hover:bg-[#d43f3a]">
                        {{ modalType === 'delete-all' ? 'Hapus Semua' : 'Hapus' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

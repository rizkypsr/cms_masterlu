<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface Category {
    id: number;
    title: string;
    audios_count: number;
}

interface Episode {
    id: number;
    audio2_category_id: number;
    title: string;
    url: string | null;
    duration: string | null;
    year: number | null;
    month: number | null;
    aired_on: string | null;
    seq: number;
    segments_count: number;
}

const props = defineProps<{
    categories: Category[];
    selectedCategoryId: number | null;
    years: number[];
    selectedYear: number | null;
    hasUndated: boolean;
    episodes: Episode[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthName = (m: number | null) => (m ? MONTHS[m - 1] : 'Tanpa bulan');

const onlyWithTranscript = ref(false);

const categoryStrip = ref<HTMLElement | null>(null);
const yearStrip = ref<HTMLElement | null>(null);

/** On phones the category/year chips scroll sideways; keep the selected one visible (e.g. 2015 after coming back from a transcript). */
const revealActiveChips = () => {
    for (const strip of [categoryStrip.value, yearStrip.value]) {
        const active = strip?.querySelector<HTMLElement>('[data-active]');
        if (strip && active && strip.scrollWidth > strip.clientWidth) {
            strip.scrollLeft = active.offsetLeft - strip.offsetLeft - (strip.clientWidth - active.offsetWidth) / 2;
        }
    }
};

onMounted(revealActiveChips);
watch(
    () => [props.selectedCategoryId, props.selectedYear],
    () => nextTick(revealActiveChips),
);
const searchQuery = ref('');

const visibleEpisodes = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.episodes.filter((e) => (!onlyWithTranscript.value || e.segments_count > 0) && (!q || e.title.toLowerCase().includes(q)));
});

const monthGroups = computed(() => {
    const groups = new Map<number | null, Episode[]>();
    for (const e of visibleEpisodes.value) {
        if (!groups.has(e.month)) groups.set(e.month, []);
        groups.get(e.month)!.push(e);
    }
    return [...groups.entries()].map(([month, items]) => ({ month, items }));
});

const withTranscriptCount = computed(() => props.episodes.filter((e) => e.segments_count > 0).length);

const go = (params: Record<string, number | string | null>) => {
    router.get('/audio2', params, { preserveState: false });
};

const formatDate = (d: string | null) => {
    if (!d) return '-';
    const [y, m, day] = d.split('-').map(Number);
    return `${day} ${MONTHS[m - 1]} ${y}`;
};

// Episode modal
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const selected = ref<Episode | null>(null);

const form = useForm({
    audio2_category_id: props.selectedCategoryId,
    title: '',
    url: '',
    duration: '',
    year: (props.selectedYear ?? '') as number | string,
    month: null as number | null,
    aired_on: '',
});

const openModal = (type: 'add' | 'edit' | 'delete', episode?: Episode) => {
    modalType.value = type;
    selected.value = episode ?? null;
    form.clearErrors();
    if (type === 'edit' && episode) {
        form.audio2_category_id = episode.audio2_category_id;
        form.title = episode.title;
        form.url = episode.url ?? '';
        form.duration = episode.duration ?? '';
        form.year = episode.year ?? '';
        form.month = episode.month;
        form.aired_on = episode.aired_on ?? '';
    } else if (type === 'add') {
        form.reset();
        form.audio2_category_id = props.selectedCategoryId;
        form.year = props.selectedYear ?? '';
    }
    modalOpen.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };
    if (modalType.value === 'add') {
        form.post('/audio2/episode', options);
    } else if (selected.value) {
        form.put(`/audio2/episode/${selected.value.id}`, options);
    }
};

const destroy = () => {
    if (!selected.value) return;
    router.delete(`/audio2/episode/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (modalOpen.value = false),
    });
};

// Category management
const manageOpen = ref(false);
const categoryList = ref<Category[]>([...props.categories]);
watch(
    () => props.categories,
    (value) => (categoryList.value = [...value]),
);

const newCategory = useForm({ title: '' });
const editingId = ref<number | null>(null);
const renameForm = useForm({ title: '' });
const deletingId = ref<number | null>(null);
const categoryError = computed(() => (page.props.errors as Record<string, string>)?.category ?? '');

const openManage = () => {
    newCategory.reset();
    newCategory.clearErrors();
    editingId.value = null;
    deletingId.value = null;
    manageOpen.value = true;
};

const addCategory = () => {
    newCategory.post('/audio2/category', { preserveScroll: true, preserveState: true, onSuccess: () => newCategory.reset() });
};

const startRename = (c: Category) => {
    deletingId.value = null;
    editingId.value = c.id;
    renameForm.title = c.title;
    renameForm.clearErrors();
    nextTick(() => {
        const input = document.getElementById('audio2-category-rename') as HTMLInputElement | null;
        input?.focus();
        input?.select();
    });
};

const saveRename = (c: Category) => {
    renameForm.put(`/audio2/category/${c.id}`, { preserveScroll: true, preserveState: true, onSuccess: () => (editingId.value = null) });
};

const moveCategory = (index: number, offset: number) => {
    const list = categoryList.value;
    const target = index + offset;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
    router.post('/audio2/category/reorder', { ids: list.map((c) => c.id) }, { preserveScroll: true, preserveState: true });
};

const deleteCategory = (c: Category) => {
    router.delete(`/audio2/category/${c.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (deletingId.value = null),
    });
};
</script>

<template>
    <Head title="Audio 2" />

    <DashboardLayout :user="user">
        <div class="flex flex-col bg-[#d3dce6] p-4 lg:h-[calc(100vh-48px)] lg:overflow-hidden lg:p-6">
            <h1 class="mb-4 text-xl text-gray-600 lg:mb-6">Audio 2</h1>

            <div class="flex flex-1 flex-col gap-4 lg:flex-row lg:gap-6 lg:overflow-hidden">
                <!-- Categories: chips on mobile, sidebar on desktop -->
                <div class="flex shrink-0 flex-col overflow-hidden rounded shadow lg:w-56">
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 font-medium text-white">
                        Kategori
                        <button @click="openManage" title="Kelola kategori" class="flex items-center gap-1 rounded px-1.5 py-0.5 text-sm font-normal hover:bg-white/20">
                            <Icon icon="mdi:cog" class="h-4 w-4" />
                            Kelola
                        </button>
                    </div>
                    <div ref="categoryStrip" class="flex gap-2 overflow-x-auto bg-white p-2 lg:block lg:flex-1 lg:overflow-y-auto lg:p-0">
                        <button
                            v-for="c in categories"
                            :key="c.id"
                            @click="go({ category: c.id })"
                            :data-active="c.id === selectedCategoryId || undefined"
                            class="flex shrink-0 items-center justify-between gap-3 rounded px-3 py-2 text-left text-sm whitespace-nowrap lg:w-full lg:rounded-none lg:border-b lg:border-gray-100 lg:px-4 lg:py-2.5"
                            :class="c.id === selectedCategoryId ? 'bg-blue-50 font-semibold text-[#337ab7]' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 lg:bg-white lg:hover:bg-gray-50'"
                        >
                            <span>{{ c.title }}</span>
                            <span class="text-xs opacity-75">{{ c.audios_count }}</span>
                        </button>
                        <p v-if="categories.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Belum ada data. Jalankan <code>php artisan audio2:import-from-audio1</code>.
                        </p>
                    </div>
                </div>

                <!-- Episodes -->
                <div class="flex min-h-[60vh] min-w-0 flex-1 flex-col overflow-hidden rounded bg-white shadow lg:min-h-0">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="text-sm text-gray-600">Episode</span>
                            <span class="truncate text-sm font-medium text-red-500">
                                {{ categories.find((c) => c.id === selectedCategoryId)?.title }} {{ selectedYear ?? (years.length > 0 ? '· Tanpa tahun' : '') }}
                            </span>
                        </div>
                        <Button v-if="selectedCategoryId" @click="openModal('add')" class="shrink-0 bg-[#5cb85c] hover:bg-[#4cae4c]" size="sm" title="Tambah Episode">
                            <Icon icon="mdi:plus" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Tambah Episode</span>
                        </Button>
                    </div>

                    <div v-if="years.length > 0" ref="yearStrip" class="flex gap-2 overflow-x-auto border-b border-gray-200 px-4 py-2">
                        <button
                            v-for="y in years"
                            :key="y"
                            @click="go({ category: selectedCategoryId, year: y })"
                            :data-active="y === selectedYear || undefined"
                            class="shrink-0 rounded px-3 py-1 text-sm"
                            :class="y === selectedYear ? 'bg-[#337ab7] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        >
                            {{ y }}
                        </button>
                        <button
                            v-if="hasUndated"
                            @click="go({ category: selectedCategoryId, year: 'none' })"
                            :data-active="selectedYear === null || undefined"
                            class="shrink-0 rounded px-3 py-1 text-sm whitespace-nowrap"
                            :class="selectedYear === null ? 'bg-[#337ab7] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        >
                            Tanpa tahun
                        </button>
                    </div>

                    <div class="flex flex-col gap-2 border-b border-gray-200 px-4 py-2 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="onlyWithTranscript" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                            Hanya yang ada transkrip ({{ withTranscriptCount }})
                        </label>
                        <Input v-model="searchQuery" placeholder="Search..." class="h-8 w-full sm:w-48" />
                    </div>

                    <div class="flex-1 overflow-auto">
                        <table class="w-full">
                            <thead class="sticky top-0 z-10 bg-gray-50">
                                <tr class="border-b border-gray-200 text-left text-sm font-medium text-gray-600">
                                    <th class="w-0 px-2 py-2 sm:px-4"></th>
                                    <th class="px-2 py-2 sm:px-4">Judul</th>
                                    <th class="hidden w-40 px-2 py-2 sm:px-4 md:table-cell">Tanggal</th>
                                    <th class="hidden w-24 px-2 py-2 sm:px-4 md:table-cell">Durasi</th>
                                    <th class="w-28 px-2 py-2 sm:px-4">Transkrip</th>
                                </tr>
                            </thead>
                            <tbody v-for="group in monthGroups" :key="String(group.month)">
                                <tr class="border-b border-gray-200 bg-gray-100">
                                    <td colspan="5" class="px-2 py-1.5 sm:px-4 text-sm font-semibold text-gray-700">
                                        {{ monthName(group.month) }}
                                        <span class="font-normal text-gray-500">· {{ group.items.length }} episode</span>
                                    </td>
                                </tr>
                                <tr v-for="e in group.items" :key="e.id" class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-2 py-2 sm:px-4">
                                        <div class="flex items-center gap-1">
                                            <button
                                                @click="openModal('edit', e)"
                                                title="Edit episode"
                                                class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            >
                                                <Icon icon="mdi:pencil" class="h-3 w-3" />
                                            </button>
                                            <button
                                                @click="openModal('delete', e)"
                                                title="Hapus episode"
                                                class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            >
                                                <Icon icon="mdi:delete" class="h-3 w-3" />
                                            </button>
                                            <Link
                                                :href="`/audio2/episode/${e.id}/transcript`"
                                                title="Kelola transkrip"
                                                class="flex h-6 w-6 items-center justify-center rounded bg-[#337ab7] text-white hover:bg-[#286090]"
                                            >
                                                <Icon icon="mdi:text-box-outline" class="h-3 w-3" />
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 sm:px-4 text-sm">
                                        <Link :href="`/audio2/episode/${e.id}/transcript`" class="font-medium text-gray-800 hover:text-[#337ab7]">{{ e.title }}</Link>
                                        <div v-if="e.aired_on || e.duration" class="text-xs text-gray-500 md:hidden">
                                            {{ [e.aired_on ? formatDate(e.aired_on) : null, e.duration].filter(Boolean).join(' · ') }}
                                        </div>
                                    </td>
                                    <td class="hidden px-2 py-2 sm:px-4 text-sm text-gray-600 md:table-cell">{{ formatDate(e.aired_on) }}</td>
                                    <td class="hidden px-2 py-2 sm:px-4 text-sm text-gray-600 md:table-cell">{{ e.duration ?? '-' }}</td>
                                    <td class="px-2 py-2 sm:px-4 text-sm">
                                        <span v-if="e.segments_count > 0" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-green-800">
                                            {{ e.segments_count }} segmen
                                        </span>
                                        <span v-else class="text-xs text-gray-500">Kosong</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-if="monthGroups.length === 0">
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada data</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-4 py-2 text-sm text-gray-600">Total {{ visibleEpisodes.length }} episode</div>
                </div>
            </div>
        </div>

        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ modalType === 'add' ? 'Tambah Episode' : modalType === 'edit' ? 'Edit Episode' : 'Hapus Episode' }}
                    </DialogTitle>
                </DialogHeader>

                <div v-if="modalType === 'delete'" class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Hapus episode <strong>{{ selected?.title }}</strong> beserta {{ selected?.segments_count ?? 0 }} segmen transkripnya?
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="destroy">Hapus</Button>
                    </div>
                </div>

                <form v-else @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Kategori</label>
                        <select v-model="form.audio2_category_id" class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Judul</label>
                        <Input v-model="form.title" placeholder="Wenda200103" />
                        <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Link audio</label>
                        <Input v-model="form.url" placeholder="https://..." />
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Durasi</label>
                            <Input v-model="form.duration" placeholder="00:41:20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Tahun</label>
                            <Input v-model.number="form.year" type="number" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Bulan</label>
                            <select v-model="form.month" class="h-9 w-full rounded-md border border-gray-300 px-2 text-sm">
                                <option :value="null">-</option>
                                <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tanggal tayang</label>
                        <Input v-model="form.aired_on" type="date" />
                        <p class="mt-1 text-xs text-gray-500">Kosongkan untuk dibaca otomatis dari judul.</p>
                        <p v-if="form.errors.aired_on" class="mt-1 text-sm text-red-500">{{ form.errors.aired_on }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'"
                        >
                            {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="manageOpen" @update:open="manageOpen = $event">
            <DialogContent class="flex max-h-[90vh] flex-col sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Kelola Kategori</DialogTitle>
                </DialogHeader>

                <div class="-mx-6 flex-1 overflow-y-auto border-y border-gray-200">
                    <div
                        v-for="(c, index) in categoryList"
                        :key="c.id"
                        class="flex items-center gap-2 border-b border-gray-100 px-6 py-2 last:border-b-0"
                        :class="deletingId === c.id ? 'bg-red-50' : ''"
                    >
                        <div class="flex shrink-0 gap-1">
                            <button @click="moveCategory(index, -1)" :disabled="index === 0" title="Naik" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 disabled:opacity-40">
                                <Icon icon="mdi:arrow-up" class="h-3 w-3" />
                            </button>
                            <button @click="moveCategory(index, 1)" :disabled="index === categoryList.length - 1" title="Turun" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 disabled:opacity-40">
                                <Icon icon="mdi:arrow-down" class="h-3 w-3" />
                            </button>
                        </div>

                        <form v-if="editingId === c.id" @submit.prevent="saveRename(c)" class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex gap-2">
                                <Input id="audio2-category-rename" v-model="renameForm.title" class="h-8 min-w-0 flex-1" @keydown.esc.stop.prevent="editingId = null" />
                                <Button type="submit" size="sm" :disabled="renameForm.processing" title="Simpan" class="h-8 bg-[#5cb85c] px-2 hover:bg-[#4cae4c] sm:px-3">
                                    <Icon icon="mdi:check" class="h-4 w-4 sm:hidden" />
                                    <span class="hidden sm:inline">Simpan</span>
                                </Button>
                                <Button type="button" size="sm" variant="outline" title="Batal" class="h-8 px-2 sm:px-3" @click="editingId = null">
                                    <Icon icon="mdi:close" class="h-4 w-4 sm:hidden" />
                                    <span class="hidden sm:inline">Batal</span>
                                </Button>
                            </div>
                            <p v-if="renameForm.errors.title" class="text-xs text-red-500">{{ renameForm.errors.title }}</p>
                        </form>

                        <template v-else-if="deletingId === c.id">
                            <span class="min-w-0 flex-1 text-sm text-red-700">Hapus <strong>{{ c.title }}</strong>?</span>
                            <Button size="sm" class="h-7 bg-[#d9534f] hover:bg-[#d43f3a]" @click="deleteCategory(c)">Hapus</Button>
                            <Button size="sm" variant="outline" class="h-7" @click="deletingId = null">Batal</Button>
                        </template>

                        <template v-else>
                            <span class="min-w-0 flex-1 truncate text-sm text-gray-800">{{ c.title }}</span>
                            <span class="shrink-0 text-xs text-gray-500">{{ c.audios_count }} episode</span>
                            <button @click="startRename(c)" title="Ubah nama" class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]">
                                <Icon icon="mdi:pencil" class="h-3 w-3" />
                            </button>
                            <button
                                @click="editingId = null; deletingId = c.id"
                                :disabled="c.audios_count > 0"
                                :title="c.audios_count > 0 ? 'Masih berisi episode, tidak bisa dihapus' : 'Hapus'"
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a] disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <Icon icon="mdi:delete" class="h-3 w-3" />
                            </button>
                        </template>
                    </div>
                    <p v-if="categoryList.length === 0" class="px-6 py-6 text-center text-sm text-gray-500">Belum ada kategori.</p>
                </div>

                <p v-if="categoryError" class="text-sm text-red-500">{{ categoryError }}</p>
                <p class="text-xs text-gray-500">Kategori yang masih berisi episode tidak bisa dihapus. Pindahkan episodenya lewat Edit episode.</p>

                <form @submit.prevent="addCategory" class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-700">Tambah kategori</label>
                    <div class="flex gap-2">
                        <Input v-model="newCategory.title" placeholder="Nama kategori" class="min-w-0 flex-1" />
                        <Button type="submit" :disabled="newCategory.processing || !newCategory.title.trim()" class="bg-[#5cb85c] hover:bg-[#4cae4c]">
                            <Icon icon="mdi:plus" class="h-4 w-4 sm:mr-1" />
                            <span class="hidden sm:inline">Tambah</span>
                        </Button>
                    </div>
                    <p v-if="newCategory.errors.title" class="text-xs text-red-500">{{ newCategory.errors.title }}</p>
                </form>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

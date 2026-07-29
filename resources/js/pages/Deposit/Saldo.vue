<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface UserRow {
    id: number;
    nama: string | null;
    email: string | null;
    username: string | null;
    balance_rp: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    users: Paginated<UserRow>;
    filters: { search: string; with_balance: boolean };
    maxTopupRp: number;
    maxBulkUsers: number;
}>();

const page = usePage();
const user = page.props.auth?.user;

const search = ref(props.filters.search ?? '');
const withBalance = ref(props.filters.with_balance);

const applyFilter = () => {
    router.get(
        '/deposit/saldo',
        { search: search.value || undefined, with_balance: withBalance.value ? 1 : undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

// ---- Bulk selection ----
const selectedIds = ref<Set<number>>(new Set());
// True = every user matching the current filter, not just the loaded page.
const selectAllMatching = ref(false);

const isSelected = (id: number) => selectAllMatching.value || selectedIds.value.has(id);

const toggleSelect = (id: number) => {
    // Ticking a row off a filter-wide selection collapses it back to explicit
    // ids, otherwise the checkbox would appear to do nothing.
    if (selectAllMatching.value) {
        selectAllMatching.value = false;
        selectedIds.value = new Set(props.users.data.map((u) => u.id).filter((x) => x !== id));
        return;
    }
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
};

const allOnPageSelected = computed(
    () => props.users.data.length > 0 && props.users.data.every((u) => isSelected(u.id)),
);

const toggleSelectAllOnPage = () => {
    if (selectAllMatching.value) {
        selectAllMatching.value = false;
        selectedIds.value = new Set();
        return;
    }
    const next = new Set(selectedIds.value);
    if (allOnPageSelected.value) {
        props.users.data.forEach((u) => next.delete(u.id));
    } else {
        props.users.data.forEach((u) => next.add(u.id));
    }
    selectedIds.value = next;
};

const clearSelection = () => {
    selectedIds.value = new Set();
    selectAllMatching.value = false;
};

const selectedCount = computed(() =>
    selectAllMatching.value ? props.users.total : selectedIds.value.size,
);

// ---- Manual correction (single or bulk) ----
const adjustOpen = ref(false);
const adjustMode = ref<'single' | 'bulk'>('single');
const selected = ref<UserRow | null>(null);

const adjustForm = useForm({
    pengguna_id: null as number | null,
    delta_rp: null as number | null,
    type: 'adjust' as 'adjust' | 'bonus',
    note: '',
});

const openAdjust = (row: UserRow) => {
    adjustMode.value = 'single';
    selected.value = row;
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.pengguna_id = row.id;
    adjustOpen.value = true;
};

const openBulkAdjust = () => {
    adjustMode.value = 'bulk';
    selected.value = null;
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.type = 'bonus';
    adjustOpen.value = true;
};

const submitAdjust = () => {
    if (adjustMode.value === 'bulk') {
        router.post(
            '/deposit/adjust/bulk',
            {
                delta_rp: adjustForm.delta_rp,
                type: adjustForm.type,
                note: adjustForm.note,
                select_all: selectAllMatching.value,
                user_ids: selectAllMatching.value ? [] : [...selectedIds.value],
                search: props.filters.search,
                with_balance: props.filters.with_balance,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    adjustOpen.value = false;
                    clearSelection();
                },
            },
        );
        return;
    }

    adjustForm.post('/deposit/adjust', {
        preserveScroll: true,
        onSuccess: () => (adjustOpen.value = false),
    });
};

const formatRp = (value: number | null) =>
    value === null ? '-' : 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);

// Balance may legitimately go negative; surface it before it happens.
const balanceAfter = computed(() => {
    if (!selected.value || adjustForm.delta_rp === null) return null;
    return selected.value.balance_rp + Number(adjustForm.delta_rp);
});

// Total money this batch moves — the number worth double-checking before an
// irreversible credit across many accounts.
const bulkTotal = computed(() => {
    if (adjustForm.delta_rp === null) return null;
    return Number(adjustForm.delta_rp) * selectedCount.value;
});
</script>

<template>
    <Head title="Saldo Pengguna" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl text-gray-700">Saldo Deposit — Saldo Pengguna</h1>
                    <p class="text-xs text-gray-500">
                        Koreksi manual untuk kasus di luar alur topup: salah kredit, transfer ganda, kompensasi gangguan,
                        atau saldo awal.
                    </p>
                </div>
                <div class="flex gap-2 text-sm">
                    <Link href="/deposit" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Antrean Topup
                    </Link>
                    <Link href="/deposit/tarif" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Tarif AI
                    </Link>
                    <Link href="/deposit/laporan" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Laporan
                    </Link>
                </div>
            </div>

            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">{{ users.total }} pengguna</span>
                    <form class="flex flex-wrap items-center gap-2" @submit.prevent="applyFilter">
                        <label class="flex items-center gap-1.5 text-xs text-gray-600">
                            <input v-model="withBalance" type="checkbox" class="h-4 w-4" @change="applyFilter" />
                            Hanya yang punya saldo
                        </label>
                        <Input v-model="search" type="text" placeholder="Cari nama / email / username" class="h-8 w-64" />
                        <Button type="submit" size="sm" class="bg-[#337ab7] hover:bg-[#286090]">
                            <Icon icon="mdi:magnify" class="h-4 w-4" />
                        </Button>
                    </form>
                </div>

                <!-- Bulk action toolbar -->
                <div
                    v-if="selectedCount > 0"
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100 bg-blue-50 px-4 py-2"
                >
                    <div class="text-sm text-blue-700">
                        <strong>{{ selectedCount }}</strong> pengguna dipilih
                        <button
                            v-if="!selectAllMatching && allOnPageSelected && users.total > users.data.length"
                            class="ml-2 underline hover:no-underline"
                            @click="selectAllMatching = true"
                        >
                            Pilih semua {{ users.total }} pengguna yang cocok filter
                        </button>
                        <span v-if="selectAllMatching" class="ml-2 text-xs text-blue-600">
                            (semua halaman, mengikuti filter saat ini)
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" class="bg-[#f0ad4e] hover:bg-[#eea236]" @click="openBulkAdjust">
                            <Icon icon="mdi:cash-multiple" class="mr-1 h-4 w-4" />
                            Koreksi Massal
                        </Button>
                        <Button size="sm" variant="outline" @click="clearSelection">Batal</Button>
                    </div>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="users.data.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="w-8 px-3 py-2">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300"
                                        :checked="allOnPageSelected"
                                        title="Pilih semua di halaman ini"
                                        @change="toggleSelectAllOnPage"
                                    />
                                </th>
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 text-right font-medium">Saldo</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in users.data"
                                :key="row.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                                :class="{ 'bg-blue-50/60': isSelected(row.id) }"
                            >
                                <td class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300"
                                        :checked="isSelected(row.id)"
                                        @change="toggleSelect(row.id)"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <Link :href="`/deposit/pengguna/${row.id}`" class="font-medium text-[#337ab7] hover:underline">
                                        {{ row.nama ?? '-' }}
                                    </Link>
                                    <div class="text-xs text-gray-500">{{ row.email ?? row.username }}</div>
                                </td>
                                <td
                                    class="px-3 py-2 text-right font-medium"
                                    :class="row.balance_rp < 0 ? 'text-red-600' : 'text-gray-700'"
                                >
                                    {{ formatRp(row.balance_rp) }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-center gap-1">
                                        <button
                                            class="flex h-7 items-center gap-1 rounded bg-[#f0ad4e] px-2 text-xs text-white hover:bg-[#eea236]"
                                            title="Koreksi saldo"
                                            @click="openAdjust(row)"
                                        >
                                            <Icon icon="mdi:tune" class="h-4 w-4" />
                                            Koreksi
                                        </button>
                                        <Link
                                            :href="`/deposit/pengguna/${row.id}`"
                                            class="flex h-7 items-center gap-1 rounded bg-[#337ab7] px-2 text-xs text-white hover:bg-[#286090]"
                                        >
                                            <Icon icon="mdi:history" class="h-4 w-4" />
                                            Riwayat
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-10 text-center text-sm text-gray-500">Pengguna tidak ditemukan.</div>

                    <div
                        v-if="users.data.length"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm text-gray-500"
                    >
                        <span>halaman {{ users.current_page }} / {{ users.last_page }}</span>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="(link, idx) in users.links"
                                :key="idx"
                                :disabled="!link.url"
                                class="rounded px-2 py-1 text-xs"
                                :class="
                                    link.active
                                        ? 'bg-[#337ab7] text-white'
                                        : link.url
                                          ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                          : 'cursor-not-allowed text-gray-300'
                                "
                                v-html="link.label"
                                @click="goToPage(link.url)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual correction -->
        <Dialog :open="adjustOpen" @update:open="adjustOpen = $event">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {{ adjustMode === 'bulk' ? `Koreksi Massal (${selectedCount} pengguna)` : 'Koreksi Saldo' }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitAdjust">
                    <div v-if="adjustMode === 'single'" class="rounded bg-gray-50 p-3 text-sm">
                        <div class="font-medium text-gray-700">{{ selected?.nama ?? '-' }}</div>
                        <div class="text-xs text-gray-500">{{ selected?.email }}</div>
                        <div class="mt-2 flex justify-between text-xs">
                            <span class="text-gray-500">Saldo sekarang</span>
                            <span class="font-medium text-gray-700">{{ formatRp(selected?.balance_rp ?? null) }}</span>
                        </div>
                    </div>

                    <div v-else class="rounded bg-gray-50 p-3 text-sm text-gray-700">
                        Nominal yang sama akan diterapkan ke <strong>{{ selectedCount }}</strong> pengguna.
                        <span v-if="selectAllMatching" class="text-xs text-gray-500">
                            (semua pengguna yang cocok filter saat ini)
                        </span>
                        <p class="mt-1 text-xs text-gray-500">
                            Tiap pengguna tetap mendapat baris buku besarnya sendiri. Maks {{ maxBulkUsers }} pengguna
                            sekali proses.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Jenis</label>
                        <select
                            v-model="adjustForm.type"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                        >
                            <option value="adjust">Koreksi (adjust)</option>
                            <option value="bonus">Bonus / saldo awal</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Nominal (Rp) <span class="text-red-500">*</span>
                        </label>
                        <Input v-model.number="adjustForm.delta_rp" type="number" step="1" placeholder="-5000" />
                        <p class="mt-1 text-xs text-gray-500">
                            Positif = menambah, negatif = menarik. Maks Rp{{ new Intl.NumberFormat('id-ID').format(maxTopupRp) }}.
                        </p>
                        <p
                            v-if="adjustMode === 'single' && balanceAfter !== null"
                            class="mt-1 text-xs"
                            :class="balanceAfter < 0 ? 'text-red-600' : 'text-gray-600'"
                        >
                            Saldo setelah koreksi: <strong>{{ formatRp(balanceAfter) }}</strong>
                            <span v-if="balanceAfter < 0"> — saldo akan menjadi negatif.</span>
                        </p>
                        <p
                            v-if="adjustMode === 'bulk' && bulkTotal !== null"
                            class="mt-2 rounded border p-2 text-xs"
                            :class="bulkTotal < 0 ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                        >
                            <Icon icon="mdi:alert-outline" class="mr-1 inline h-3.5 w-3.5" />
                            Total dana bergerak:
                            <strong>{{ formatRp(bulkTotal) }}</strong>
                            ({{ formatRp(Number(adjustForm.delta_rp)) }} × {{ selectedCount }} pengguna).
                            Periksa jumlah nolnya — koreksi ini tidak bisa dibatalkan.
                        </p>
                        <p v-if="adjustForm.errors.delta_rp" class="mt-1 text-sm text-red-600">{{ adjustForm.errors.delta_rp }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Alasan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="adjustForm.note"
                            rows="3"
                            placeholder="Koreksi transfer ganda topup #12"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            <strong>Ditampilkan ke user</strong> di riwayat mutasi mereka. Saldo yang berubah tanpa alasan
                            yang terlihat adalah sumber komplain nomor satu.
                        </p>
                        <p v-if="adjustForm.errors.note" class="mt-1 text-sm text-red-600">{{ adjustForm.errors.note }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="adjustOpen = false">Batal</Button>
                        <Button type="submit" :disabled="adjustForm.processing" class="bg-[#f0ad4e] hover:bg-[#eea236]">
                            Simpan Koreksi
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface TopupUser {
    id: number;
    nama: string | null;
    email: string | null;
    username: string | null;
    balance_rp: number;
}

interface TopupRow {
    id: number;
    pengguna: TopupUser | null;
    amount_rp: number;
    credited_rp: number | null;
    status: string;
    payment_note: string | null;
    rejected_reason: string | null;
    approved_by: string | null;
    paid_at: string | null;
    created_at: string | null;
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

defineProps<{
    topups: Paginated<TopupRow>;
    filters: { status: string };
    counts: Record<string, number>;
    maxTopupRp: number;
}>();

const page = usePage();
const user = page.props.auth?.user;

const tabs = [
    { value: 'pending', label: 'Menunggu Verifikasi' },
    { value: 'paid', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'all', label: 'Semua' },
];

const setStatus = (status: string) => {
    router.get('/deposit', { status }, { preserveState: true, preserveScroll: true, replace: true });
};

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

// ---- Approve ----
const approveOpen = ref(false);
const selected = ref<TopupRow | null>(null);

const approveForm = useForm({
    credited_rp: null as number | null,
    payment_note: '',
});

const openApprove = (row: TopupRow) => {
    selected.value = row;
    approveForm.reset();
    approveForm.clearErrors();
    approveForm.credited_rp = row.amount_rp;
    approveOpen.value = true;
};

const submitApprove = () => {
    if (!selected.value) return;
    approveForm.post(`/deposit/topup/${selected.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => (approveOpen.value = false),
    });
};

// ---- Reject ----
const rejectOpen = ref(false);

const rejectForm = useForm({
    rejected_reason: '',
});

const openReject = (row: TopupRow) => {
    selected.value = row;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectOpen.value = true;
};

const submitReject = () => {
    if (!selected.value) return;
    rejectForm.post(`/deposit/topup/${selected.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => (rejectOpen.value = false),
    });
};

const formatRp = (value: number | null) =>
    value === null ? '-' : 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);

const formatDate = (value: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Mistyping one extra zero is the failure mode no other process catches.
const amountMismatch = () =>
    selected.value !== null &&
    approveForm.credited_rp !== null &&
    Number(approveForm.credited_rp) !== selected.value.amount_rp;
</script>

<template>
    <Head title="Antrean Topup" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl text-gray-700">Saldo Deposit — Antrean Topup</h1>
                    <p class="text-xs text-gray-500">
                        User transfer manual lewat WhatsApp, admin cocokkan bukti transfer dengan nomor permintaan lalu
                        setujui.
                    </p>
                </div>
                <div class="flex gap-2 text-sm">
                    <Link href="/deposit/saldo" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Saldo Pengguna
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
                <!-- Status tabs -->
                <div class="flex shrink-0 border-b border-gray-200">
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm transition-colors"
                        :class="
                            filters.status === tab.value
                                ? 'border-[#337ab7] font-medium text-[#337ab7]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        "
                        @click="setStatus(tab.value)"
                    >
                        {{ tab.label }}
                        <span
                            v-if="counts[tab.value]"
                            class="rounded-full px-1.5 py-0.5 text-[10px] text-white"
                            :class="tab.value === 'pending' ? 'bg-[#f0ad4e]' : 'bg-gray-400'"
                        >
                            {{ counts[tab.value] }}
                        </span>
                    </button>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="topups.data.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">#</th>
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 text-right font-medium">Diminta</th>
                                <th class="px-3 py-2 text-right font-medium">Dikreditkan</th>
                                <th class="px-3 py-2 text-right font-medium">Saldo Sekarang</th>
                                <th class="px-3 py-2 font-medium">Catatan</th>
                                <th class="px-3 py-2 font-medium">Waktu</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in topups.data"
                                :key="row.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                            >
                                <td class="px-3 py-2 font-mono text-gray-500">{{ row.id }}</td>
                                <td class="px-3 py-2">
                                    <Link
                                        v-if="row.pengguna"
                                        :href="`/deposit/pengguna/${row.pengguna.id}`"
                                        class="font-medium text-[#337ab7] hover:underline"
                                    >
                                        {{ row.pengguna.nama ?? '-' }}
                                    </Link>
                                    <span v-else class="text-gray-400">(user tidak ditemukan)</span>
                                    <div class="text-xs text-gray-500">{{ row.pengguna?.email }}</div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-700">{{ formatRp(row.amount_rp) }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-700">
                                    {{ formatRp(row.credited_rp) }}
                                </td>
                                <td class="px-3 py-2 text-right text-gray-500">
                                    {{ formatRp(row.pengguna?.balance_rp ?? null) }}
                                </td>
                                <td class="max-w-xs px-3 py-2 text-xs text-gray-500">
                                    <div v-if="row.payment_note" class="truncate" :title="row.payment_note">
                                        {{ row.payment_note }}
                                    </div>
                                    <div v-if="row.rejected_reason" class="truncate text-red-600" :title="row.rejected_reason">
                                        {{ row.rejected_reason }}
                                    </div>
                                    <div v-if="row.approved_by" class="text-[11px] text-gray-400">
                                        oleh {{ row.approved_by }}
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ formatDate(row.created_at) }}</td>
                                <td class="px-3 py-2">
                                    <div v-if="row.status === 'pending'" class="flex justify-center gap-1">
                                        <button
                                            class="flex h-7 items-center gap-1 rounded bg-[#5cb85c] px-2 text-xs text-white hover:bg-[#4cae4c]"
                                            @click="openApprove(row)"
                                        >
                                            <Icon icon="mdi:check" class="h-4 w-4" />
                                            Setujui
                                        </button>
                                        <button
                                            class="flex h-7 items-center gap-1 rounded bg-[#d9534f] px-2 text-xs text-white hover:bg-[#d43f3a]"
                                            @click="openReject(row)"
                                        >
                                            <Icon icon="mdi:close" class="h-4 w-4" />
                                            Tolak
                                        </button>
                                    </div>
                                    <div v-else class="text-center">
                                        <span
                                            class="rounded px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                row.status === 'paid'
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-red-100 text-red-600'
                                            "
                                        >
                                            {{ row.status === 'paid' ? 'Disetujui' : 'Ditolak' }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-10 text-center text-sm text-gray-500">
                        {{ filters.status === 'pending' ? 'Tidak ada permintaan menunggu verifikasi.' : 'Belum ada data.' }}
                    </div>

                    <div
                        v-if="topups.data.length"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm text-gray-500"
                    >
                        <span>{{ topups.total }} permintaan &middot; halaman {{ topups.current_page }} / {{ topups.last_page }}</span>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="(link, idx) in topups.links"
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

        <!-- Approve -->
        <Dialog :open="approveOpen" @update:open="approveOpen = $event">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Setujui Topup #{{ selected?.id }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitApprove">
                    <div class="rounded bg-gray-50 p-3 text-sm">
                        <div class="font-medium text-gray-700">{{ selected?.pengguna?.nama ?? '-' }}</div>
                        <div class="text-xs text-gray-500">{{ selected?.pengguna?.email }}</div>
                        <div class="mt-2 flex justify-between text-xs">
                            <span class="text-gray-500">Diminta</span>
                            <span class="font-medium text-gray-700">{{ formatRp(selected?.amount_rp ?? null) }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">Saldo sekarang</span>
                            <span class="text-gray-700">{{ formatRp(selected?.pengguna?.balance_rp ?? null) }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Nominal dikreditkan (Rp) <span class="text-red-500">*</span>
                        </label>
                        <Input v-model.number="approveForm.credited_rp" type="number" min="1" :max="maxTopupRp" step="1" />
                        <p class="mt-1 text-xs text-gray-500">
                            Isi sesuai yang <strong>benar-benar diterima</strong> di rekening. Maks Rp{{
                                new Intl.NumberFormat('id-ID').format(maxTopupRp)
                            }}.
                        </p>
                        <p v-if="amountMismatch()" class="mt-1 text-xs text-amber-600">
                            <Icon icon="mdi:alert" class="mr-1 inline h-3.5 w-3.5" />
                            Berbeda dari yang diminta — tulis selisihnya di catatan, user akan melihatnya.
                        </p>
                        <p v-if="approveForm.errors.credited_rp" class="mt-1 text-sm text-red-600">
                            {{ approveForm.errors.credited_rp }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Catatan pembayaran</label>
                        <Input v-model="approveForm.payment_note" type="text" placeholder="Transfer BCA a.n. Budi, ref 8827361" />
                        <p class="mt-1 text-xs text-gray-500">Tampil di riwayat mutasi user.</p>
                        <p v-if="approveForm.errors.payment_note" class="mt-1 text-sm text-red-600">
                            {{ approveForm.errors.payment_note }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="approveOpen = false">Batal</Button>
                        <Button type="submit" :disabled="approveForm.processing" class="bg-[#5cb85c] hover:bg-[#4cae4c]">
                            Setujui &amp; Kreditkan
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Reject -->
        <Dialog :open="rejectOpen" @update:open="rejectOpen = $event">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Tolak Topup #{{ selected?.id }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitReject">
                    <p class="text-sm text-gray-600">
                        Saldo tidak akan berubah. Alasan di bawah <strong>ditampilkan ke user</strong> — tulis kalimat yang
                        bisa mereka baca, bukan kode internal.
                    </p>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Alasan penolakan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="rejectForm.rejected_reason"
                            rows="3"
                            placeholder="Bukti transfer tidak terbaca, mohon kirim ulang"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                        />
                        <p v-if="rejectForm.errors.rejected_reason" class="mt-1 text-sm text-red-600">
                            {{ rejectForm.errors.rejected_reason }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="rejectOpen = false">Batal</Button>
                        <Button type="submit" :disabled="rejectForm.processing" class="bg-[#d9534f] hover:bg-[#d43f3a]">
                            Tolak
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

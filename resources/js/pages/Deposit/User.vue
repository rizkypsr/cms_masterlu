<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface LedgerRow {
    id: number;
    delta_rp: number;
    type: string;
    balance_after_rp: number;
    ref_type: string | null;
    ref_id: number | null;
    model: string | null;
    input_tokens: number | null;
    cached_input_tokens: number | null;
    output_tokens: number | null;
    note: string | null;
    created_by: string | null;
    created_at: string | null;
}

interface TopupRow {
    id: number;
    amount_rp: number;
    credited_rp: number | null;
    status: string;
    payment_note: string | null;
    rejected_reason: string | null;
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
    pengguna: {
        id: number;
        nama: string | null;
        email: string | null;
        username: string | null;
        balance_rp: number;
        ledger_rp: number;
        drift: boolean;
    };
    ledger: Paginated<LedgerRow>;
    topups: TopupRow[];
    maxTopupRp: number;
}>();

const page = usePage();
const user = page.props.auth?.user;

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
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

const typeLabel: Record<string, string> = {
    topup: 'Topup',
    bonus: 'Bonus',
    adjust: 'Koreksi',
    spend: 'Pemakaian',
};

const typeClass: Record<string, string> = {
    topup: 'bg-green-100 text-green-700',
    bonus: 'bg-blue-100 text-blue-700',
    adjust: 'bg-amber-100 text-amber-700',
    spend: 'bg-gray-100 text-gray-600',
};
</script>

<template>
    <Head :title="`Riwayat Saldo — ${pengguna.nama ?? pengguna.id}`" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <button
                    class="flex items-center gap-1 rounded bg-white px-3 py-1.5 text-sm text-gray-600 shadow-sm hover:bg-gray-50"
                    @click="router.get('/deposit/saldo')"
                >
                    <Icon icon="mdi:arrow-left" class="h-4 w-4" />
                    Kembali
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-medium text-gray-700">{{ pengguna.nama ?? '-' }}</h1>
                    <p class="text-xs text-gray-500">{{ pengguna.email ?? pengguna.username }}</p>
                </div>
            </div>

            <!-- Summary -->
            <div class="flex flex-wrap gap-4 rounded bg-white p-4 shadow">
                <div>
                    <div class="text-xs text-gray-500">Saldo (cache)</div>
                    <div class="text-lg font-medium" :class="pengguna.balance_rp < 0 ? 'text-red-600' : 'text-gray-700'">
                        {{ formatRp(pengguna.balance_rp) }}
                    </div>
                </div>
                <div class="border-l border-gray-100 pl-4">
                    <div class="text-xs text-gray-500">Buku besar (otoritatif)</div>
                    <div class="text-lg font-medium text-gray-700">{{ formatRp(pengguna.ledger_rp) }}</div>
                </div>
            </div>

            <div
                v-if="pengguna.drift"
                class="flex items-start gap-2 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <Icon icon="mdi:alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <strong>Saldo tidak cocok dengan buku besar.</strong>
                    Hampir selalu karena saldo pernah diubah lewat UPDATE manual tanpa baris buku besar. Buku besar yang
                    benar — telusuri penyebabnya dulu, baru koreksi lewat menu Koreksi Saldo.
                </div>
            </div>

            <!-- Ledger -->
            <div class="overflow-hidden rounded bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Riwayat Mutasi ({{ ledger.total }})</span>
                    <span class="ml-2 text-xs text-gray-400">append-only — tidak bisa diubah atau dihapus</span>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="ledger.data.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Waktu</th>
                                <th class="px-3 py-2 font-medium">Jenis</th>
                                <th class="px-3 py-2 text-right font-medium">Perubahan</th>
                                <th class="px-3 py-2 text-right font-medium">Saldo Sesudah</th>
                                <th class="px-3 py-2 font-medium">Token</th>
                                <th class="px-3 py-2 font-medium">Catatan</th>
                                <th class="px-3 py-2 font-medium">Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in ledger.data" :key="row.id" class="border-b border-gray-100 text-sm hover:bg-gray-50">
                                <td class="px-3 py-2 text-xs text-gray-500">{{ formatDate(row.created_at) }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="typeClass[row.type] ?? 'bg-gray-100 text-gray-600'">
                                        {{ typeLabel[row.type] ?? row.type }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2 text-right font-medium"
                                    :class="row.delta_rp < 0 ? 'text-red-600' : 'text-green-700'"
                                >
                                    {{ row.delta_rp > 0 ? '+' : '' }}{{ formatRp(row.delta_rp) }}
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ formatRp(row.balance_after_rp) }}</td>
                                <td class="px-3 py-2 text-[11px] text-gray-500">
                                    <template v-if="row.input_tokens !== null">
                                        <div>masuk: {{ row.input_tokens }}<span v-if="row.cached_input_tokens"> (cache {{ row.cached_input_tokens }})</span></div>
                                        <div>jawab: {{ row.output_tokens }}</div>
                                        <div class="text-gray-400">{{ row.model }}</div>
                                    </template>
                                    <span v-else class="text-gray-300">-</span>
                                </td>
                                <td class="max-w-xs px-3 py-2 text-xs text-gray-600">
                                    <div class="truncate" :title="row.note ?? ''">{{ row.note ?? '-' }}</div>
                                    <div v-if="row.ref_type" class="text-[11px] text-gray-400">
                                        {{ row.ref_type }}#{{ row.ref_id }}
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ row.created_by ?? '(sistem)' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-8 text-center text-sm text-gray-500">Belum ada mutasi.</div>

                    <div
                        v-if="ledger.data.length"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm text-gray-500"
                    >
                        <span>halaman {{ ledger.current_page }} / {{ ledger.last_page }}</span>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="(link, idx) in ledger.links"
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

            <!-- Topup history -->
            <div class="overflow-hidden rounded bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Riwayat Permintaan Topup</span>
                </div>
                <div class="overflow-x-auto p-4">
                    <table v-if="topups.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">#</th>
                                <th class="px-3 py-2 font-medium">Waktu</th>
                                <th class="px-3 py-2 text-right font-medium">Diminta</th>
                                <th class="px-3 py-2 text-right font-medium">Dikreditkan</th>
                                <th class="px-3 py-2 text-center font-medium">Status</th>
                                <th class="px-3 py-2 font-medium">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in topups" :key="row.id" class="border-b border-gray-100 text-sm hover:bg-gray-50">
                                <td class="px-3 py-2 font-mono text-gray-500">{{ row.id }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ formatDate(row.created_at) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ formatRp(row.amount_rp) }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-700">{{ formatRp(row.credited_rp) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            row.status === 'paid'
                                                ? 'bg-green-100 text-green-700'
                                                : row.status === 'rejected'
                                                  ? 'bg-red-100 text-red-600'
                                                  : 'bg-amber-100 text-amber-700'
                                        "
                                    >
                                        {{ row.status === 'paid' ? 'Disetujui' : row.status === 'rejected' ? 'Ditolak' : 'Menunggu' }}
                                    </span>
                                </td>
                                <td class="max-w-xs px-3 py-2 text-xs text-gray-500">
                                    <div v-if="row.payment_note" class="truncate" :title="row.payment_note">{{ row.payment_note }}</div>
                                    <div v-if="row.rejected_reason" class="truncate text-red-600" :title="row.rejected_reason">
                                        {{ row.rejected_reason }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-8 text-center text-sm text-gray-500">Belum ada permintaan topup.</div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface UserCostRow {
    id: number;
    nama: string | null;
    username: string | null;
    conversation_count: number;
    messages: number;
    total_tokens: number;
    balance_rp: number;
    cost_rp: number;
}

const props = defineProps<{
    users: UserCostRow[];
    filters: { from: string | null; to: string | null };
    unpricedModels: string[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const applyFilter = () => {
    router.get(
        '/chat/report',
        { from: from.value || undefined, to: to.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const clearFilter = () => {
    from.value = '';
    to.value = '';
    applyFilter();
};

const formatRp = (value: number | null) =>
    value === null ? '-' : 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);

const grandTotalCost = computed(() => props.users.reduce((sum, row) => sum + row.cost_rp, 0));
const grandTotalTokens = computed(() => props.users.reduce((sum, row) => sum + row.total_tokens, 0));
</script>

<template>
    <Head title="Laporan Cost Chat" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div
                v-if="unpricedModels.length"
                class="flex items-start gap-2 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <Icon icon="mdi:alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <strong>Model tanpa tarif aktif:</strong> {{ unpricedModels.join(', ') }}.
                    Pertanyaan dari model ini <strong>tidak ditagih sama sekali</strong> dan tidak masuk hitungan di
                    bawah. Tambahkan tarifnya di
                    <Link href="/deposit/tarif" class="underline hover:no-underline">halaman Tarif AI</Link>.
                </div>
            </div>

            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Laporan Cost per Pengguna</span>
                    <form class="flex flex-wrap items-center gap-2" @submit.prevent="applyFilter">
                        <Input v-model="from" type="date" class="h-8 w-36" />
                        <span class="text-xs text-gray-400">s/d</span>
                        <Input v-model="to" type="date" class="h-8 w-36" />
                        <Button type="submit" size="sm" class="bg-[#337ab7] hover:bg-[#286090]">Terapkan</Button>
                        <Button
                            v-if="filters.from || filters.to"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="clearFilter"
                        >
                            Reset
                        </Button>
                    </form>
                </div>

                <p class="border-b border-gray-100 bg-blue-50 px-4 py-2 text-xs text-blue-700">
                    <Icon icon="mdi:information-outline" class="mr-1 inline h-3.5 w-3.5" />
                    Hanya menghitung pesan assistant dengan token tercatat. Biaya dihitung dari tarif aktif di
                    <Link href="/deposit/tarif" class="underline hover:no-underline">tabel ai_rate</Link> — sumber yang
                    sama dengan yang menagih saldo user, jadi angkanya bisa dipakai saat menjawab komplain.
                </p>

                <div class="overflow-x-auto p-4">
                    <table v-if="users.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 text-right font-medium">Percakapan</th>
                                <th class="px-3 py-2 text-right font-medium">Pertanyaan</th>
                                <th class="px-3 py-2 text-right font-medium">Token</th>
                                <th class="px-3 py-2 text-right font-medium">Biaya</th>
                                <th class="px-3 py-2 text-right font-medium">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in users" :key="row.id" class="border-b border-gray-100 text-sm hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <Link :href="`/deposit/pengguna/${row.id}`" class="font-medium text-[#337ab7] hover:underline">
                                        {{ row.nama ?? '-' }}
                                    </Link>
                                    <div class="text-xs text-gray-500">{{ row.username }}</div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.conversation_count }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.messages }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.total_tokens.toLocaleString('id-ID') }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-700">{{ formatRp(row.cost_rp) }}</td>
                                <td
                                    class="px-3 py-2 text-right"
                                    :class="row.balance_rp < 0 ? 'text-red-600' : 'text-gray-500'"
                                >
                                    {{ formatRp(row.balance_rp) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 text-sm font-medium text-gray-700">
                                <td class="px-3 py-2" colspan="3">Total</td>
                                <td class="px-3 py-2 text-right">{{ grandTotalTokens.toLocaleString('id-ID') }}</td>
                                <td class="px-3 py-2 text-right">{{ formatRp(grandTotalCost) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div v-else class="py-8 text-center text-sm text-gray-500">
                        Tidak ada penggunaan chat pada rentang ini.
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

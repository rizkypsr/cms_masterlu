<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface UserCostRow {
    id: number;
    nama: string | null;
    username: string | null;
    conversation_count: number;
    total_tokens: number;
    cost_usd: number;
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
    users: Paginated<UserCostRow>;
    filters: { from: string | null; to: string | null };
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

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const formatUsd = (value: number) => `$${value.toFixed(4)}`;

const grandTotalCost = () => props.users.data.reduce((sum, row) => sum + row.cost_usd, 0);
const grandTotalTokens = () => props.users.data.reduce((sum, row) => sum + row.total_tokens, 0);
</script>

<template>
    <Head title="Laporan Cost Chat" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-6 bg-[#d3dce6] p-6">
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

                <p class="border-b border-gray-100 bg-amber-50 px-4 py-2 text-xs text-amber-700">
                    <Icon icon="mdi:information-outline" class="mr-1 inline h-3.5 w-3.5" />
                    Hanya menghitung pesan assistant (balasan LLM) dengan token tercatat. Estimasi cost berdasarkan
                    harga per model di <code>config/chat_pricing.php</code>.
                </p>

                <div class="overflow-x-auto p-4">
                    <table v-if="users.data.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 text-right font-medium">Percakapan</th>
                                <th class="px-3 py-2 text-right font-medium">Token</th>
                                <th class="px-3 py-2 text-right font-medium">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in users.data"
                                :key="row.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                            >
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-700">{{ row.nama ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ row.username }}</div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.conversation_count }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.total_tokens.toLocaleString('id-ID') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-gray-700">{{ formatUsd(row.cost_usd) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 text-sm font-medium text-gray-700">
                                <td class="px-3 py-2" colspan="2">Total (halaman ini)</td>
                                <td class="px-3 py-2 text-right">{{ grandTotalTokens().toLocaleString('id-ID') }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ formatUsd(grandTotalCost()) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div v-else class="py-8 text-center text-sm text-gray-500">
                        Tidak ada penggunaan chat pada rentang ini.
                    </div>

                    <div
                        v-if="users.data.length"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm text-gray-500"
                    >
                        <span>{{ users.total }} pengguna &middot; halaman {{ users.current_page }} / {{ users.last_page }}</span>
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
    </DashboardLayout>
</template>

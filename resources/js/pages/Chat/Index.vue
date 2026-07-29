<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface ConversationRow {
    id: number;
    title: string | null;
    pengguna: { id: number; nama: string | null; username: string | null } | null;
    category: string | null;
    message_count: number;
    total_tokens: number;
    cost_rp: number;
    created_at: string | null;
    updated_at: string | null;
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
    conversations: Paginated<ConversationRow>;
    filters: { search: string };
    unpricedModels: string[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const search = ref(props.filters.search ?? '');

const doSearch = () => {
    router.get('/chat', { search: search.value }, { preserveState: true, preserveScroll: true, replace: true });
};

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

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

const formatRp = (value: number | null) =>
    value === null ? '-' : 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
</script>

<template>
    <Head title="Percakapan Chat" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div
                v-if="unpricedModels.length"
                class="flex items-start gap-2 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <Icon icon="mdi:alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <strong>Model tanpa tarif aktif:</strong> {{ unpricedModels.join(', ') }}.
                    Pertanyaan dari model ini tidak ditagih sama sekali dan muncul sebagai Rp0. Tambahkan tarifnya di
                    <Link href="/deposit/tarif" class="underline hover:no-underline">halaman Tarif AI</Link>.
                </div>
            </div>

            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div>
                        <span class="text-sm font-medium text-gray-600">Percakapan</span>
                        <span class="ml-2 text-xs text-gray-400">{{ conversations.total }} total</span>
                    </div>
                    <form class="flex items-center gap-2" @submit.prevent="doSearch">
                        <Input v-model="search" type="text" placeholder="Cari judul / nama / username" class="h-8 w-64" />
                        <Button type="submit" size="sm" class="bg-[#337ab7] hover:bg-[#286090]">
                            <Icon icon="mdi:magnify" class="h-4 w-4" />
                        </Button>
                    </form>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="conversations.data.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Judul</th>
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 font-medium">Kategori</th>
                                <th class="px-3 py-2 text-right font-medium">Pesan</th>
                                <th class="px-3 py-2 text-right font-medium">Token</th>
                                <th class="px-3 py-2 text-right font-medium">Cost</th>
                                <th class="px-3 py-2 font-medium">Update</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in conversations.data"
                                :key="row.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                            >
                                <td class="max-w-xs truncate px-3 py-2 text-gray-700" :title="row.title ?? ''">
                                    {{ row.title ?? '(tanpa judul)' }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-700">{{ row.pengguna?.nama ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ row.pengguna?.username }}</div>
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ row.category ?? '-' }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.message_count }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.total_tokens.toLocaleString('id-ID') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-gray-600">{{ formatRp(row.cost_rp) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ formatDate(row.updated_at) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <Link
                                        :href="`/chat/${row.id}`"
                                        class="inline-flex h-7 items-center gap-1 rounded bg-[#337ab7] px-2 text-xs text-white hover:bg-[#286090]"
                                    >
                                        <Icon icon="mdi:eye" class="h-4 w-4" />
                                        Lihat
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-8 text-center text-sm text-gray-500">
                        {{ filters.search ? 'Percakapan tidak ditemukan' : 'Belum ada percakapan' }}
                    </div>

                    <div
                        v-if="conversations.data.length"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm text-gray-500"
                    >
                        <span>{{ conversations.total }} percakapan &middot; halaman {{ conversations.current_page }} / {{ conversations.last_page }}</span>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="(link, idx) in conversations.links"
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

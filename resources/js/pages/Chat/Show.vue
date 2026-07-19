<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface ChatMessageRow {
    id: number;
    role: string;
    content: string;
    flagged: boolean;
    model: string | null;
    prompt_tokens: number | null;
    completion_tokens: number | null;
    total_tokens: number | null;
    cost_usd: number | null;
    created_at: string | null;
}

interface ConversationDetail {
    id: number;
    title: string | null;
    pengguna: { id: number; nama: string | null; username: string | null } | null;
    category: string | null;
    created_at: string | null;
}

defineProps<{
    conversation: ConversationDetail;
    messages: ChatMessageRow[];
    total_tokens: number;
    total_cost_usd: number;
}>();

const page = usePage();
const user = page.props.auth?.user;

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

const formatUsd = (value: number | null) => (value === null ? '-' : `$${value.toFixed(4)}`);
</script>

<template>
    <Head :title="`Percakapan - ${conversation.title ?? 'Detail'}`" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <button
                    class="flex items-center gap-1 rounded bg-white px-3 py-1.5 text-sm text-gray-600 shadow-sm hover:bg-gray-50"
                    @click="router.get('/chat')"
                >
                    <Icon icon="mdi:arrow-left" class="h-4 w-4" />
                    Kembali
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-medium text-gray-700">
                        {{ conversation.title ?? '(tanpa judul)' }}
                    </h1>
                    <p class="text-xs text-gray-500">
                        {{ conversation.pengguna?.nama ?? '-' }}
                        <span v-if="conversation.pengguna?.username">({{ conversation.pengguna.username }})</span>
                        &middot; {{ conversation.category ?? 'tanpa kategori' }}
                        &middot; {{ formatDate(conversation.created_at) }}
                    </p>
                </div>
            </div>

            <!-- Summary -->
            <div class="flex gap-4 rounded bg-white p-4 shadow">
                <div>
                    <div class="text-xs text-gray-500">Total Pesan</div>
                    <div class="text-lg font-medium text-gray-700">{{ messages.length }}</div>
                </div>
                <div class="border-l border-gray-100 pl-4">
                    <div class="text-xs text-gray-500">Total Token</div>
                    <div class="text-lg font-medium text-gray-700">{{ total_tokens.toLocaleString('id-ID') }}</div>
                </div>
                <div class="border-l border-gray-100 pl-4">
                    <div class="text-xs text-gray-500">Total Cost</div>
                    <div class="font-mono text-lg font-medium text-gray-700">{{ formatUsd(total_cost_usd) }}</div>
                </div>
            </div>

            <!-- Messages -->
            <div class="space-y-3 rounded bg-white p-4 shadow">
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex"
                    :class="message.role === 'assistant' ? 'justify-start' : 'justify-end'"
                >
                    <div
                        class="max-w-2xl rounded-lg border p-3 text-sm"
                        :class="[
                            message.role === 'assistant' ? 'bg-blue-50 border-blue-100' : 'bg-gray-50 border-gray-200',
                            message.flagged ? '!border-red-300 !bg-red-50' : '',
                        ]"
                    >
                        <div class="mb-1 flex items-center gap-2 text-[11px] text-gray-500">
                            <span class="font-medium uppercase">{{ message.role }}</span>
                            <span
                                v-if="message.flagged"
                                class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] text-red-600"
                            >
                                flagged
                            </span>
                            <span>{{ formatDate(message.created_at) }}</span>
                        </div>
                        <p class="whitespace-pre-wrap text-gray-800">{{ message.content }}</p>
                        <div
                            v-if="message.role === 'assistant' && message.model"
                            class="mt-2 flex flex-wrap gap-3 border-t border-black/5 pt-2 text-[11px] text-gray-500"
                        >
                            <span>{{ message.model }}</span>
                            <span>prompt: {{ message.prompt_tokens ?? 0 }}</span>
                            <span>completion: {{ message.completion_tokens ?? 0 }}</span>
                            <span>total: {{ message.total_tokens ?? 0 }}</span>
                            <span class="font-mono">{{ formatUsd(message.cost_usd) }}</span>
                        </div>
                    </div>
                </div>

                <p v-if="!messages.length" class="py-8 text-center text-sm text-gray-400">Belum ada pesan.</p>
            </div>
        </div>
    </DashboardLayout>
</template>

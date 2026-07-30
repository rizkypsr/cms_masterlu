<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, inject } from 'vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { ChatCategoryNode, NodeActions } from './types';

const props = defineProps<{
    node: ChatCategoryNode;
    depth: number;
}>();

// Injected rather than emitted so actions don't have to be re-emitted through
// every level of the recursion.
const actions = inject<NodeActions>('categoryActions')!;
const maxLevels = inject<number>('maxLevels', 3);

// Rows deeper than the limit still render (existing data), they just can't
// gain another level from here.
const canAddChild = computed(() => props.depth < maxLevels - 1);

// Only the deepest node in a branch holds content. A node with active children
// is a grouping header: not selectable by users, nothing to attach.
const isLeaf = computed(() => !props.node.children.some((c) => c.is_active));

// Content attached before this node gained children is no longer served.
const hasStaleItems = computed(() => !isLeaf.value && (props.node.items_count ?? 0) > 0);
</script>

<template>
    <div>
        <div
            class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-2 last:border-b-0 hover:bg-gray-50/60"
            :style="{ paddingLeft: `${1 + depth * 1.5}rem` }"
        >
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <Icon
                    v-if="depth > 0"
                    icon="mdi:subdirectory-arrow-right"
                    class="h-3.5 w-3.5 shrink-0 text-gray-300"
                />
                <span :class="depth === 0 ? 'font-medium text-gray-700' : 'text-sm text-gray-600'">
                    {{ node.name }}
                </span>

                <Popover>
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-[#337ab7]"
                            title="Deskripsi"
                        >
                            <Icon icon="mdi:information-outline" class="h-4 w-4" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-72 text-sm text-gray-600">
                        <div
                            v-if="node.description"
                            class="prose prose-sm max-w-none [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                            v-html="node.description"
                        />
                        <span v-else>Belum ada deskripsi.</span>
                    </PopoverContent>
                </Popover>

                <span v-if="!node.is_active" class="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-500">
                    Nonaktif
                </span>

                <span v-if="!isLeaf" class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
                    Grup
                </span>
                <span
                    v-else-if="node.items_count"
                    class="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-700"
                >
                    {{ node.items_count }} konten
                </span>
                <span v-else class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                    Belum ada konten
                </span>

                <button
                    v-if="hasStaleItems"
                    type="button"
                    class="flex items-center gap-1 rounded bg-red-100 px-2 py-0.5 text-xs text-red-700 hover:bg-red-200"
                    title="Kategori ini punya sub-kategori, jadi kontennya tidak lagi disajikan ke pengguna. Klik untuk melihat dan membersihkan."
                    @click="actions.staleItems(node)"
                >
                    {{ node.items_count }} konten tidak terpakai
                    <Icon icon="mdi:broom" class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    v-if="isLeaf"
                    class="flex h-7 items-center gap-1 rounded bg-[#337ab7] px-2 text-xs text-white hover:bg-[#286090]"
                    title="Kelola Konten"
                    @click="actions.scope(node)"
                >
                    <Icon icon="mdi:folder-cog" class="h-4 w-4" />
                    {{ depth === 0 ? 'Kelola Konten' : 'Kelola' }}
                </button>
                <button
                    v-if="canAddChild"
                    class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                    title="Tambah Sub-Kategori"
                    @click="actions.add(node)"
                >
                    <Icon icon="mdi:plus" class="h-4 w-4" />
                </button>
                <span
                    v-else
                    class="flex h-7 w-7 items-center justify-center text-gray-300"
                    :title="`Sudah di level ${maxLevels} (paling dalam) — tidak bisa punya sub-kategori lagi`"
                >
                    <Icon icon="mdi:plus" class="h-4 w-4" />
                </span>
                <button
                    class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                    title="Edit"
                    @click="actions.edit(node)"
                >
                    <Icon icon="mdi:pencil" class="h-4 w-4" />
                </button>
                <button
                    class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                    title="Hapus"
                    @click="actions.remove(node)"
                >
                    <Icon icon="mdi:delete" class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Self-reference: renders however deep the tree goes. -->
        <CategoryNode
            v-for="child in node.children"
            :key="child.id"
            :node="child"
            :depth="depth + 1"
        />
    </div>
</template>

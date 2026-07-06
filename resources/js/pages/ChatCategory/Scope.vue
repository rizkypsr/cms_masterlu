<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { nextTick, onMounted, provide, reactive, ref, watch } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import TreeNode, { type ExpandControl, type TreeNodeData } from './TreeNode.vue';

interface ScopeNode {
    domain: string;
    level: string;
    node_id: number;
}

interface ScopeItem extends ScopeNode {
    label: string;
    path: string[];
    path_nodes: ScopeNode[];
    missing: boolean;
}

const props = defineProps<{
    category: { id: number; name: string; is_active: boolean };
    domains: { value: string; label: string }[];
    items: ScopeItem[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const keyOf = (n: { domain: string; level: string; node_id: number }) =>
    `${n.domain}:${n.level}:${n.node_id}`;

// Reactive checked-key set, seeded from saved items.
const checkedSet = reactive(new Set<string>(props.items.map(keyOf)));
// Enriched selected list (label + breadcrumb) for the side panel.
const selectedItems = ref<ScopeItem[]>([...props.items]);

const domainLabel = (value: string) =>
    props.domains.find((d) => d.value === value)?.label ?? value;

const activeDomain = ref(props.domains[0]?.value ?? 'book');
const rootsCache = reactive<Record<string, TreeNodeData[]>>({});
// Node children keyed by "domain:level:node_id" — shared with every TreeNode
// instance so a prefetched ancestor path is read synchronously, no per-level
// network wait when auto-expanding.
const childrenCache = reactive<Record<string, TreeNodeData[]>>({});
const loadingRoots = ref(false);
const saving = ref(false);

const countForDomain = (domain: string) =>
    [...checkedSet].filter((k) => k.startsWith(domain + ':')).length;

const refreshSelected = async () => {
    const { data } = await axios.get(`/chatbot/kategori/${props.category.id}/scope/items`);
    selectedItems.value = data.items ?? [];
};

const loadRoots = async (domain: string) => {
    if (rootsCache[domain]) return;
    loadingRoots.value = true;
    try {
        const { data } = await axios.get(`/chatbot/content-tree/${domain}/children`);
        rootsCache[domain] = data.nodes ?? [];
    } finally {
        loadingRoots.value = false;
    }
};

const toggle = async (node: TreeNodeData, checked: boolean) => {
    const key = keyOf(node);
    // Optimistic update.
    if (checked) checkedSet.add(key);
    else checkedSet.delete(key);

    saving.value = true;
    try {
        await axios.post(`/chatbot/kategori/${props.category.id}/scope/toggle`, {
            domain: node.domain,
            level: node.level,
            node_id: node.node_id,
            checked,
        });
        if (checked) {
            // Refetch to get the resolved breadcrumb/label for the new item.
            await refreshSelected();
        } else {
            selectedItems.value = selectedItems.value.filter((i) => keyOf(i) !== key);
        }
    } catch (error) {
        // Revert on failure.
        if (checked) checkedSet.delete(key);
        else checkedSet.add(key);
        const message =
            (error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
            'Gagal menyimpan. Coba lagi.';
        alert(message);
    } finally {
        saving.value = false;
    }
};

const uncheckItem = (item: ScopeItem) => {
    toggle(
        { domain: item.domain, level: item.level, node_id: item.node_id, label: item.label, has_children: false },
        false,
    );
};

// Which node keys are open — single shared source of truth (manual toggles in
// TreeNode mutate this same set, so auto-expand and manual expand can't drift).
const expandedKeys = reactive(new Set<string>());
// Only drives the one-shot scroll/highlight on the clicked leaf.
const expandControl = reactive<ExpandControl>({
    targetKey: '',
    token: 0,
});

const fetchChildrenInto = async (node: ScopeNode): Promise<void> => {
    const key = keyOf(node);
    if (childrenCache[key]) return;
    const { data } = await axios.get(`/chatbot/content-tree/${node.domain}/children`, {
        params: { level: node.level, node_id: node.node_id },
    });
    childrenCache[key] = data.nodes ?? [];
};

const openItem = async (item: ScopeItem) => {
    if (item.missing || !item.path_nodes.length) return;

    activeDomain.value = item.domain;
    await loadRoots(item.domain);
    await nextTick();

    // All ancestor ids are already known from the backend path — fetch every
    // level's children up front (in parallel) instead of letting the tree
    // cascade network calls level-by-level as each expand reveals the next id.
    const ancestors = item.path_nodes.slice(0, -1);
    await Promise.all(ancestors.map(fetchChildrenInto));

    // Expand one level at a time, awaiting Vue's DOM flush after each one.
    // A never-before-rendered ancestor (e.g. "2 Januari 2020" closed, its
    // child "Totem, 2 Januari 2020" never mounted at all) doesn't exist as a
    // component yet, so it can't react to a batched Set mutation made before
    // it exists. Waiting a tick per level guarantees each parent is actually
    // mounted — and has read the (already-true) key for itself — before its
    // own child's key gets added.
    for (const ancestor of ancestors) {
        expandedKeys.add(keyOf(ancestor));
        await nextTick();
    }

    const target = item.path_nodes[item.path_nodes.length - 1];
    expandControl.targetKey = keyOf(target);
    expandControl.token += 1;
    await nextTick();
};

provide('checkedSet', checkedSet);
provide('keyOf', keyOf);
provide('toggle', toggle);
provide('expandControl', expandControl);
provide('childrenCache', childrenCache);
provide('expandedKeys', expandedKeys);

watch(activeDomain, (d) => loadRoots(d));
onMounted(() => loadRoots(activeDomain.value));
</script>

<template>
    <Head :title="`Kelola Konten - ${category.name}`" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <!-- Header -->
            <div class="mb-4 flex items-center gap-3">
                <button
                    class="flex items-center gap-1 rounded bg-white px-3 py-1.5 text-sm text-gray-600 shadow-sm hover:bg-gray-50"
                    @click="router.get('/chatbot/kategori')"
                >
                    <Icon icon="mdi:arrow-left" class="h-4 w-4" />
                    Kembali
                </button>
                <div>
                    <h1 class="text-lg font-medium text-gray-700">Kelola Konten — {{ category.name }}</h1>
                    <p class="text-xs text-gray-500">
                        Centang node konten yang dicakup kategori ini. Centang parent = semua anak ikut tercakup.
                    </p>
                </div>
                <div class="ml-auto flex items-center gap-2 text-sm text-gray-500">
                    <Icon v-if="saving" icon="mdi:loading" class="h-4 w-4 animate-spin" />
                    <span>{{ checkedSet.size }} item dipilih</span>
                </div>
            </div>

            <!-- Empty warning -->
            <div
                v-if="checkedSet.size === 0"
                class="mb-4 flex items-center gap-2 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700"
            >
                <Icon icon="mdi:alert" class="h-4 w-4 shrink-0" />
                Kategori tanpa item tidak akan muncul di aplikasi. Pilih minimal satu node.
            </div>

            <div class="flex flex-1 gap-4 overflow-hidden">
            <div class="flex flex-1 flex-col overflow-hidden rounded bg-white shadow">
                <!-- Tabs -->
                <div class="flex shrink-0 border-b border-gray-200">
                    <button
                        v-for="domain in domains"
                        :key="domain.value"
                        class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm transition-colors"
                        :class="
                            activeDomain === domain.value
                                ? 'border-[#337ab7] font-medium text-[#337ab7]'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        "
                        @click="activeDomain = domain.value"
                    >
                        {{ domain.label }}
                        <span
                            v-if="countForDomain(domain.value)"
                            class="rounded-full bg-[#337ab7] px-1.5 py-0.5 text-[10px] text-white"
                        >
                            {{ countForDomain(domain.value) }}
                        </span>
                    </button>
                </div>

                <!-- Tree -->
                <div class="flex-1 overflow-auto p-4">
                    <div v-if="loadingRoots && !rootsCache[activeDomain]" class="flex items-center gap-2 text-sm text-gray-500">
                        <Icon icon="mdi:loading" class="h-4 w-4 animate-spin" />
                        Memuat...
                    </div>

                    <template v-else-if="rootsCache[activeDomain]?.length">
                        <TreeNode
                            v-for="node in rootsCache[activeDomain]"
                            :key="node.domain + ':' + node.level + ':' + node.node_id"
                            :node="node"
                            :depth="0"
                            :ancestor-checked="false"
                        />
                    </template>

                    <p v-else class="py-8 text-center text-sm text-gray-400">Tidak ada konten di domain ini.</p>
                </div>
            </div>

            <!-- Selected items panel -->
            <aside class="flex w-80 shrink-0 flex-col overflow-hidden rounded bg-white shadow">
                <div class="shrink-0 border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Konten Terpilih ({{ selectedItems.length }})</span>
                </div>
                <div class="flex-1 overflow-auto p-3">
                    <p v-if="!selectedItems.length" class="py-6 text-center text-xs text-gray-400">
                        Belum ada yang dipilih.
                    </p>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="item in selectedItems"
                            :key="keyOf(item)"
                            class="group rounded border border-gray-100 bg-gray-50 p-2 text-xs"
                            :class="item.missing ? '' : 'cursor-pointer hover:border-[#337ab7] hover:bg-blue-50'"
                            :title="item.missing ? '' : 'Klik untuk buka di pohon'"
                            @click="openItem(item)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1">
                                        <span class="rounded bg-[#337ab7] px-1.5 py-0.5 text-[10px] text-white">
                                            {{ domainLabel(item.domain) }}
                                        </span>
                                        <span
                                            v-if="item.missing"
                                            class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] text-red-600"
                                            title="Node sudah tidak ada di database"
                                        >
                                            hilang
                                        </span>
                                    </div>
                                    <p class="mt-1 truncate font-medium text-gray-700" :title="item.label">
                                        {{ item.label }}
                                    </p>
                                    <p
                                        v-if="item.path.length > 1"
                                        class="truncate text-[11px] text-gray-400"
                                        :title="item.path.join(' › ')"
                                    >
                                        {{ item.path.slice(0, -1).join(' › ') }}
                                    </p>
                                </div>
                                <button
                                    class="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500"
                                    title="Hapus dari kategori"
                                    @click.stop="uncheckItem(item)"
                                >
                                    <Icon icon="mdi:close" class="h-4 w-4" />
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </aside>
            </div>
        </div>
    </DashboardLayout>
</template>

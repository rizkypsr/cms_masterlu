<script setup lang="ts">
import { Icon } from '@iconify/vue';
import axios from 'axios';
import { computed, inject, nextTick, onMounted, ref, watch } from 'vue';

export interface TreeNodeData {
    domain: string;
    level: string;
    node_id: number;
    label: string;
    has_children: boolean;
}

// Only drives the one-shot scroll/highlight on the exact clicked node —
// expand/collapse itself is driven by the shared `expandedKeys` set below,
// so it works regardless of component mount order.
export interface ExpandControl {
    targetKey: string;
    token: number;
}

const props = defineProps<{
    node: TreeNodeData;
    depth: number;
    ancestorChecked: boolean;
}>();

const checkedSet = inject<Set<string>>('checkedSet')!;
const keyOf = inject<(n: TreeNodeData) => string>('keyOf')!;
const toggle = inject<(n: TreeNodeData, checked: boolean) => Promise<void>>('toggle')!;
const expandControl = inject<ExpandControl>('expandControl')!;
// Single source of truth for which nodes are open — shared across every
// TreeNode instance. A computed reading it reacts correctly whether the key
// was added before or after this particular node mounted.
const expandedKeys = inject<Set<string>>('expandedKeys')!;
// Prefetched children keyed by "domain:level:node_id" (see Scope.vue openItem).
// `children`/`loaded` below read straight off this shared object instead of a
// local ref populated via a watcher callback — so once Scope.vue has cached an
// entire ancestor path, every level in that path resolves in the SAME render
// pass, with no per-level async/watcher hop that could stall the cascade.
const childrenCache = inject<Record<string, TreeNodeData[]>>('childrenCache')!;

const loading = ref(false);
const rowRef = ref<HTMLElement | null>(null);
const highlighted = ref(false);

const nodeKey = computed(() => keyOf(props.node));
const expanded = computed(() => expandedKeys.has(nodeKey.value));
const checked = computed(() => checkedSet.has(nodeKey.value));
const childAncestorChecked = computed(() => props.ancestorChecked || checked.value);
const loaded = computed(() => nodeKey.value in childrenCache);
const children = computed(() => childrenCache[nodeKey.value] ?? []);

const fetchChildren = async () => {
    if (loaded.value || loading.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get(
            `/chatbot/content-tree/${props.node.domain}/children`,
            { params: { level: props.node.level, node_id: props.node.node_id } },
        );
        childrenCache[nodeKey.value] = data.nodes ?? [];
    } finally {
        loading.value = false;
    }
};

// Only need to fetch on manual expand — auto-expand already has the cache
// prefetched by Scope.vue before `expanded` ever flips true.
watch(expanded, (isExpanded) => {
    if (isExpanded && !loaded.value) fetchChildren();
}, { immediate: true });

const onExpand = () => {
    if (!props.node.has_children) return;
    if (expandedKeys.has(nodeKey.value)) {
        expandedKeys.delete(nodeKey.value);
    } else {
        expandedKeys.add(nodeKey.value);
    }
};

const onCheck = (event: Event) => {
    const target = event.target as HTMLInputElement;
    toggle(props.node, target.checked);
};

// Scroll/highlight the exact clicked node once it (and its ancestors) exist
// in the DOM. Runs on mount (covers nodes revealed later by the expand
// cascade) and whenever the click token bumps (covers already-mounted nodes).
const maybeHighlight = async () => {
    if (expandControl.token === 0 || nodeKey.value !== expandControl.targetKey) return;

    highlighted.value = true;
    await nextTick();
    rowRef.value?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    window.setTimeout(() => (highlighted.value = false), 2500);
};

onMounted(maybeHighlight);
watch(() => expandControl.token, maybeHighlight);
</script>

<template>
    <div>
        <div
            ref="rowRef"
            class="flex items-center gap-1 rounded py-1 pr-2 transition-colors"
            :class="highlighted ? 'bg-yellow-100 ring-1 ring-yellow-300' : 'hover:bg-gray-50'"
            :style="{ paddingLeft: depth * 18 + 'px' }"
        >
            <button
                v-if="node.has_children"
                type="button"
                class="flex h-5 w-5 shrink-0 items-center justify-center text-gray-400 hover:text-gray-600"
                @click="onExpand"
            >
                <Icon
                    :icon="loading ? 'mdi:loading' : expanded ? 'mdi:chevron-down' : 'mdi:chevron-right'"
                    :class="['h-4 w-4', loading && 'animate-spin']"
                />
            </button>
            <span v-else class="w-5 shrink-0"></span>

            <label class="flex flex-1 items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    class="h-4 w-4 shrink-0 rounded border-gray-300"
                    :checked="checked"
                    @change="onCheck"
                />
                <span :class="{ 'font-medium': node.has_children }">{{ node.label }}</span>
                <span
                    v-if="ancestorChecked"
                    class="rounded bg-green-50 px-1.5 py-0.5 text-[10px] text-green-600"
                    title="Sudah tercakup karena parent tercentang"
                >
                    tercakup
                </span>
            </label>
        </div>

        <div v-if="expanded && loaded">
            <p v-if="!children.length" class="py-1 text-xs text-gray-400" :style="{ paddingLeft: (depth + 1) * 18 + 20 + 'px' }">
                (kosong)
            </p>
            <TreeNode
                v-for="child in children"
                :key="child.domain + ':' + child.level + ':' + child.node_id"
                :node="child"
                :depth="depth + 1"
                :ancestor-checked="childAncestorChecked"
            />
        </div>
    </div>
</template>

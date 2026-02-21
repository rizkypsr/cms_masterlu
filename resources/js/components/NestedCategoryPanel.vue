<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, ref, onMounted, onBeforeUpdate, onUpdated, watch } from 'vue';
import { Card } from '@/components/ui/card';

export interface PanelAction {
    icon: string;
    color: 'blue' | 'green' | 'orange' | 'red';
    title?: string;
    onClick: (item: any, parent?: any) => void;
    show?: (item: any, parent?: any) => boolean;
}

export interface PanelHeaderAction {
    icon: string;
    color: 'blue' | 'green' | 'orange' | 'red';
    title?: string;
    onClick: (item: any) => void;
}

export interface NestedPanelColumn {
    header: {
        accessor: string;
        actions?: PanelHeaderAction[];
    };
    children?: {
        accessor: string;
        itemAccessor: string;
        actions?: PanelAction[];
        onClick?: (item: any) => void;
    };
}

interface Props {
    data: any[];
    columns: NestedPanelColumn;
    emptyMessage?: string;
    scrollKey?: string;
}

const props = withDefaults(defineProps<Props>(), {
    emptyMessage: 'Belum ada data',
    scrollKey: 'nested-panel-scroll',
});

const colorClasses = {
    blue: 'bg-[#5bc0de] hover:bg-[#46b8da]',
    green: 'bg-[#5cb85c] hover:bg-[#4cae4c]',
    orange: 'bg-[#f0ad4e] hover:bg-[#eea236]',
    red: 'bg-[#d9534f] hover:bg-[#d43f3a]',
};

const shouldShowAction = (action: PanelAction, item: any, parent?: any) => {
    if (action.show) {
        return action.show(item, parent);
    }
    return true;
};

// Scroll preservation using sessionStorage
const scrollContainer = ref<HTMLElement | null>(null);

onMounted(() => {
    if (scrollContainer.value) {
        const savedScroll = sessionStorage.getItem(props.scrollKey);
        if (savedScroll) {
            scrollContainer.value.scrollTop = parseInt(savedScroll);
        }
    }
});

const saveScrollPosition = () => {
    if (scrollContainer.value) {
        sessionStorage.setItem(props.scrollKey, scrollContainer.value.scrollTop.toString());
    }
};

// Save scroll position on scroll
const handleScroll = () => {
    saveScrollPosition();
};
</script>

<template>
    <div ref="scrollContainer" class="flex-1 space-y-4 overflow-y-auto bg-white p-4" @scroll="handleScroll">
        <template v-if="data.length">
            <Card
                v-for="item in data"
                :key="item.id"
                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <span class="font-medium text-gray-700">{{ item[columns.header.accessor] }}</span>
                    <div v-if="columns.header.actions" class="flex items-center gap-1">
                        <button
                            v-for="(action, index) in columns.header.actions"
                            :key="index"
                            @click="action.onClick(item)"
                            :class="['flex h-7 w-7 items-center justify-center rounded text-white', colorClasses[action.color]]"
                            :title="action.title"
                        >
                            <Icon :icon="action.icon" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Children -->
                <div v-if="columns.children && item[columns.children.accessor]?.length">
                    <div
                        v-for="child in item[columns.children.accessor]"
                        :key="child.id"
                        :class="[
                            'flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0',
                            columns.children.onClick ? 'cursor-pointer hover:bg-gray-50' : ''
                        ]"
                        @click="columns.children.onClick ? columns.children.onClick(child) : undefined"
                    >
                        <span class="text-sm text-gray-600">{{ child[columns.children.itemAccessor] }}</span>
                        <div v-if="columns.children.actions" class="flex items-center gap-1" @click.stop>
                            <button
                                v-for="(action, index) in columns.children.actions"
                                :key="index"
                                v-show="shouldShowAction(action, child, item)"
                                @click="action.onClick(child, item)"
                                :class="['flex h-7 w-7 items-center justify-center rounded text-white', colorClasses[action.color]]"
                                :title="action.title"
                            >
                                <Icon :icon="action.icon" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </Card>
        </template>
        <div v-else class="py-8 text-center text-gray-500">
            {{ emptyMessage }}
        </div>
    </div>
</template>

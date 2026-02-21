<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';
import { Card } from '@/components/ui/card';

export interface PanelAction {
    icon: string;
    color: 'blue' | 'green' | 'orange' | 'red';
    title?: string;
    onClick: (item: any) => void;
    show?: (item: any) => boolean;
}

export interface PanelHeaderAction {
    icon: string;
    color: 'blue' | 'green' | 'orange' | 'red';
    title?: string;
    onClick: () => void;
}

export interface PanelColumn {
    header: {
        title: string | ((item: any) => string);
        actions?: PanelHeaderAction[];
    };
    items?: {
        accessor: string;
        actions?: PanelAction[];
        onClick?: (item: any) => void;
        highlightCondition?: (item: any) => boolean;
    };
}

interface Props {
    data: any[];
    columns: PanelColumn;
    emptyMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
    emptyMessage: 'Belum ada data',
});

const colorClasses = {
    blue: 'bg-[#5bc0de] hover:bg-[#46b8da]',
    green: 'bg-[#5cb85c] hover:bg-[#4cae4c]',
    orange: 'bg-[#f0ad4e] hover:bg-[#eea236]',
    red: 'bg-[#d9534f] hover:bg-[#d43f3a]',
};

const getHeaderTitle = (item: any) => {
    if (typeof props.columns.header.title === 'function') {
        return props.columns.header.title(item);
    }
    return item[props.columns.header.title as string] || '';
};

const getItemValue = (item: any) => {
    if (!props.columns.items) return '';
    return item[props.columns.items.accessor] || '';
};

const shouldShowAction = (action: PanelAction, item: any) => {
    if (action.show) {
        return action.show(item);
    }
    return true;
};

const isItemHighlighted = (item: any) => {
    if (props.columns.items?.highlightCondition) {
        return props.columns.items.highlightCondition(item);
    }
    return false;
};
</script>

<template>
    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
        <template v-if="data.length">
            <Card
                v-for="item in data"
                :key="item.id"
                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <span class="font-medium text-gray-700">{{ getHeaderTitle(item) }}</span>
                    <div v-if="columns.header.actions" class="flex items-center gap-1">
                        <button
                            v-for="(action, index) in columns.header.actions"
                            :key="index"
                            @click="action.onClick()"
                            :class="['flex h-7 w-7 items-center justify-center rounded text-white', colorClasses[action.color]]"
                            :title="action.title"
                        >
                            <Icon :icon="action.icon" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Items (Children) -->
                <div v-if="columns.items && item[columns.items.accessor]?.length">
                    <div
                        v-for="child in item[columns.items.accessor]"
                        :key="child.id"
                        :class="[
                            'flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0',
                            columns.items.onClick ? 'cursor-pointer hover:bg-gray-50' : '',
                            isItemHighlighted(child) ? 'bg-blue-50 ring-2 ring-blue-200' : ''
                        ]"
                        @click="columns.items.onClick ? columns.items.onClick(child) : undefined"
                    >
                        <span class="text-sm text-gray-600">{{ getItemValue(child) }}</span>
                        <div v-if="columns.items.actions" class="flex items-center gap-1" @click.stop>
                            <button
                                v-for="(action, index) in columns.items.actions"
                                :key="index"
                                v-show="shouldShowAction(action, child)"
                                @click="action.onClick(child)"
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

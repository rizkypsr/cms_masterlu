<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { Switch } from '@/components/ui/switch';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface MenuMobile {
    id: number;
    nama: string;
    code: string;
    status: boolean;
}

const props = defineProps<{
    menus: MenuMobile[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const toggleStatus = (menuId: number) => {
    router.post(`/menu-mobile/${menuId}/toggle-status`, {}, {
        preserveState: false,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Menu Mobile" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <!-- Header -->
            <div class="mb-6 rounded-lg bg-yellow-400 p-4">
                <h1 class="text-2xl font-bold text-gray-800">Menu Mobile</h1>
            </div>

            <!-- Menu List -->
            <div class="flex-1 overflow-y-auto">
                <div class="space-y-3">
                    <div
                        v-for="menu in menus"
                        :key="menu.id"
                        class="flex items-center justify-between rounded-lg border-2 border-yellow-400 bg-white p-4 shadow-sm"
                    >
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-800">{{ menu.nama }}</h3>
                        </div>

                        <div class="flex items-center gap-4">
                            <!-- Switch Toggle -->
                            <Switch
                                :model-value="menu.status"
                                @update:model-value="() => toggleStatus(menu.id)"
                            />
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="menus.length === 0" class="py-12 text-center text-gray-500">
                    <Icon icon="mdi:menu" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                    <p class="text-lg">Tidak ada menu</p>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

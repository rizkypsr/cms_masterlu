<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface UserRow {
    id: number;
    nama: string | null;
    email: string | null;
    username: string | null;
    is_admin: boolean;
    is_self: boolean;
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
    users: Paginated<UserRow>;
    filters: { search: string; admins_only: boolean };
    adminCount: number;
}>();

const page = usePage();
const user = page.props.auth?.user;

const search = ref(props.filters.search ?? '');
const adminsOnly = ref(props.filters.admins_only);

const applyFilter = () => {
    router.get(
        '/settings/admin-akses',
        { search: search.value || undefined, admins_only: adminsOnly.value ? 1 : undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

// ---- Confirm ----
const confirmOpen = ref(false);
const target = ref<UserRow | null>(null);
const processing = ref(false);

const openConfirm = (row: UserRow) => {
    if (row.is_self) return;
    target.value = row;
    confirmOpen.value = true;
};

const confirmToggle = () => {
    if (!target.value) return;
    processing.value = true;
    router.post(
        `/settings/admin-akses/${target.value.id}/toggle`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
            onSuccess: () => (confirmOpen.value = false),
        },
    );
};

// Revoking the last admin leaves the admin API unreachable for everyone.
const isLastAdmin = (row: UserRow) => row.is_admin && props.adminCount <= 1;
</script>

<template>
    <Head title="Akses Admin" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div>
                <h1 class="text-xl text-gray-700">Akses Admin Aplikasi</h1>
                <p class="text-xs text-gray-500">
                    Menentukan siapa yang boleh membuka endpoint admin di aplikasi (verifikasi topup, koreksi saldo,
                    riwayat pemakaian). Ini akun pengguna aplikasi, bukan akun login CMS ini.
                </p>
            </div>

            <div class="flex items-start gap-2 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                <Icon icon="mdi:shield-account-outline" class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    Saat ini ada <strong>{{ adminCount }}</strong> admin.
                    Akun Anda sendiri tidak bisa diubah dari sini — minta admin lain kalau memang perlu.
                </div>
            </div>

            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">{{ users.total }} pengguna</span>
                    <form class="flex flex-wrap items-center gap-2" @submit.prevent="applyFilter">
                        <label class="flex items-center gap-1.5 text-xs text-gray-600">
                            <input v-model="adminsOnly" type="checkbox" class="h-4 w-4" @change="applyFilter" />
                            Hanya admin
                        </label>
                        <Input v-model="search" type="text" placeholder="Cari nama / email / username" class="h-8 w-64" />
                        <Button type="submit" size="sm" class="bg-[#337ab7] hover:bg-[#286090]">
                            <Icon icon="mdi:magnify" class="h-4 w-4" />
                        </Button>
                    </form>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="users.data.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 text-center font-medium">Status</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in users.data"
                                :key="row.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                                :class="{ 'bg-blue-50/40': row.is_self }"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-700">{{ row.nama ?? '-' }}</span>
                                        <span
                                            v-if="row.is_self"
                                            class="rounded bg-blue-100 px-2 py-0.5 text-[10px] text-blue-700"
                                        >
                                            akun Anda
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500">{{ row.email ?? row.username }}</div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="row.is_admin ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'"
                                    >
                                        {{ row.is_admin ? 'Admin' : 'Pengguna biasa' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-center">
                                        <span
                                            v-if="row.is_self"
                                            class="text-xs text-gray-400"
                                            title="Akses admin akun sendiri tidak bisa diubah dari sini"
                                        >
                                            —
                                        </span>
                                        <button
                                            v-else
                                            class="flex h-7 items-center gap-1 rounded px-2 text-xs text-white disabled:cursor-not-allowed disabled:opacity-50"
                                            :class="row.is_admin ? 'bg-[#d9534f] hover:bg-[#d43f3a]' : 'bg-[#5cb85c] hover:bg-[#4cae4c]'"
                                            :disabled="isLastAdmin(row)"
                                            :title="isLastAdmin(row) ? 'Admin terakhir — angkat admin lain dulu' : ''"
                                            @click="openConfirm(row)"
                                        >
                                            <Icon
                                                :icon="row.is_admin ? 'mdi:shield-off-outline' : 'mdi:shield-check-outline'"
                                                class="h-4 w-4"
                                            />
                                            {{ row.is_admin ? 'Cabut admin' : 'Jadikan admin' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-10 text-center text-sm text-gray-500">Pengguna tidak ditemukan.</div>

                    <div
                        v-if="users.data.length"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm text-gray-500"
                    >
                        <span>halaman {{ users.current_page }} / {{ users.last_page }}</span>
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

        <Dialog :open="confirmOpen" @update:open="confirmOpen = $event">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {{ target?.is_admin ? 'Cabut Akses Admin' : 'Jadikan Admin' }}
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="rounded bg-gray-50 p-3 text-sm">
                        <div class="font-medium break-words text-gray-700">{{ target?.nama ?? '-' }}</div>
                        <div class="text-xs break-words text-gray-500">{{ target?.email ?? target?.username }}</div>
                    </div>

                    <p v-if="!target?.is_admin" class="text-sm text-gray-600">
                        Akun ini akan bisa memverifikasi topup, mengoreksi saldo, dan melihat riwayat pemakaian
                        pengguna lain — termasuk teks pertanyaan mereka.
                    </p>
                    <p v-else class="text-sm text-gray-600">
                        Akun ini tidak akan bisa lagi membuka endpoint admin di aplikasi.
                    </p>

                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="confirmOpen = false">Batal</Button>
                        <Button
                            :disabled="processing"
                            :class="target?.is_admin ? 'bg-[#d9534f] hover:bg-[#d43f3a]' : 'bg-[#5cb85c] hover:bg-[#4cae4c]'"
                            @click="confirmToggle"
                        >
                            {{ target?.is_admin ? 'Cabut' : 'Jadikan Admin' }}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

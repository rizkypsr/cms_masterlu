<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface Plan {
    id: number;
    name: string;
    label: string;
    price: number;
    daily_limit: number;
    duration_days: number | null;
    seq: number;
    is_active: boolean;
    active_users_count: number;
}

interface UserPlan {
    id: number;
    name: string;
    label: string;
    daily_limit: number;
}

interface UserRow {
    id: number;
    nama: string | null;
    email: string | null;
    username: string | null;
    plan: UserPlan | null;
    plan_started_at: string | null;
    plan_expires_at: string | null;
    state: 'active' | 'expired' | 'free';
}

const props = defineProps<{
    plans: Plan[];
    users: UserRow[];
    filters: { search: string };
}>();

const page = usePage();
const user = page.props.auth?.user;

const search = ref(props.filters.search ?? '');

// ---- Plan modal ----
const planModalOpen = ref(false);
const planModalType = ref<'add' | 'edit'>('add');
const selectedPlan = ref<Plan | null>(null);

const planForm = useForm({
    name: '',
    label: '',
    price: 0,
    daily_limit: 0,
    duration_days: 30 as number | null,
    seq: 0,
    is_active: true,
});

const openPlanModal = (type: 'add' | 'edit', plan?: Plan) => {
    planModalType.value = type;
    selectedPlan.value = plan ?? null;

    if (type === 'add') {
        planForm.reset();
        planForm.seq = props.plans.length;
    } else if (plan) {
        planForm.name = plan.name;
        planForm.label = plan.label;
        planForm.price = plan.price;
        planForm.daily_limit = plan.daily_limit;
        planForm.duration_days = plan.duration_days;
        planForm.seq = plan.seq;
        planForm.is_active = plan.is_active;
    }

    planModalOpen.value = true;
};

const submitPlan = () => {
    if (planModalType.value === 'add') {
        planForm.post('/settings/subscription/plan', {
            preserveScroll: true,
            onSuccess: () => (planModalOpen.value = false),
        });
    } else if (selectedPlan.value) {
        planForm.put(`/settings/subscription/plan/${selectedPlan.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (planModalOpen.value = false),
        });
    }
};

const togglePlan = (plan: Plan) => {
    router.post(
        `/settings/subscription/plan/${plan.id}/toggle`,
        {},
        { preserveScroll: true },
    );
};

// ---- User assignment ----
const assignModalOpen = ref(false);
const selectedUser = ref<UserRow | null>(null);

const assignForm = useForm({
    plan_id: null as number | null,
});

const openAssignModal = (row: UserRow) => {
    selectedUser.value = row;
    assignForm.reset();
    assignForm.plan_id = row.plan?.id ?? null;
    assignModalOpen.value = true;
};

const submitAssign = () => {
    if (!selectedUser.value) return;
    assignForm.post(
        `/settings/subscription/user/${selectedUser.value.id}/assign`,
        {
            preserveScroll: true,
            onSuccess: () => (assignModalOpen.value = false),
        },
    );
};

const extendUser = (row: UserRow) => {
    router.post(
        `/settings/subscription/user/${row.id}/extend`,
        {},
        { preserveScroll: true },
    );
};

const revokeUser = (row: UserRow) => {
    if (!confirm(`Cabut plan untuk ${row.nama ?? row.email}?`)) return;
    router.delete(`/settings/subscription/user/${row.id}`, {
        preserveScroll: true,
    });
};

const doSearch = () => {
    router.get(
        '/settings/subscription',
        { search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatRupiah = (value: number) =>
    new Intl.NumberFormat('id-ID').format(value);

const formatDate = (value: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const activePlans = props.plans.filter((p) => p.is_active);
</script>

<template>
    <Head title="Subscription" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-6 bg-[#d3dce6] p-6">
            <!-- Plans -->
            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Subscription Plans</span>
                    <Button
                        @click="openPlanModal('add')"
                        class="bg-[#337ab7] hover:bg-[#286090]"
                        size="sm"
                    >
                        <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                        Tambah Plan
                    </Button>
                </div>

                <div class="overflow-x-auto p-4">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Name</th>
                                <th class="px-3 py-2 font-medium">Label</th>
                                <th class="px-3 py-2 text-right font-medium">Harga</th>
                                <th class="px-3 py-2 text-right font-medium">Limit/hari</th>
                                <th class="px-3 py-2 text-right font-medium">Durasi</th>
                                <th class="px-3 py-2 text-right font-medium">User Aktif</th>
                                <th class="px-3 py-2 text-center font-medium">Status</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="plan in plans"
                                :key="plan.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                            >
                                <td class="px-3 py-2 font-mono text-gray-700">{{ plan.name }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ plan.label }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">Rp {{ formatRupiah(plan.price) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">
                                    {{ plan.daily_limit === 0 ? 'Unlimited' : plan.daily_limit }}
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">
                                    {{ plan.duration_days ? plan.duration_days + ' hari' : 'Selamanya' }}
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ plan.active_users_count }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-xs font-medium',
                                            plan.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-gray-200 text-gray-500',
                                        ]"
                                    >
                                        {{ plan.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-center gap-1">
                                        <button
                                            @click="openPlanModal('edit', plan)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            title="Edit"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="plan.name !== 'free'"
                                            @click="togglePlan(plan)"
                                            class="flex h-7 w-7 items-center justify-center rounded text-white"
                                            :class="plan.is_active ? 'bg-[#d9534f] hover:bg-[#d43f3a]' : 'bg-[#5cb85c] hover:bg-[#4cae4c]'"
                                            :title="plan.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                        >
                                            <Icon :icon="plan.is_active ? 'mdi:eye-off' : 'mdi:eye'" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Users -->
            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Plan Pengguna</span>
                    <form class="flex items-center gap-2" @submit.prevent="doSearch">
                        <Input
                            v-model="search"
                            type="text"
                            placeholder="Cari email / nama / username"
                            class="h-8 w-64"
                        />
                        <Button type="submit" size="sm" class="bg-[#337ab7] hover:bg-[#286090]">
                            <Icon icon="mdi:magnify" class="h-4 w-4" />
                        </Button>
                    </form>
                </div>

                <div class="overflow-x-auto p-4">
                    <p v-if="!filters.search" class="mb-3 text-xs text-gray-500">
                        Menampilkan pengguna yang punya plan. Cari untuk menetapkan plan ke pengguna lain.
                    </p>

                    <table v-if="users.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 font-medium">Plan</th>
                                <th class="px-3 py-2 font-medium">Mulai</th>
                                <th class="px-3 py-2 font-medium">Kadaluarsa</th>
                                <th class="px-3 py-2 text-center font-medium">Status</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in users"
                                :key="row.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                            >
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-700">{{ row.nama ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ row.email ?? row.username }}</div>
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    {{ row.plan ? row.plan.label : '— (Free)' }}
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ formatDate(row.plan_started_at) }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    {{ row.plan_expires_at ? formatDate(row.plan_expires_at) : (row.plan ? 'Selamanya' : '-') }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-xs font-medium',
                                            row.state === 'active'
                                                ? 'bg-green-100 text-green-700'
                                                : row.state === 'expired'
                                                  ? 'bg-amber-100 text-amber-700'
                                                  : 'bg-gray-200 text-gray-500',
                                        ]"
                                    >
                                        {{ row.state === 'active' ? 'Aktif' : row.state === 'expired' ? 'Kadaluarsa' : 'Free' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-center gap-1">
                                        <button
                                            @click="openAssignModal(row)"
                                            class="flex h-7 items-center gap-1 rounded bg-[#337ab7] px-2 text-xs text-white hover:bg-[#286090]"
                                            title="Tetapkan plan"
                                        >
                                            <Icon icon="mdi:account-arrow-up" class="h-4 w-4" />
                                            Set
                                        </button>
                                        <button
                                            v-if="row.plan"
                                            @click="extendUser(row)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Perpanjang"
                                        >
                                            <Icon icon="mdi:calendar-plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="row.plan"
                                            @click="revokeUser(row)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            title="Cabut plan"
                                        >
                                            <Icon icon="mdi:account-cancel" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-8 text-center text-sm text-gray-500">
                        {{ filters.search ? 'Pengguna tidak ditemukan' : 'Belum ada pengguna dengan plan' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Plan modal -->
        <Dialog :open="planModalOpen" @update:open="planModalOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ planModalType === 'add' ? 'Tambah Plan' : 'Edit Plan' }}</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submitPlan" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Name (kode) <span class="text-red-500">*</span>
                            </label>
                            <Input v-model="planForm.name" type="text" placeholder="donatur_d" required />
                            <p v-if="planForm.errors.name" class="mt-1 text-sm text-red-600">{{ planForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Label <span class="text-red-500">*</span>
                            </label>
                            <Input v-model="planForm.label" type="text" placeholder="Donatur D" required />
                            <p v-if="planForm.errors.label" class="mt-1 text-sm text-red-600">{{ planForm.errors.label }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Harga (Rp)</label>
                            <Input v-model.number="planForm.price" type="number" min="0" />
                            <p v-if="planForm.errors.price" class="mt-1 text-sm text-red-600">{{ planForm.errors.price }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Limit/hari</label>
                            <Input v-model.number="planForm.daily_limit" type="number" min="0" />
                            <p class="mt-1 text-xs text-gray-500">0 = unlimited</p>
                            <p v-if="planForm.errors.daily_limit" class="mt-1 text-sm text-red-600">{{ planForm.errors.daily_limit }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Durasi (hari)</label>
                            <Input v-model.number="planForm.duration_days" type="number" min="1" placeholder="kosong = selamanya" />
                            <p v-if="planForm.errors.duration_days" class="mt-1 text-sm text-red-600">{{ planForm.errors.duration_days }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 items-center gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan (seq)</label>
                            <Input v-model.number="planForm.seq" type="number" min="0" />
                        </div>
                        <label class="mt-5 flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="planForm.is_active" type="checkbox" class="h-4 w-4" />
                            Aktif
                        </label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="planModalOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="planForm.processing" class="bg-[#5cb85c] hover:bg-[#4cae4c]">
                            {{ planModalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Assign modal -->
        <Dialog :open="assignModalOpen" @update:open="assignModalOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tetapkan Plan</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submitAssign" class="space-y-4">
                    <div class="rounded bg-gray-50 p-3 text-sm">
                        <div class="font-medium text-gray-700">{{ selectedUser?.nama ?? '-' }}</div>
                        <div class="text-xs text-gray-500">{{ selectedUser?.email ?? selectedUser?.username }}</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Plan <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model.number="assignForm.plan_id"
                            required
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                        >
                            <option :value="null" disabled>Pilih plan</option>
                            <option v-for="plan in activePlans" :key="plan.id" :value="plan.id">
                                {{ plan.label }} — {{ plan.daily_limit === 0 ? 'Unlimited' : plan.daily_limit + '/hari' }}
                                {{ plan.duration_days ? '(' + plan.duration_days + ' hari)' : '(selamanya)' }}
                            </option>
                        </select>
                        <p v-if="assignForm.errors.plan_id" class="mt-1 text-sm text-red-600">{{ assignForm.errors.plan_id }}</p>
                        <p class="mt-2 text-xs text-gray-500">
                            Tanggal mulai = sekarang, kadaluarsa dihitung dari durasi plan. Lakukan setelah pembayaran via WhatsApp terverifikasi.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="assignModalOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="assignForm.processing" class="bg-[#5cb85c] hover:bg-[#4cae4c]">
                            Tetapkan
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

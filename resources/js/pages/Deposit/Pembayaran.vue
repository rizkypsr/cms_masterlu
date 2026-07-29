<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface InfoRow {
    id: number;
    wa_number: string;
    wa_link: string;
    description: string;
    is_active: boolean;
    seq: number;
    updated_by: string | null;
    updated_at: string | null;
}

const props = defineProps<{
    infos: InfoRow[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const active = computed(() => props.infos.find((i) => i.is_active) ?? null);

const modalOpen = ref(false);
const editing = ref<InfoRow | null>(null);

const form = useForm({
    wa_number: '',
    description: '',
    seq: 0,
    is_active: true,
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.seq = props.infos.length;
    form.is_active = props.infos.length === 0;
    modalOpen.value = true;
};

const openEdit = (row: InfoRow) => {
    editing.value = row;
    form.reset();
    form.clearErrors();
    form.wa_number = row.wa_number;
    form.description = row.description;
    form.seq = row.seq;
    form.is_active = row.is_active;
    modalOpen.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(`/deposit/pembayaran/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (modalOpen.value = false),
        });
        return;
    }
    form.post('/deposit/pembayaran', {
        preserveScroll: true,
        onSuccess: () => (modalOpen.value = false),
    });
};

const activate = (row: InfoRow) => {
    router.post(`/deposit/pembayaran/${row.id}/activate`, {}, { preserveScroll: true });
};

const destroy = (row: InfoRow) => {
    if (!confirm('Hapus format pembayaran ini?')) return;
    router.delete(`/deposit/pembayaran/${row.id}`, { preserveScroll: true });
};

// Mirrors the server: digits only, leading 0 becomes 62.
const waPreview = computed(() => {
    const digits = form.wa_number.replace(/\D+/g, '');
    if (!digits) return null;
    return 'https://wa.me/' + (digits.startsWith('0') ? '62' + digits.slice(1) : digits);
});

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
</script>

<template>
    <Head title="Format Pembayaran" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl text-gray-700">Saldo Deposit — Format Pembayaran</h1>
                    <p class="text-xs text-gray-500">
                        Nomor WhatsApp admin dan instruksi transfer yang ditampilkan ke pengguna saat mereka mengajukan
                        topup.
                    </p>
                </div>
                <div class="flex gap-2 text-sm">
                    <Link href="/deposit" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Antrean Topup
                    </Link>
                    <Link href="/deposit/saldo" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Saldo Pengguna
                    </Link>
                    <Link href="/deposit/tarif" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Tarif AI
                    </Link>
                </div>
            </div>

            <div
                v-if="!active"
                class="flex items-start gap-2 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <Icon icon="mdi:alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <strong>Tidak ada format aktif.</strong> Pengguna tidak akan melihat instruksi transfer apa pun saat
                    mengajukan topup. Aktifkan salah satu format di bawah.
                </div>
            </div>

            <!-- Live preview of what the user sees -->
            <div v-if="active" class="overflow-hidden rounded bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Yang dilihat pengguna</span>
                    <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktif</span>
                </div>
                <div class="space-y-3 p-4">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <Icon icon="mdi:whatsapp" class="h-5 w-5 text-[#25D366]" />
                        <span class="font-medium text-gray-700">{{ active.wa_number }}</span>
                        <a
                            :href="active.wa_link"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs text-[#337ab7] hover:underline"
                        >
                            {{ active.wa_link }}
                        </a>
                    </div>
                    <div
                        class="prose prose-sm max-w-none text-sm text-gray-700 [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                        v-html="active.description"
                    />
                </div>
            </div>

            <!-- All versions -->
            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div>
                        <span class="text-sm font-medium text-gray-600">Semua Format</span>
                        <span class="ml-2 text-xs text-gray-400">satu yang aktif disajikan; sisanya cadangan</span>
                    </div>
                    <Button size="sm" class="bg-[#337ab7] hover:bg-[#286090]" @click="openCreate">
                        <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                        Tambah Format
                    </Button>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="infos.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Nomor WA</th>
                                <th class="px-3 py-2 font-medium">Instruksi</th>
                                <th class="px-3 py-2 text-right font-medium">Urutan</th>
                                <th class="px-3 py-2 text-center font-medium">Status</th>
                                <th class="px-3 py-2 font-medium">Diubah</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in infos"
                                :key="row.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                                :class="{ 'bg-green-50/40': row.is_active }"
                            >
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-700">{{ row.wa_number }}</div>
                                    <a
                                        :href="row.wa_link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-[11px] text-gray-400 hover:text-[#337ab7] hover:underline"
                                    >
                                        {{ row.wa_link }}
                                    </a>
                                </td>
                                <td class="max-w-md px-3 py-2">
                                    <div
                                        class="line-clamp-2 text-xs text-gray-600 [&_ol]:list-decimal [&_ol]:pl-4 [&_ul]:list-disc [&_ul]:pl-4"
                                        v-html="row.description"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.seq }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'"
                                    >
                                        {{ row.is_active ? 'Aktif' : 'Cadangan' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">
                                    {{ formatDate(row.updated_at) }}
                                    <div v-if="row.updated_by" class="text-[11px] text-gray-400">
                                        oleh {{ row.updated_by }}
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-center gap-1">
                                        <button
                                            v-if="!row.is_active"
                                            class="flex h-7 items-center gap-1 rounded bg-[#5cb85c] px-2 text-xs text-white hover:bg-[#4cae4c]"
                                            title="Jadikan format yang ditampilkan"
                                            @click="activate(row)"
                                        >
                                            <Icon icon="mdi:check" class="h-4 w-4" />
                                            Aktifkan
                                        </button>
                                        <button
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            title="Edit"
                                            @click="openEdit(row)"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="!row.is_active"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            title="Hapus"
                                            @click="destroy(row)"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-10 text-center text-sm text-gray-500">
                        Belum ada format pembayaran.
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / edit -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit Format Pembayaran' : 'Tambah Format Pembayaran' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Nomor WhatsApp Admin <span class="text-red-500">*</span>
                        </label>
                        <Input v-model="form.wa_number" type="text" placeholder="+62 812-8197-7739" />
                        <p class="mt-1 text-xs text-gray-500">
                            Format bebas — <code>+62 812-8197-7739</code> atau <code>08128197739</code> sama saja.
                            Angkanya diambil untuk membuat link wa.me.
                        </p>
                        <p v-if="waPreview" class="mt-1 text-xs text-[#337ab7]">Link: {{ waPreview }}</p>
                        <p v-if="form.errors.wa_number" class="mt-1 text-sm text-red-600">{{ form.errors.wa_number }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Instruksi Pembayaran <span class="text-red-500">*</span>
                        </label>
                        <TiptapEditor v-model="form.description" height="220px" />
                        <p class="mt-1 text-xs text-gray-500">
                            Nama bank, nomor rekening, atas nama, dan langkah-langkahnya. Ditampilkan langsung ke
                            pengguna.
                        </p>
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 items-start gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                            <Input v-model.number="form.seq" type="number" min="0" />
                        </div>
                        <label class="mt-6 flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
                            Tampilkan format ini ke pengguna
                        </label>
                    </div>

                    <p class="rounded bg-gray-50 p-3 text-xs text-gray-600">
                        Hanya satu format yang disajikan. Mencentang di atas otomatis menonaktifkan format lain; yang
                        lama tetap tersimpan sebagai cadangan.
                    </p>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-[#5cb85c] hover:bg-[#4cae4c]">
                            {{ editing ? 'Simpan Perubahan' : 'Simpan' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

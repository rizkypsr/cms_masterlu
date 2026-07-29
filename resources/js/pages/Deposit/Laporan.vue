<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface MonthRow {
    month: string;
    in_rp: number;
    spent_rp: number;
    questions: number;
}

interface DriftRow {
    id: number;
    nama: string | null;
    email: string | null;
    balance_rp: number;
    ledger_rp: number;
}

const props = defineProps<{
    months: MonthRow[];
    shadow_rp: number;
    drift: DriftRow[];
    freeQuota: { id: number; daily_limit: number } | null;
    totals: { balance_rp: number; pending_topups: number };
}>();

const page = usePage();
const user = page.props.auth?.user;

const quotaForm = useForm({
    daily_limit: props.freeQuota?.daily_limit ?? 1,
});

const submitQuota = () => {
    quotaForm.post('/deposit/free-quota', { preserveScroll: true });
};

const formatRp = (value: number) =>
    'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);

const monthLabel = (month: string) => {
    const [y, m] = month.split('-');
    return new Date(Number(y), Number(m) - 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
};
</script>

<template>
    <Head title="Laporan Deposit" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl text-gray-700">Saldo Deposit — Laporan</h1>
                    <p class="text-xs text-gray-500">Uang masuk vs biaya terpakai, kesehatan data, dan jatah gratis harian.</p>
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

            <!-- Summary tiles -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Total saldo mengendap</div>
                    <div class="mt-1 text-xl font-medium text-gray-700">{{ formatRp(totals.balance_rp) }}</div>
                    <div class="mt-1 text-[11px] text-gray-400">Kewajiban, bukan pemasukan — uang titipan user.</div>
                </div>
                <div class="rounded bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Menunggu verifikasi</div>
                    <div class="mt-1 text-xl font-medium" :class="totals.pending_topups ? 'text-[#f0ad4e]' : 'text-gray-700'">
                        {{ totals.pending_topups }}
                    </div>
                    <Link href="/deposit" class="mt-1 inline-block text-[11px] text-[#337ab7] hover:underline">
                        Buka antrean topup
                    </Link>
                </div>
                <div class="rounded bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Biaya mode bayangan</div>
                    <div class="mt-1 text-xl font-medium text-gray-700">{{ formatRp(shadow_rp) }}</div>
                    <div class="mt-1 text-[11px] text-gray-400">
                        Yang <em>seharusnya</em> tertagih bila DEPOSIT_ENFORCE aktif.
                    </div>
                </div>
            </div>

            <!-- Reconciliation -->
            <div
                v-if="drift.length"
                class="overflow-hidden rounded border border-red-200 bg-white shadow"
            >
                <div class="flex items-center gap-2 border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <Icon icon="mdi:alert" class="h-4 w-4 shrink-0" />
                    <strong>Saldo tidak cocok dengan buku besar pada {{ drift.length }} akun</strong>
                </div>
                <div class="p-4">
                    <p class="mb-3 text-xs text-gray-600">
                        Hampir selalu karena saldo pernah diubah lewat UPDATE manual tanpa baris buku besar.
                        <strong>Buku besar yang benar.</strong> Sistem sengaja tidak memperbaikinya otomatis — telusuri
                        penyebabnya dulu, baru koreksi lewat Koreksi Saldo.
                    </p>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Pengguna</th>
                                <th class="px-3 py-2 text-right font-medium">Saldo (cache)</th>
                                <th class="px-3 py-2 text-right font-medium">Buku besar</th>
                                <th class="px-3 py-2 text-right font-medium">Selisih</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in drift" :key="row.id" class="border-b border-gray-100 text-sm">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-700">{{ row.nama ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ row.email }}</div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ formatRp(row.balance_rp) }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-700">{{ formatRp(row.ledger_rp) }}</td>
                                <td class="px-3 py-2 text-right font-medium text-red-600">
                                    {{ formatRp(row.balance_rp - row.ledger_rp) }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Link :href="`/deposit/pengguna/${row.id}`" class="text-xs text-[#337ab7] hover:underline">
                                        Telusuri
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else class="flex items-center gap-2 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
                <Icon icon="mdi:check-circle-outline" class="h-4 w-4 shrink-0" />
                Rekonsiliasi bersih — saldo semua akun cocok dengan buku besar.
            </div>

            <!-- Monthly report -->
            <div class="overflow-hidden rounded bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Laporan Bulanan</span>
                    <span class="ml-2 text-xs text-gray-400">maks 24 bulan terakhir</span>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="months.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Bulan</th>
                                <th class="px-3 py-2 text-right font-medium">Masuk (topup + bonus)</th>
                                <th class="px-3 py-2 text-right font-medium">Terpakai</th>
                                <th class="px-3 py-2 text-right font-medium">Pertanyaan berbayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in months" :key="row.month" class="border-b border-gray-100 text-sm hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-700">{{ monthLabel(row.month) }}</td>
                                <td class="px-3 py-2 text-right text-green-700">{{ formatRp(row.in_rp) }}</td>
                                <td class="px-3 py-2 text-right text-gray-700">{{ formatRp(row.spent_rp) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ row.questions }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-8 text-center text-sm text-gray-500">Belum ada mutasi.</div>

                    <div class="mt-3 rounded bg-gray-50 p-3 text-xs text-gray-600">
                        <strong>Cara membaca:</strong> bandingkan <em>Terpakai</em> dengan tagihan Google + OpenAI bulan
                        yang sama. User menagihkan persis harga penyedia × kurs, jadi keduanya seharusnya berdekatan —
                        selisihnya tinggal biaya jatah gratis yang memang ditanggung dan tidak pernah muncul di buku
                        besar, plus gerakan kurs sejak tarif terakhir disetel. Bila <em>Terpakai</em> jauh di bawah
                        tagihan provider, kurs di baris tarif sudah ketinggalan — perbarui di halaman Tarif AI.
                        <br /><br />
                        <strong>Masuk bukan pemasukan.</strong> Itu kewajiban — uang titipan yang baru menjadi pemasukan
                        saat dipakai bertanya. Catat terpisah di laporan keuangan.
                    </div>
                </div>
            </div>

            <!-- Free quota -->
            <div class="overflow-hidden rounded bg-white shadow">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Jatah Gratis Harian</span>
                </div>
                <div class="p-4">
                    <div v-if="freeQuota" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Pertanyaan gratis per hari</label>
                            <Input v-model.number="quotaForm.daily_limit" type="number" min="0" max="100" class="h-9 w-32" />
                            <p v-if="quotaForm.errors.daily_limit" class="mt-1 text-sm text-red-600">
                                {{ quotaForm.errors.daily_limit }}
                            </p>
                        </div>
                        <Button
                            :disabled="quotaForm.processing"
                            class="bg-[#337ab7] hover:bg-[#286090]"
                            @click="submitQuota"
                        >
                            Simpan
                        </Button>
                    </div>

                    <div v-else class="text-sm text-red-600">
                        Baris plan <code>free</code> tidak ditemukan — API bergantung padanya.
                    </div>

                    <p class="mt-3 rounded bg-amber-50 p-3 text-xs text-amber-700">
                        <Icon icon="mdi:alert-outline" class="mr-1 inline h-3.5 w-3.5" />
                        Pertimbangkan sebelum menaikkan: jatah gratis adalah biaya murni yang skalanya ikut jumlah
                        <strong>akun</strong>, bukan jumlah pembeli. Menaikkan dari 1 ke 2 melipatgandakan pos pengeluaran
                        terbesar sistem ini. Berlaku paling lambat 1 menit.
                    </p>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

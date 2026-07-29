<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface RateRow {
    id: number;
    model: string;
    input_usd_per_1m: number;
    cached_input_usd_per_1m: number;
    output_usd_per_1m: number;
    embed_usd_per_1m: number;
    usd_to_idr: number;
    input_mrp_per_1k: number;
    cached_input_mrp_per_1k: number;
    output_mrp_per_1k: number;
    embed_mrp_per_1k: number;
    is_free_tier: boolean;
    is_active: boolean;
    note: string | null;
    effective_from: string | null;
}

const props = defineProps<{
    rates: RateRow[];
    models: string[];
    lastKurs: number;
}>();

const page = usePage();
const user = page.props.auth?.user;

const modalOpen = ref(false);

const form = useForm({
    model: '',
    input_usd_per_1m: 0.1,
    cached_input_usd_per_1m: 0.025,
    output_usd_per_1m: 0.4,
    embed_usd_per_1m: 0.02,
    usd_to_idr: props.lastKurs,
    is_free_tier: false,
    note: '',
});

const openModal = (prefill?: RateRow) => {
    form.reset();
    form.clearErrors();
    form.usd_to_idr = props.lastKurs;
    if (prefill) {
        form.model = prefill.model;
        form.input_usd_per_1m = prefill.input_usd_per_1m;
        form.cached_input_usd_per_1m = prefill.cached_input_usd_per_1m;
        form.output_usd_per_1m = prefill.output_usd_per_1m;
        form.embed_usd_per_1m = prefill.embed_usd_per_1m;
        form.usd_to_idr = prefill.usd_to_idr;
        form.is_free_tier = prefill.is_free_tier;
    }
    modalOpen.value = true;
};

const submit = () => {
    form.post('/deposit/tarif', {
        preserveScroll: true,
        onSuccess: () => (modalOpen.value = false),
    });
};

// Gemini charges cached input at 25% of the normal input price.
const cachedSuggestion = computed(() => Number((form.input_usd_per_1m * 0.25).toFixed(6)));

/** USD per 1M tokens -> milli-rupiah per 1.000 tokens, rounded up like the API bills. */
const toMrpPer1k = (usdPer1m: number) => Math.ceil((usdPer1m || 0) * (form.usd_to_idr || 0));

/** 1800 mrp/1k -> "Rp1,80" */
const perThousand = (mrp: number) =>
    'Rp' + (mrp / 1000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// A realistic question, so the abstract per-million numbers land as a real charge.
const SAMPLE_INPUT_TOKENS = 12000;
const SAMPLE_OUTPUT_TOKENS = 500;
// A normal question should land near this; wildly above means the unit is wrong.
const SANITY_CEILING_RP = 100;

const sampleCostRp = computed(() => {
    const input = (SAMPLE_INPUT_TOKENS / 1000) * toMrpPer1k(form.input_usd_per_1m);
    const output = (SAMPLE_OUTPUT_TOKENS / 1000) * toMrpPer1k(form.output_usd_per_1m);
    return (input + output) / 1000;
});

// The one mistake this form exists to catch: pasting a per-1.000-token price
// into a per-1.000.000-token field, which overcharges by ~1000x.
const looksMistyped = computed(() => sampleCostRp.value > SANITY_CEILING_RP);

const formatUsd = (value: number) => '$' + value.toLocaleString('en-US', { maximumFractionDigits: 6 });

const formatMrp = (mrp: number) => 'Rp' + (mrp / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 });

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
    <Head title="Tarif AI" />

    <DashboardLayout :user="user">
        <div class="min-h-[calc(100vh-48px)] space-y-4 bg-[#d3dce6] p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl text-gray-700">Saldo Deposit — Tarif AI</h1>
                    <p class="text-xs text-gray-500">
                        Menentukan berapa yang dipotong dari saldo per token. Insert-only — tarif lama tidak pernah diubah.
                    </p>
                </div>
                <div class="flex gap-2 text-sm">
                    <Link href="/deposit" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Antrean Topup
                    </Link>
                    <Link href="/deposit/saldo" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Saldo Pengguna
                    </Link>
                    <Link href="/deposit/laporan" class="rounded bg-white px-3 py-1.5 text-gray-600 shadow-sm hover:bg-gray-50">
                        Laporan
                    </Link>
                </div>
            </div>

            <div class="flex items-start gap-2 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                <Icon icon="mdi:information-outline" class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    Nama model harus <strong>sama persis</strong> dengan <code>GOOGLE_CHAT_MODEL</code> yang berjalan.
                    Kalau tidak ada baris aktif yang cocok, pertanyaan <strong>tidak ditagih sama sekali</strong> dan
                    muncul log <code>billing_rate_missing</code>. Tarif baru berlaku paling lambat 5 menit (ada cache).
                </div>
            </div>

            <div class="overflow-hidden rounded bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Riwayat Tarif</span>
                    <Button size="sm" class="bg-[#337ab7] hover:bg-[#286090]" @click="openModal()">
                        <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                        Tarif Baru
                    </Button>
                </div>

                <div class="overflow-x-auto p-4">
                    <table v-if="rates.length" class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-sm text-gray-700">
                                <th class="px-3 py-2 font-medium">Model</th>
                                <th class="px-3 py-2 text-right font-medium">Kurs</th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Masuk
                                    <div class="font-normal text-[10px] text-gray-400">USD/1jt → Rp/1k</div>
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Cache
                                    <div class="font-normal text-[10px] text-gray-400">USD/1jt → Rp/1k</div>
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Jawaban
                                    <div class="font-normal text-[10px] text-gray-400">USD/1jt → Rp/1k</div>
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Embed
                                    <div class="font-normal text-[10px] text-gray-400">USD/1jt → Rp/1k</div>
                                </th>
                                <th class="px-3 py-2 text-center font-medium">Status</th>
                                <th class="px-3 py-2 font-medium">Berlaku</th>
                                <th class="px-3 py-2 font-medium">Catatan</th>
                                <th class="px-3 py-2 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="rate in rates"
                                :key="rate.id"
                                class="border-b border-gray-100 text-sm hover:bg-gray-50"
                                :class="{ 'opacity-50': !rate.is_active }"
                            >
                                <td class="px-3 py-2 font-mono text-xs text-gray-700">
                                    {{ rate.model }}
                                    <span
                                        v-if="rate.is_free_tier"
                                        class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 font-sans text-[10px] text-blue-700"
                                        title="Free tier — saldo user tidak dipotong"
                                    >
                                        gratis
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right text-xs text-gray-500">
                                    Rp{{ rate.usd_to_idr.toLocaleString('id-ID') }}
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">
                                    <div class="text-[11px] text-gray-400">{{ formatUsd(rate.input_usd_per_1m) }}</div>
                                    <div class="font-medium">{{ formatMrp(rate.input_mrp_per_1k) }}</div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">
                                    <div class="text-[11px] text-gray-400">{{ formatUsd(rate.cached_input_usd_per_1m) }}</div>
                                    <div class="font-medium">{{ formatMrp(rate.cached_input_mrp_per_1k) }}</div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">
                                    <div class="text-[11px] text-gray-400">{{ formatUsd(rate.output_usd_per_1m) }}</div>
                                    <div class="font-medium">{{ formatMrp(rate.output_mrp_per_1k) }}</div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">
                                    <div class="text-[11px] text-gray-400">{{ formatUsd(rate.embed_usd_per_1m) }}</div>
                                    <div class="font-medium">{{ formatMrp(rate.embed_mrp_per_1k) }}</div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="rate.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'"
                                    >
                                        {{ rate.is_active ? 'Aktif' : 'Pensiun' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ formatDate(rate.effective_from) }}</td>
                                <td class="max-w-xs px-3 py-2 text-xs text-gray-500">
                                    <div class="truncate" :title="rate.note ?? ''">{{ rate.note ?? '-' }}</div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        class="flex h-7 items-center gap-1 rounded bg-[#f0ad4e] px-2 text-xs text-white hover:bg-[#eea236]"
                                        title="Buat tarif baru berdasarkan baris ini"
                                        @click="openModal(rate)"
                                    >
                                        <Icon icon="mdi:content-copy" class="h-4 w-4" />
                                        Ubah
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-10 text-center text-sm text-gray-500">Belum ada tarif.</div>
                </div>
            </div>
        </div>

        <!-- New rate -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <!-- Tall form (5 price fields + preview + warnings): cap it and scroll. -->
            <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Tarif Baru</DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <p class="rounded bg-gray-50 p-3 text-xs text-gray-600">
                        Baris tarif aktif untuk model yang sama otomatis dipensiunkan. Tagihan lama tidak berubah — baris
                        buku besar lama tetap menunjuk tarif yang menagihnya.
                    </p>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Model <span class="text-red-500">*</span>
                        </label>
                        <Input v-model="form.model" type="text" list="known-models" placeholder="gemini-flash-lite-latest" />
                        <datalist id="known-models">
                            <option v-for="m in models" :key="m" :value="m" />
                        </datalist>
                        <p v-if="form.errors.model" class="mt-1 text-sm text-red-600">{{ form.errors.model }}</p>
                    </div>

                    <p class="rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600">
                        Isi <strong>persis seperti yang tertulis di halaman harga penyedia</strong>: USD per 1 juta token.
                        Contoh Gemini Flash Lite: input <code>0.10</code>, output <code>0.40</code>. Konversi ke rupiah
                        dihitung otomatis: <code>USD/1jt × kurs</code>.
                    </p>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Kurs USD → IDR <span class="text-red-500">*</span>
                        </label>
                        <Input v-model.number="form.usd_to_idr" type="number" min="1" step="100" class="w-40" />
                        <p class="mt-1 text-[11px] text-gray-500">
                            Kurs disimpan di baris tarif ini, bukan setelan global — supaya tagihan lama tetap bisa
                            dihitung ulang dengan angka yang persis dipakai saat itu.
                        </p>
                        <p v-if="form.errors.usd_to_idr" class="mt-1 text-xs text-red-600">{{ form.errors.usd_to_idr }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Token masuk — USD / 1jt</label>
                            <Input v-model.number="form.input_usd_per_1m" type="number" min="0" step="0.000001" />
                            <p class="mt-1 text-[11px] text-gray-500">
                                → <strong>{{ perThousand(toMrpPer1k(form.input_usd_per_1m)) }}</strong> / 1.000 token<br />
                                Pertanyaan user + potongan buku/subtitle yang dikirim ke AI. Biasanya paling besar.
                            </p>
                            <p v-if="form.errors.input_usd_per_1m" class="mt-1 text-xs text-red-600">
                                {{ form.errors.input_usd_per_1m }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Token jawaban — USD / 1jt</label>
                            <Input v-model.number="form.output_usd_per_1m" type="number" min="0" step="0.000001" />
                            <p class="mt-1 text-[11px] text-gray-500">
                                → <strong>{{ perThousand(toMrpPer1k(form.output_usd_per_1m)) }}</strong> / 1.000 token<br />
                                Teks jawaban yang dihasilkan AI. Biasanya 4× harga token masuk.
                            </p>
                            <p v-if="form.errors.output_usd_per_1m" class="mt-1 text-xs text-red-600">
                                {{ form.errors.output_usd_per_1m }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Token cache — USD / 1jt</label>
                            <Input v-model.number="form.cached_input_usd_per_1m" type="number" min="0" step="0.000001" />
                            <p class="mt-1 text-[11px] text-gray-500">
                                → <strong>{{ perThousand(toMrpPer1k(form.cached_input_usd_per_1m)) }}</strong> / 1.000 token<br />
                                Token masuk yang sudah pernah dikirim dan disimpan cache AI.
                            </p>
                            <button
                                v-if="form.cached_input_usd_per_1m !== cachedSuggestion"
                                type="button"
                                class="mt-1 text-[11px] text-[#337ab7] hover:underline"
                                @click="form.cached_input_usd_per_1m = cachedSuggestion"
                            >
                                Pakai 25% dari harga token masuk ({{ cachedSuggestion }})
                            </button>
                            <p v-if="form.errors.cached_input_usd_per_1m" class="mt-1 text-xs text-red-600">
                                {{ form.errors.cached_input_usd_per_1m }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Embed — USD / 1jt</label>
                            <Input v-model.number="form.embed_usd_per_1m" type="number" min="0" step="0.000001" />
                            <p class="mt-1 text-[11px] text-gray-500">
                                → <strong>{{ perThousand(toMrpPer1k(form.embed_usd_per_1m)) }}</strong> / 1.000 token<br />
                                Mengubah pertanyaan jadi vektor untuk mencari konten relevan. Sangat kecil.
                            </p>
                        </div>
                    </div>

                    <!-- Pratinjau konversi: pencegah kesalahan paling penting. -->
                    <div
                        class="rounded border p-3 text-xs"
                        :class="looksMistyped ? 'border-red-300 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'"
                    >
                        <div class="mb-1 font-medium">
                            <Icon :icon="looksMistyped ? 'mdi:alert' : 'mdi:calculator'" class="mr-1 inline h-3.5 w-3.5" />
                            Pratinjau tagihan
                        </div>
                        <div>{{ perThousand(toMrpPer1k(form.input_usd_per_1m)) }} per 1.000 token masuk</div>
                        <div>{{ perThousand(toMrpPer1k(form.output_usd_per_1m)) }} per 1.000 token jawaban</div>
                        <div class="mt-1">
                            Perkiraan 1 pertanyaan (±{{ SAMPLE_INPUT_TOKENS.toLocaleString('id-ID') }} token masuk,
                            ±{{ SAMPLE_OUTPUT_TOKENS }} keluar):
                            <strong>Rp{{ sampleCostRp.toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}</strong>
                        </div>
                        <div v-if="looksMistyped" class="mt-2 font-medium">
                            Patokan kewajaran satu pertanyaan ≈ Rp25. Angka sebesar ini hampir pasti berarti satuan
                            harganya tertukar — kolom di atas <strong>per 1 JUTA token</strong>, bukan per 1.000.
                        </div>
                        <div v-else-if="form.is_free_tier" class="mt-2">
                            Model ini free tier: saldo user <strong>tidak dipotong</strong>. Angka di atas tetap dicatat
                            di buku besar sebagai biaya seandainya berbayar.
                        </div>
                    </div>

                    <!-- Wrong-model-price trap: silently overbills, never errors. -->
                    <div class="rounded border border-amber-200 bg-amber-50 p-3 text-[11px] text-amber-800">
                        <Icon icon="mdi:alert-outline" class="mr-1 inline h-3.5 w-3.5" />
                        Cocokkan nama model di halaman harga penyedia dengan kolom <strong>Model</strong> di atas.
                        Harga model yang namanya mirip tidak memicu error apa pun — hanya tagihan yang meleset berlipat.
                        Contoh: Gemini 2.5 Flash $0,30/$2,50 vs Flash-Lite $0,10/$0,40 — pernah tertukar dan menagih
                        3,6× biaya sebenarnya.
                    </div>

                    <label class="flex items-start gap-2 rounded border border-gray-200 p-3 text-sm text-gray-700">
                        <input v-model="form.is_free_tier" type="checkbox" class="mt-0.5 h-4 w-4" />
                        <span>
                            Model ini <strong>gratis</strong> di penyedia (free tier)
                            <span class="mt-1 block text-xs text-gray-500">
                                Saldo user tidak dipotong, saldo kosong tetap dilayani, dan jatah gratis harian tidak
                                terpakai. Harga USD tetap diisi apa adanya — jangan dinolkan, supaya sistem tetap
                                mencatat berapa biayanya seandainya berbayar.
                            </span>
                        </span>
                    </label>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Catatan <span class="text-red-500">*</span>
                        </label>
                        <Input v-model="form.note" type="text" placeholder="penyesuaian kurs Rp16.500/USD, Agustus 2026" />
                        <p class="mt-1 text-xs text-gray-500">
                            Wajib — ini satu-satunya jejak alasan perubahan, dan riwayat tarif tidak pernah dihapus.
                        </p>
                        <p v-if="form.errors.note" class="mt-1 text-sm text-red-600">{{ form.errors.note }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-[#5cb85c] hover:bg-[#4cae4c]">
                            Simpan Tarif
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

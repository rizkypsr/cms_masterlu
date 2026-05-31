<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Database, Download, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { download as downloadBackup } from '@/routes/database-backup';

interface DatabaseInfo {
    connection: string;
    name: string;
    driver: string;
}

defineProps<{
    database: DatabaseInfo;
}>();

const page = usePage();
const user = page.props.auth?.user;

type BackupStatus = 'idle' | 'preparing' | 'downloading' | 'finishing' | 'done' | 'error';

const status = ref<BackupStatus>('idle');
const loadedBytes = ref(0);
const totalBytes = ref(0);
const errorMessage = ref<string | null>(null);

const percentage = computed(() => {
    if (status.value === 'done') {
        return 100;
    }

    if (totalBytes.value <= 0) {
        return 0;
    }

    const value = (loadedBytes.value / totalBytes.value) * 100;

    return Math.min(100, Math.max(0, Math.round(value)));
});

const isBusy = computed(
    () => status.value !== 'idle' && status.value !== 'done' && status.value !== 'error',
);

const isIndeterminate = computed(
    () => status.value === 'preparing' || (status.value === 'downloading' && totalBytes.value <= 0),
);

const statusLabel = computed(() => {
    switch (status.value) {
        case 'preparing':
            return 'Membuat backup di server...';
        case 'downloading':
            return totalBytes.value > 0
                ? `Mengunduh ${formatBytes(loadedBytes.value)} / ${formatBytes(totalBytes.value)}`
                : `Mengunduh ${formatBytes(loadedBytes.value)}...`;
        case 'finishing':
            return 'Menyiapkan file...';
        case 'done':
            return 'Backup selesai diunduh.';
        case 'error':
            return errorMessage.value ?? 'Terjadi kesalahan saat membuat backup.';
        default:
            return '';
    }
});

function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(value >= 100 || unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function extractFilename(disposition: string | null, fallback: string): string {
    if (!disposition) {
        return fallback;
    }

    const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
    if (utf8Match?.[1]) {
        try {
            return decodeURIComponent(utf8Match[1]);
        } catch {
            // fall through
        }
    }

    const plainMatch = /filename="?([^";]+)"?/i.exec(disposition);
    if (plainMatch?.[1]) {
        return plainMatch[1];
    }

    return fallback;
}

function triggerSave(blob: Blob, filename: string): void {
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(objectUrl);
}

async function handleBackup(): Promise<void> {
    if (isBusy.value) {
        return;
    }

    status.value = 'preparing';
    loadedBytes.value = 0;
    totalBytes.value = 0;
    errorMessage.value = null;

    try {
        const response = await fetch(downloadBackup().url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/sql',
            },
        });

        if (!response.ok) {
            throw new Error(`Server merespon dengan status ${response.status}`);
        }

        const contentLength = response.headers.get('Content-Length');
        totalBytes.value = contentLength ? Number.parseInt(contentLength, 10) : 0;

        const filename = extractFilename(
            response.headers.get('Content-Disposition'),
            `database-backup-${Date.now()}.sql`,
        );

        status.value = 'downloading';

        const reader = response.body?.getReader();

        if (!reader) {
            const blob = await response.blob();
            loadedBytes.value = blob.size;
            totalBytes.value = blob.size;
            status.value = 'finishing';
            triggerSave(blob, filename);
            status.value = 'done';
            return;
        }

        const chunks: Uint8Array[] = [];

        while (true) {
            const { done, value } = await reader.read();

            if (done) {
                break;
            }

            chunks.push(value);
            loadedBytes.value += value.byteLength;
        }

        status.value = 'finishing';

        const blob = new Blob(chunks, { type: 'application/sql' });
        triggerSave(blob, filename);

        if (totalBytes.value <= 0) {
            totalBytes.value = blob.size;
        }

        status.value = 'done';
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Terjadi kesalahan saat membuat backup.';
        status.value = 'error';
    }
}

function reset(): void {
    status.value = 'idle';
    loadedBytes.value = 0;
    totalBytes.value = 0;
    errorMessage.value = null;
}
</script>

<template>
    <Head title="Backup Database" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-600">Backup Database</span>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6">
                    <div class="max-w-2xl space-y-6">
                        <p class="text-sm text-gray-600">
                            Buat cadangan seluruh database aplikasi dan unduh
                            sebagai file <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">.sql</code>
                            yang dapat dipulihkan kembali bila diperlukan.
                        </p>

                        <div class="rounded border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-start gap-3">
                                <Database class="mt-0.5 h-5 w-5 text-gray-500" />
                                <div class="space-y-2 text-sm">
                                    <p class="font-medium text-gray-700">Detail koneksi</p>
                                    <dl class="grid grid-cols-[120px_1fr] gap-y-1 text-gray-600">
                                        <dt>Connection</dt>
                                        <dd class="text-gray-900">{{ database.connection }}</dd>
                                        <dt>Driver</dt>
                                        <dd class="text-gray-900">{{ database.driver }}</dd>
                                        <dt>Database</dt>
                                        <dd class="text-gray-900">{{ database.name }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="rounded border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                            <p>
                                Database besar mungkin membutuhkan waktu beberapa
                                saat. Jangan menutup tab sampai unduhan selesai.
                            </p>
                        </div>

                        <!-- Progress -->
                        <div
                            v-if="status !== 'idle'"
                            class="space-y-2"
                            role="status"
                            aria-live="polite"
                        >
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2 text-gray-700">
                                    <Loader2
                                        v-if="isBusy"
                                        class="h-4 w-4 animate-spin text-[#337ab7]"
                                    />
                                    <CheckCircle2
                                        v-else-if="status === 'done'"
                                        class="h-4 w-4 text-green-600"
                                    />
                                    <span>{{ statusLabel }}</span>
                                </div>
                                <span
                                    v-if="status === 'downloading' && totalBytes > 0"
                                    class="font-medium tabular-nums text-gray-700"
                                >
                                    {{ percentage }}%
                                </span>
                            </div>

                            <div class="h-2 w-full overflow-hidden rounded bg-gray-200">
                                <div
                                    v-if="isIndeterminate"
                                    class="h-full w-1/3 animate-[backupSlide_1.4s_ease-in-out_infinite] rounded bg-[#337ab7]"
                                />
                                <div
                                    v-else
                                    class="h-full rounded bg-[#337ab7] transition-[width] duration-200 ease-out"
                                    :class="{ 'bg-green-600': status === 'done' }"
                                    :style="{ width: `${percentage}%` }"
                                />
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                :disabled="isBusy"
                                class="bg-[#337ab7] hover:bg-[#286090]"
                                @click="handleBackup"
                            >
                                <Loader2 v-if="isBusy" class="h-4 w-4 animate-spin" />
                                <Download v-else class="h-4 w-4" />
                                {{ isBusy ? 'Memproses...' : status === 'done' ? 'Backup Lagi' : 'Backup & Download' }}
                            </Button>

                            <Button
                                v-if="status === 'error'"
                                type="button"
                                variant="ghost"
                                @click="reset"
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<style>
@keyframes backupSlide {
    0% {
        transform: translateX(-100%);
    }
    50% {
        transform: translateX(150%);
    }
    100% {
        transform: translateX(300%);
    }
}
</style>

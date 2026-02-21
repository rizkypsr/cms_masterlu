<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';


interface Subtitle {
    id: number;
    audio_id: number;
    timestamp: string;
    title: string;
    description: string;
    script: string;
}

interface Audio {
    id: number;
    title: string;
}

const props = defineProps<{
    audio: Audio;
    subtitles: Subtitle[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Format timestamp - handles both seconds and HH:MM:SS format
const formatTimestamp = (timestamp: string | number): string => {
    // If it's already in HH:MM:SS format, return as is
    if (typeof timestamp === 'string' && timestamp.includes(':')) {
        return timestamp;
    }
    
    // Convert to number
    const value = typeof timestamp === 'string' ? parseInt(timestamp) : timestamp;
    
    // If value is very small (< 86400 = 24 hours in seconds), treat as seconds
    // If value is large, treat as milliseconds
    const totalSeconds = value < 86400 ? value : Math.floor(value / 1000);
    
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
};

// Pagination
const itemsPerPage = ref(10);
const currentPage = ref(1);
const searchQuery = ref('');

// Filtered and paginated subtitles
const filteredSubtitles = computed(() => {
    if (!searchQuery.value) return props.subtitles;
    const query = searchQuery.value.toLowerCase();
    return props.subtitles.filter(s => 
        s.description.toLowerCase().includes(query) ||
        s.timestamp.toLowerCase().includes(query)
    );
});

const paginatedSubtitles = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredSubtitles.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(filteredSubtitles.value.length / itemsPerPage.value));
const showingFrom = computed(() => filteredSubtitles.value.length === 0 ? 0 : (currentPage.value - 1) * itemsPerPage.value + 1);
const showingTo = computed(() => Math.min(currentPage.value * itemsPerPage.value, filteredSubtitles.value.length));

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete' | 'deleteAll' | 'uploadSrt'>('add');
const modalTitle = ref('');
const selectedItem = ref<Subtitle | null>(null);

// Form
const form = useForm({
    timestamp: '',
    title: '',
    description: '',
    script: '',
});

// Format timestamp input as user types (HH:MM:SS)
const formatTimestampInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    let value = input.value.replace(/\D/g, ''); // Remove non-digits
    
    // Limit to 6 digits (HHMMSS)
    if (value.length > 6) {
        value = value.slice(0, 6);
    }
    
    // Format as HH:MM:SS
    let formatted = '';
    if (value.length > 0) {
        formatted = value.slice(0, 2);
        if (value.length > 2) {
            formatted += ':' + value.slice(2, 4);
        }
        if (value.length > 4) {
            formatted += ':' + value.slice(4, 6);
        }
    }
    
    form.timestamp = formatted;
};

// Convert HH:MM:SS to seconds
const timestampToSeconds = (timestamp: string): number => {
    if (!timestamp) return 0;
    
    const parts = timestamp.split(':');
    if (parts.length !== 3) return 0;
    
    const hours = parseInt(parts[0]) || 0;
    const minutes = parseInt(parts[1]) || 0;
    const seconds = parseInt(parts[2]) || 0;
    
    return hours * 3600 + minutes * 60 + seconds;
};

// Upload SRT form
const uploadForm = useForm({
    srt_file: null as File | null,
});

const openModal = (type: 'add' | 'edit' | 'delete' | 'deleteAll' | 'uploadSrt', item?: Subtitle) => {
    modalType.value = type;
    selectedItem.value = item || null;

    switch (type) {
        case 'uploadSrt':
            modalTitle.value = 'Upload Subtitle';
            uploadForm.reset();
            break;
        case 'add':
            modalTitle.value = 'Form Subtitle Audio';
            form.reset();
            break;
        case 'edit':
            modalTitle.value = 'Edit Subtitle';
            form.timestamp = item?.timestamp ? formatTimestamp(item.timestamp) : '';
            form.title = item?.title || '';
            form.description = item?.description || '';
            form.script = item?.script || '';
            break;
        case 'delete':
            modalTitle.value = 'Hapus Data';
            break;
        case 'deleteAll':
            modalTitle.value = 'Hapus Semua Data';
            break;
    }

    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        router.post(`/audio/subtitle/${props.audio.id}`, {
            timestamp: form.timestamp,
            title: form.title,
            description: form.description,
            script: form.script,
        }, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        router.put(`/audio/subtitle/item/${selectedItem.value.id}`, {
            timestamp: form.timestamp,
            title: form.title,
            description: form.description,
            script: form.script,
        }, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/audio/subtitle/item/${selectedItem.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const handleDeleteAll = () => {
    router.delete(`/audio/subtitle/${props.audio.id}/all`, {
        onSuccess: () => modalOpen.value = false,
    });
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        uploadForm.srt_file = target.files[0];
    }
};

const handleUploadSrt = () => {
    if (!uploadForm.srt_file) return;
    
    uploadForm.post(`/audio/subtitle/${props.audio.id}/upload-srt`, {
        forceFormData: true,
        onSuccess: () => {
            modalOpen.value = false;
            uploadForm.reset();
        },
        onError: (errors) => {
            console.error('Upload errors:', errors);
        },
    });
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const exportSrt = () => {
    window.location.href = `/audio/subtitle/${props.audio.id}/export-srt`;
};
</script>

<template>
    <Head :title="`Subtitle - ${audio.title}`" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600">Subtitle Audio</span>
                        <span class="text-sm font-medium text-red-500">{{ audio.title }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            @click="openModal('deleteAll')"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                            size="sm"
                        >
                            <Icon icon="mdi:delete" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Hapus Semua Data</span>
                        </Button>
                        <Button 
                            @click="exportSrt"
                            class="bg-[#f0ad4e] hover:bg-[#eea236]"
                            size="sm"
                        >
                            <Icon icon="mdi:download" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Export SRT</span>
                        </Button>
                        <Button 
                            @click="openModal('uploadSrt')"
                            class="bg-[#5bc0de] hover:bg-[#46b8da]"
                            size="sm"
                        >
                            <Icon icon="mdi:upload" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Tambah Subtitle</span>
                        </Button>
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                            size="sm"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4 lg:mr-1" />
                            <span class="hidden lg:inline">Tambah Data</span>
                        </Button>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2">
                    <div class="flex items-center gap-2">
                        <select 
                            v-model="itemsPerPage" 
                            class="rounded border border-gray-300 px-2 py-1 text-sm"
                            @change="currentPage = 1"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                        <span class="text-sm text-gray-600">items/page</span>
                    </div>
                    <div>
                        <Input 
                            v-model="searchQuery"
                            placeholder="Search..."
                            class="h-8 w-48"
                            @input="currentPage = 1"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-auto">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr class="border-b border-gray-200">
                                <th class="w-20 px-4 py-2 text-left text-sm font-medium text-gray-600"></th>
                                <th class="w-32 px-4 py-2 text-left text-sm font-medium text-gray-600">
                                    <div class="flex items-center gap-1">
                                        Waktu
                                        <Icon icon="mdi:arrow-down" class="h-3 w-3" />
                                    </div>
                                </th>
                                <th class="w-48 px-4 py-2 text-left text-sm font-medium text-gray-600">Title</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="subtitle in paginatedSubtitles" 
                                :key="subtitle.id"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openModal('edit', subtitle)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-3 w-3" />
                                        </button>
                                        <button
                                            @click="openModal('delete', subtitle)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ formatTimestamp(subtitle.timestamp) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ subtitle.title }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <div class="prose prose-sm max-w-none" v-html="subtitle.description"></div>
                                </td>
                            </tr>
                            <tr v-if="paginatedSubtitles.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada data
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2">
                    <div class="text-sm text-gray-600">
                        Showing {{ showingFrom }} to {{ showingTo }} of {{ filteredSubtitles.length }} entries
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="goToPage(1)"
                            :disabled="currentPage === 1"
                            class="flex h-8 w-8 items-center justify-center rounded border border-gray-300 bg-white text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Icon icon="mdi:chevron-double-left" class="h-4 w-4" />
                        </button>
                        <button
                            @click="goToPage(currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="flex h-8 w-8 items-center justify-center rounded border border-gray-300 bg-white text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Icon icon="mdi:chevron-left" class="h-4 w-4" />
                        </button>
                        
                        <template v-for="page in totalPages" :key="page">
                            <button
                                v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                                @click="goToPage(page)"
                                :class="[
                                    'flex h-8 min-w-[32px] items-center justify-center rounded border px-2 text-sm',
                                    page === currentPage 
                                        ? 'border-[#337ab7] bg-[#337ab7] text-white hover:bg-[#286090]' 
                                        : 'border-gray-300 bg-white hover:bg-gray-50'
                                ]"
                            >
                                {{ page }}
                            </button>
                            <span 
                                v-else-if="page === currentPage - 2 || page === currentPage + 2"
                                class="flex h-8 w-8 items-center justify-center text-gray-400"
                            >
                                ...
                            </span>
                        </template>

                        <button
                            @click="goToPage(currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="flex h-8 w-8 items-center justify-center rounded border border-gray-300 bg-white text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Icon icon="mdi:chevron-right" class="h-4 w-4" />
                        </button>
                        <button
                            @click="goToPage(totalPages)"
                            :disabled="currentPage === totalPages"
                            class="flex h-8 w-8 items-center justify-center rounded border border-gray-300 bg-white text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Icon icon="mdi:chevron-double-right" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] translate-y-0 sm:max-w-2xl max-h-[85vh] flex flex-col p-0">
                <DialogHeader class="px-6 pt-6 pb-0">
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <!-- Scrollable Content -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <!-- Add/Edit Modal -->
                    <form v-if="modalType === 'add' || modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Waktu</label>
                            <Input 
                                v-model="form.timestamp" 
                                @input="formatTimestampInput"
                                placeholder="000000 (akan diformat menjadi HH:MM:SS)"
                                maxlength="8"
                            />
                            <p class="mt-1 text-xs text-gray-500">Ketik 6 angka, contoh: 100000 → 10:00:00</p>
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <Input v-model="form.title" placeholder="Enter title" />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Deskripsi</label>
                            <TiptapEditor v-model="form.description" height="300px" />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Script</label>
                            <TiptapEditor v-model="form.script" height="300px" />
                        </div>
                    </form>

                    <!-- Delete Modal -->
                    <div v-else-if="modalType === 'delete'">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus subtitle ini?
                        </p>
                    </div>

                    <!-- Delete All Modal -->
                    <div v-else-if="modalType === 'deleteAll'">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <strong>semua</strong> subtitle untuk audio ini?
                        </p>
                    </div>

                    <!-- Upload SRT Modal -->
                    <form v-else-if="modalType === 'uploadSrt'" @submit.prevent="handleUploadSrt">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">SRT File</label>
                            <input 
                                type="file" 
                                accept=".srt,.txt"
                                @change="handleFileChange"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                            <p class="mt-1 text-xs text-gray-500">Upload an SRT subtitle file (max 10MB)</p>
                        </div>
                    </form>
                </div>

                <!-- Fixed Footer with Buttons -->
                <div class="border-t border-gray-200 px-6 py-4">
                    <!-- Add/Edit Buttons -->
                    <div v-if="modalType === 'add' || modalType === 'edit'" class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button 
                            type="button"
                            @click="handleSubmit"
                            :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'" 
                            :disabled="form.processing"
                        >
                            {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>

                    <!-- Delete Buttons -->
                    <div v-else-if="modalType === 'delete'" class="flex justify-end gap-2">
                        <Button variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">
                            Hapus
                        </Button>
                    </div>

                    <!-- Delete All Buttons -->
                    <div v-else-if="modalType === 'deleteAll'" class="flex justify-end gap-2">
                        <Button variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDeleteAll">
                            Hapus Semua
                        </Button>
                    </div>

                    <!-- Upload SRT Buttons -->
                    <div v-else-if="modalType === 'uploadSrt'" class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button 
                            type="button"
                            @click="handleUploadSrt"
                            class="bg-[#f0ad4e] hover:bg-[#eea236]" 
                            :disabled="uploadForm.processing || !uploadForm.srt_file"
                        >
                            Upload
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

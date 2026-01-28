<script setup lang="ts">
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Icon } from '@iconify/vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface Subtitle {
    id: number;
    video_id: number;
    timestamp: string;
    description: string;
}

interface Video {
    id: number;
    title: string;
}

const props = defineProps<{
    video: Video;
    subtitles: Subtitle[];
}>();

const page = usePage();
const user = page.props.auth?.user;

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
const modalType = ref<'add' | 'edit' | 'delete' | 'deleteAll'>('add');
const modalTitle = ref('');
const selectedItem = ref<Subtitle | null>(null);

// Form
const form = useForm({
    timestamp: '',
    description: '',
});

const openModal = (type: 'add' | 'edit' | 'delete' | 'deleteAll', item?: Subtitle) => {
    modalType.value = type;
    selectedItem.value = item || null;

    switch (type) {
        case 'add':
            modalTitle.value = 'Tambah Data';
            form.reset();
            break;
        case 'edit':
            modalTitle.value = 'Edit Data';
            form.timestamp = item?.timestamp || '';
            form.description = item?.description || '';
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
        form.post(`/video/subtitle/${props.video.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        form.put(`/video/subtitle/item/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/video/subtitle/item/${selectedItem.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const handleDeleteAll = () => {
    router.delete(`/video/subtitle/${props.video.id}/all`, {
        onSuccess: () => modalOpen.value = false,
    });
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};
</script>

<template>
    <Head :title="`Subtitle - ${video.title}`" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600">Subtitle Video</span>
                        <span class="text-sm font-medium text-red-500">{{ video.title }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            @click="openModal('deleteAll')"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                            size="sm"
                        >
                            <Icon icon="mdi:delete" class="mr-1 h-4 w-4" />
                            Hapus Semua Data
                        </Button>
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#5bc0de] hover:bg-[#46b8da]"
                            size="sm"
                        >
                            <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                            Tambah Subtitle
                        </Button>
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                            size="sm"
                        >
                            <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                            Tambah Data
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
                                <td class="px-4 py-2 text-sm text-gray-600">{{ subtitle.timestamp }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 whitespace-pre-line">{{ subtitle.description }}</td>
                            </tr>
                            <tr v-if="paginatedSubtitles.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">
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
                        <Button 
                            variant="outline" 
                            size="sm"
                            :disabled="currentPage === 1"
                            @click="goToPage(currentPage - 1)"
                        >
                            Previous
                        </Button>
                        <Button 
                            v-for="page in totalPages" 
                            :key="page"
                            :variant="page === currentPage ? 'default' : 'outline'"
                            size="sm"
                            class="min-w-[32px]"
                            :class="page === currentPage ? 'bg-[#337ab7] hover:bg-[#286090]' : ''"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            :disabled="currentPage === totalPages || totalPages === 0"
                            @click="goToPage(currentPage + 1)"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[20%] translate-y-0 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="py-4">
                    <!-- Add/Edit Modal -->
                    <form v-if="modalType === 'add' || modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Waktu</label>
                            <Input v-model="form.timestamp" placeholder="00:00:00" />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Deskripsi</label>
                            <textarea
                                v-model="form.description"
                                rows="5"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Masukkan deskripsi subtitle"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'" 
                                :disabled="form.processing"
                            >
                                {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Delete Modal -->
                    <div v-else-if="modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus subtitle ini?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">
                                Hapus
                            </Button>
                        </div>
                    </div>

                    <!-- Delete All Modal -->
                    <div v-else-if="modalType === 'deleteAll'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <strong>semua</strong> subtitle untuk video ini?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDeleteAll">
                                Hapus Semua
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

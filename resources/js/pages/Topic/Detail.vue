<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface TopicCategory {
    id: number;
    parent_id: number | null;
    topics_id: number;
    title: string;
    seq: number;
    have_child: number;
    topic: {
        id: number;
        title: string;
    };
}

interface TopicContentItem {
    id: number;
    type: 'audio' | 'video';
    title: string;
    waktu: string;
    timestamp: string;
    source: string;
    seq: number;
    content: any;
}

interface AvailableItem {
    id: number;
    type: 'audio' | 'video';
    title: string;
    timestamp: string;
    description: string;
    source: string;
}

interface PaginatedAvailable {
    data: AvailableItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Category {
    id: number;
    languange: string;
}

const props = defineProps<{
    category: Category;
    topicCategory: TopicCategory;
    items: TopicContentItem[];
    // Optional Inertia prop: absent until the picker asks for it.
    availableItems?: PaginatedAvailable;
    availableFilters: { search: string };
}>();

const page = usePage();
const user = page.props.auth?.user;

// Pagination for main table
// Search for main table
const searchQuery = ref('');

// The picker is searched and paged on the server: the pool is ~18k rows, and
// shipping it whole exhausted PHP's memory before the page could render.
const modalSearchQuery = ref('');
const availableLoading = ref(false);

const loadAvailable = (page = 1) => {
    availableLoading.value = true;
    router.reload({
        only: ['availableItems'],
        data: { item_search: modalSearchQuery.value || undefined, item_page: page },
        onFinish: () => (availableLoading.value = false),
    });
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;

const onModalSearch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadAvailable(1), 300);
};

// Filtered items (main table - no pagination, show all)
const filteredItems = computed(() => {
    if (!searchQuery.value) return props.items;
    const query = searchQuery.value.toLowerCase();
    return props.items.filter(item => 
        item.title.toLowerCase().includes(query)
    );
});

const paginatedAvailableItems = computed(() => props.availableItems?.data ?? []);
const modalCurrentPage = computed(() => props.availableItems?.current_page ?? 1);
const modalTotalPages = computed(() => props.availableItems?.last_page ?? 1);
const availableTotal = computed(() => props.availableItems?.total ?? 0);
const modalShowingFrom = computed(() => props.availableItems?.from ?? 0);
const modalShowingTo = computed(() => props.availableItems?.to ?? 0);

const goToModalPage = (page: number) => {
    if (page >= 1 && page <= modalTotalPages.value) {
        loadAvailable(page);
    }
};

// Only the pages actually shown. The pool runs to ~900 pages, so looping over
// every one of them just to render five buttons is wasted work each render.
const modalPageWindow = computed<(number | '...')[]>(() => {
    const last = modalTotalPages.value;
    const current = modalCurrentPage.value;
    const pages = new Set<number>([1, last, current - 1, current, current + 1]);

    const shown = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);

    return shown.flatMap((p, i) => (i > 0 && p - shown[i - 1] > 1 ? ['...' as const, p] : [p]));
});

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedItem = ref<TopicContentItem | null>(null);
const selectedAvailableItem = ref<AvailableItem | null>(null);

// Bulk delete functionality
const selectedItems = ref<number[]>([]);
const selectAll = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = filteredItems.value.map(item => item.id);
    } else {
        selectedItems.value = [];
    }
};

const toggleSelectItem = (itemId: number) => {
    const index = selectedItems.value.indexOf(itemId);
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(itemId);
    }
    
    // Update select all checkbox
    selectAll.value = selectedItems.value.length === filteredItems.value.length;
};

const bulkDeleteForm = useForm({
    content_ids: [] as number[],
});

const handleBulkDelete = () => {
    if (selectedItems.value.length === 0) return;
    
    if (confirm(`Apakah Anda yakin ingin menghapus ${selectedItems.value.length} item?`)) {
        bulkDeleteForm.content_ids = selectedItems.value;
        bulkDeleteForm.post('/topic/content/bulk-delete', {
            onSuccess: () => {
                selectedItems.value = [];
                selectAll.value = false;
            },
        });
    }
};

// Navigate to audio subtitle source
const navigateToSource = (item: TopicContentItem) => {
    if (item.content && item.content.audio_id) {
        const url = `/audio/subtitle/${item.content.audio_id}`;
        window.open(url, '_blank');
    }
};

// Form
const form = useForm({
    id_header: '' as string | number,
    seq: null as number | null,
});
const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    props.items.forEach((item, index) => {
        const position = index + 1;
        options.push({
            position,
            label: `${position} - ${item.title}`,
        });
    });
    
    const lastPosition = props.items.length + 1;
    options.push({
        position: lastPosition,
        label: `${lastPosition} - (Terakhir)`,
    });
    
    return options;
});

const selectUrutan = (position: number) => {
    const option = urutanOptions.value.find((o: { position: number; label: string }) => o.position === position);
    if (option) {
        const match = option.label.match(/^(\d+) - (.+)$/);
        if (match) {
            const seq = parseInt(match[1]);
            form.seq = seq;
        }
    }
};

const openModal = (type: typeof modalType.value, item?: TopicContentItem) => {
    modalType.value = type;
    selectedItem.value = item || null;
    selectedAvailableItem.value = null;
    modalSearchQuery.value = '';

    switch (type) {
        case 'add':
            modalTitle.value = 'Form Topik';
            form.reset();
            form.seq = props.items.length + 1;
            // Fetched here rather than with the page, so opening the detail
            // screen costs nothing when the picker is never used.
            loadAvailable(1);
            break;
        case 'edit':
            modalTitle.value = 'Edit Data';
            form.seq = item!.seq;
            break;
        case 'delete':
            modalTitle.value = 'Hapus Data';
            break;
    }

    modalOpen.value = true;
};

const selectAvailableItem = (item: AvailableItem) => {
    selectedAvailableItem.value = item;
    form.id_header = item.id;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        if (!selectedAvailableItem.value) {
            alert('Please select an item');
            return;
        }
        
        form.post(`/topic/category/${props.topicCategory.id}/content`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
                selectedAvailableItem.value = null;
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        form.put(`/topic/content/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/topic/content/${selectedItem.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};
</script>

<template>
    <Head :title="`Topik 1 - ${topicCategory.title}`" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600">Topik 1</span>
                        <span class="text-sm font-medium text-red-500">{{ topicCategory.title }}</span>
                    </div>
                    <Button 
                        @click="openModal('add')"
                        class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        size="sm"
                    >
                        <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                        Tambah Data
                    </Button>
                </div>

                <!-- Controls -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2">
                    <div class="flex items-center gap-4">
                        <Button 
                            v-if="selectedItems.length > 0"
                            @click="handleBulkDelete"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                            size="sm"
                        >
                            <Icon icon="mdi:delete" class="mr-1 h-4 w-4" />
                            Hapus ({{ selectedItems.length }})
                        </Button>
                    </div>
                    <div>
                        <Input 
                            v-model="searchQuery"
                            placeholder="Search..."
                            class="h-8 w-48"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-auto">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr class="border-b border-gray-200">
                                <th class="w-12 px-4 py-2 text-left">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300"
                                    />
                                </th>
                                <th class="w-20 px-4 py-2 text-left text-sm font-medium text-gray-600"></th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Title</th>
                                <th class="w-32 px-4 py-2 text-left text-sm font-medium text-gray-600">Timestamp</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="item in filteredItems" 
                                :key="item.id"
                                class="border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                                @click="navigateToSource(item)"
                            >
                                <td class="px-4 py-2" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="selectedItems.includes(item.id)"
                                        @change="toggleSelectItem(item.id)"
                                        class="rounded border-gray-300"
                                    />
                                </td>
                                <td class="px-4 py-2" @click.stop>
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openModal('edit', item)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-3 w-3" />
                                        </button>
                                        <button
                                            @click="openModal('delete', item)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ item.title }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ item.timestamp }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ item.source }}</td>
                            </tr>
                            <tr v-if="filteredItems.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada data
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2">
                    <div class="text-sm text-gray-600">
                        Showing {{ filteredItems.length }} entries
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[5%] max-h-[90vh] translate-y-0 overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="py-4">
                    <!-- Add Form with Table -->
                    <form v-if="modalType === 'add'" @submit.prevent="handleSubmit" class="space-y-4">
                        <!-- Controls -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">
                                <span v-if="availableLoading">Memuat...</span>
                                <span v-else>{{ availableTotal }} item tersedia</span>
                            </span>
                            <Input
                                v-model="modalSearchQuery"
                                placeholder="Cari judul / deskripsi / audio..."
                                class="h-8 w-64"
                                @input="onModalSearch"
                            />
                        </div>

                        <!-- Table -->
                        <div class="max-h-[45vh] overflow-auto rounded border">
                            <table class="w-full">
                                <thead class="sticky top-0 bg-gray-50">
                                    <tr class="border-b border-gray-200">
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Title</th>
                                        <th class="w-32 px-4 py-2 text-left text-sm font-medium text-gray-600">Timestamp</th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="item in paginatedAvailableItems" 
                                        :key="`${item.type}-${item.id}`"
                                        @click="selectAvailableItem(item)"
                                        :class="[
                                            'border-b border-gray-100 cursor-pointer hover:bg-gray-50',
                                            selectedAvailableItem?.id === item.id && selectedAvailableItem?.type === item.type ? 'bg-blue-50' : ''
                                        ]"
                                    >
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ item.title }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ item.timestamp }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ item.source }}</td>
                                    </tr>
                                    <tr v-if="paginatedAvailableItems.length === 0">
                                        <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="flex items-center justify-between border-t pt-2">
                            <div class="text-sm text-gray-600">
                                Showing {{ modalShowingFrom }} to {{ modalShowingTo }} of {{ availableTotal }} entries
                            </div>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="goToModalPage(modalCurrentPage - 1)"
                                    :disabled="modalCurrentPage === 1"
                                    class="flex h-8 px-3 items-center justify-center rounded border border-gray-300 bg-white text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                
                                <template v-for="(page, idx) in modalPageWindow" :key="`${page}-${idx}`">
                                    <button
                                        v-if="page !== '...'"
                                        type="button"
                                        :class="[
                                            'flex h-8 min-w-[32px] items-center justify-center rounded border px-2 text-sm',
                                            page === modalCurrentPage
                                                ? 'border-[#337ab7] bg-[#337ab7] text-white hover:bg-[#286090]'
                                                : 'border-gray-300 bg-white hover:bg-gray-50',
                                        ]"
                                        @click="goToModalPage(page)"
                                    >
                                        {{ page }}
                                    </button>
                                    <span v-else class="flex h-8 w-8 items-center justify-center text-gray-400">...</span>
                                </template>

                                <button
                                    type="button"
                                    @click="goToModalPage(modalCurrentPage + 1)"
                                    :disabled="modalCurrentPage === modalTotalPages"
                                    class="flex h-8 px-3 items-center justify-center rounded border border-gray-300 bg-white text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>

                        <!-- Urutan -->
                        <div>
                            <Label>Urutan</Label>
                            <select
                                :value="form.seq"
                                @change="selectUrutan(Number(($event.target as HTMLSelectElement).value))"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option v-for="option in urutanOptions" :key="option.position" :value="option.position">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                class="bg-[#5cb85c] hover:bg-[#4cae4c]" 
                                :disabled="form.processing || !selectedAvailableItem"
                            >
                                Pilih Topik
                            </Button>
                        </div>
                    </form>

                    <!-- Edit Form -->
                    <form v-else-if="modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <Label>Urutan</Label>
                            <select
                                :value="form.seq"
                                @change="selectUrutan(Number(($event.target as HTMLSelectElement).value))"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option v-for="option in urutanOptions" :key="option.position" :value="option.position">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                class="bg-[#f0ad4e] hover:bg-[#eea236]" 
                                :disabled="form.processing"
                            >
                                Update
                            </Button>
                        </div>
                    </form>

                    <!-- Delete Form -->
                    <div v-else-if="modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus data ini?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleDelete">
                                Hapus
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

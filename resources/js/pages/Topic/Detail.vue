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
    seq: number;
    content: any;
}

interface AvailableItem {
    id: number;
    type: 'audio' | 'video';
    title: string;
}

interface Category {
    id: number;
    languange: string;
}

const props = defineProps<{
    category: Category;
    topicCategory: TopicCategory;
    items: TopicContentItem[];
    availableItems: AvailableItem[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Pagination for main table
const itemsPerPage = ref(10);
const currentPage = ref(1);
const searchQuery = ref('');

// Pagination for modal table
const modalItemsPerPage = ref(10);
const modalCurrentPage = ref(1);
const modalSearchQuery = ref('');

// Filtered and paginated items (main table)
const filteredItems = computed(() => {
    if (!searchQuery.value) return props.items;
    const query = searchQuery.value.toLowerCase();
    return props.items.filter(item => 
        item.title.toLowerCase().includes(query)
    );
});

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredItems.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage.value));
const showingFrom = computed(() => filteredItems.value.length === 0 ? 0 : (currentPage.value - 1) * itemsPerPage.value + 1);
const showingTo = computed(() => Math.min(currentPage.value * itemsPerPage.value, filteredItems.value.length));

// Filtered and paginated available items (modal table)
const filteredAvailableItems = computed(() => {
    if (!modalSearchQuery.value) return props.availableItems;
    const query = modalSearchQuery.value.toLowerCase();
    return props.availableItems.filter(item => 
        item.title.toLowerCase().includes(query)
    );
});

const paginatedAvailableItems = computed(() => {
    const start = (modalCurrentPage.value - 1) * modalItemsPerPage.value;
    const end = start + modalItemsPerPage.value;
    return filteredAvailableItems.value.slice(start, end);
});

const modalTotalPages = computed(() => Math.ceil(filteredAvailableItems.value.length / modalItemsPerPage.value));
const modalShowingFrom = computed(() => filteredAvailableItems.value.length === 0 ? 0 : (modalCurrentPage.value - 1) * modalItemsPerPage.value + 1);
const modalShowingTo = computed(() => Math.min(modalCurrentPage.value * modalItemsPerPage.value, filteredAvailableItems.value.length));

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const goToModalPage = (page: number) => {
    if (page >= 1 && page <= modalTotalPages.value) {
        modalCurrentPage.value = page;
    }
};

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedItem = ref<TopicContentItem | null>(null);
const selectedAvailableItem = ref<AvailableItem | null>(null);

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
    const option = urutanOptions.value.find(o => o.position === position);
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
    modalCurrentPage.value = 1;

    switch (type) {
        case 'add':
            modalTitle.value = 'Form Topik';
            form.reset();
            form.seq = props.items.length + 1;
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
    <Head title="Topik 1 - {{ topicCategory.title }}" />

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
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Title</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="item in paginatedItems" 
                                :key="item.id"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="px-4 py-2">
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
                            </tr>
                            <tr v-if="paginatedItems.length === 0">
                                <td colspan="2" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada data
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2">
                    <div class="text-sm text-gray-600">
                        Showing {{ showingFrom }} to {{ showingTo }} of {{ filteredItems.length }} entries
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
            <DialogContent class="top-[5%] translate-y-0 sm:max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="flex-1 overflow-hidden py-4">
                    <!-- Add Form with Table -->
                    <form v-if="modalType === 'add'" @submit.prevent="handleSubmit" class="flex h-full flex-col space-y-4">
                        <!-- Controls -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <select 
                                    v-model="modalItemsPerPage" 
                                    class="rounded border border-gray-300 px-2 py-1 text-sm"
                                    @change="modalCurrentPage = 1"
                                >
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                </select>
                                <span class="text-sm text-gray-600">items/page</span>
                            </div>
                            <Input 
                                v-model="modalSearchQuery"
                                placeholder="Search..."
                                class="h-8 w-48"
                                @input="modalCurrentPage = 1"
                            />
                        </div>

                        <!-- Table -->
                        <div class="flex-1 overflow-auto border rounded">
                            <table class="w-full">
                                <thead class="sticky top-0 bg-gray-50">
                                    <tr class="border-b border-gray-200">
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Title</th>
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
                                    </tr>
                                    <tr v-if="paginatedAvailableItems.length === 0">
                                        <td class="px-4 py-8 text-center text-sm text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="flex items-center justify-between border-t pt-2">
                            <div class="text-sm text-gray-600">
                                Showing {{ modalShowingFrom }} to {{ modalShowingTo }} of {{ filteredAvailableItems.length }} entries
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
                                
                                <template v-for="page in modalTotalPages" :key="page">
                                    <button
                                        type="button"
                                        v-if="page === 1 || page === modalTotalPages || (page >= modalCurrentPage - 1 && page <= modalCurrentPage + 1)"
                                        @click="goToModalPage(page)"
                                        :class="[
                                            'flex h-8 min-w-[32px] items-center justify-center rounded border px-2 text-sm',
                                            page === modalCurrentPage 
                                                ? 'border-[#337ab7] bg-[#337ab7] text-white hover:bg-[#286090]' 
                                                : 'border-gray-300 bg-white hover:bg-gray-50'
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                    <span 
                                        v-else-if="page === modalCurrentPage - 2 || page === modalCurrentPage + 2"
                                        class="flex h-8 w-8 items-center justify-center text-gray-400"
                                    >
                                        ...
                                    </span>
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

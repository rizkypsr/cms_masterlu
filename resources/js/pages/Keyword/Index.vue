<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import KeywordModal from '@/components/Keyword/KeywordModal.vue';
import CategoryManagementModal from '@/components/Keyword/CategoryManagementModal.vue';
import { cn } from '@/lib/utils';

interface Category {
    id: number;
    title: string;
    type: string;
    seq: number;
}

interface Keyword {
    id: number;
    keyword_category_id: number;
    kata_kunci: string;
    category?: Category;
}

const props = defineProps<{
    keywords: Keyword[];
    categories: Category[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Pagination
const itemsPerPage = ref(10);
const currentPage = ref(1);
const searchQuery = ref('');

// Filtered and paginated keywords
const filteredKeywords = computed(() => {
    if (!searchQuery.value) return props.keywords;
    const query = searchQuery.value.toLowerCase();
    return props.keywords.filter(k => 
        k.kata_kunci.toLowerCase().includes(query) ||
        k.category?.title.toLowerCase().includes(query)
    );
});

const paginatedKeywords = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredKeywords.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(filteredKeywords.value.length / itemsPerPage.value));
const showingFrom = computed(() => filteredKeywords.value.length === 0 ? 0 : (currentPage.value - 1) * itemsPerPage.value + 1);
const showingTo = computed(() => Math.min(currentPage.value * itemsPerPage.value, filteredKeywords.value.length));

// Modal states
const keywordModalOpen = ref(false);
const keywordModalType = ref<'add' | 'edit' | 'delete'>('add');
const selectedKeyword = ref<Keyword | null>(null);

const categoryModalOpen = ref(false);

// Combobox state
const comboboxOpen = ref(false);

// Open keyword modal
const openKeywordModal = (type: 'add' | 'edit' | 'delete', keyword?: Keyword) => {
    keywordModalType.value = type;
    selectedKeyword.value = keyword || null;
    keywordModalOpen.value = true;
};

// Open category management modal
const openCategoryModal = () => {
    categoryModalOpen.value = true;
};

// Handle success (refresh page)
const handleSuccess = () => {
    // The page will automatically refresh via Inertia
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};
</script>

<template>
    <Head title="Kata Kunci" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-600">Kata Kunci</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            @click="openCategoryModal"
                            class="bg-[#337ab7] hover:bg-[#286090]"
                            size="sm"
                        >
                            <Icon icon="mdi:folder" class="mr-1 h-4 w-4" />
                            Kategori
                        </Button>
                        <Button 
                            @click="openKeywordModal('add')"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                            size="sm"
                        >
                            <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                            Tambah Keyword
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
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">
                                    <div class="flex items-center gap-1">
                                        Kategori
                                        <Icon icon="mdi:arrow-down" class="h-3 w-3" />
                                    </div>
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Kata Kunci</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="keyword in paginatedKeywords" 
                                :key="keyword.id"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openKeywordModal('edit', keyword)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-3 w-3" />
                                        </button>
                                        <button
                                            @click="openKeywordModal('delete', keyword)"
                                            class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ keyword.category?.title || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ keyword.kata_kunci }}</td>
                            </tr>
                            <tr v-if="paginatedKeywords.length === 0">
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
                        Showing {{ showingFrom }} to {{ showingTo }} of {{ filteredKeywords.length }} entries
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

        <!-- Keyword Modal -->
        <KeywordModal
            :open="keywordModalOpen"
            :type="keywordModalType"
            :keyword="selectedKeyword"
            :categories="categories"
            @update:open="keywordModalOpen = $event"
            @success="handleSuccess"
        />

        <!-- Category Management Modal -->
        <CategoryManagementModal
            :open="categoryModalOpen"
            :categories="categories"
            @update:open="categoryModalOpen = $event"
            @success="handleSuccess"
        />
    </DashboardLayout>
</template>

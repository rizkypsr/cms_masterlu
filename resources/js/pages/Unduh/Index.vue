<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface Category {
    id: number;
    title: string;
    seq: number;
    parent_id: number | null;
    unduh_items?: UnduhItem[];
    children?: Category[];
}

interface UnduhItem {
    id: number;
    unduh_category_id: number;
    title: string;
    is_pdf: boolean;
    cover: string | null;
    url: string | null;
    link_url: string | null;
    seq: number;
}

const props = defineProps<{
    categories: Category[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Modal states
const categoryModalOpen = ref(false);
const categoryModalType = ref<'add' | 'edit' | 'delete' | 'addChild' | 'editChild' | 'deleteChild'>('add');
const selectedCategory = ref<Category | null>(null);
const selectedParentCategory = ref<Category | null>(null);

const unduhModalOpen = ref(false);
const unduhModalType = ref<'add' | 'edit' | 'delete'>('add');
const selectedUnduh = ref<UnduhItem | null>(null);
const selectedCategoryForUnduh = ref<number | null>(null);

// Forms
const categoryForm = useForm({
    title: '',
    seq: null as number | null,
    parent_id: null as number | null,
});

const unduhForm = useForm({
    unduh_category_id: 0,
    title: '',
    cover: null as File | null,
    url: '',
    link_url: '',
    seq: null as number | null,
});

// Urutan options for categories
const categoryUrutanOptions = computed(() => {
    const options: { position: number; seq: number; label: string }[] = [];
    
    let items: Category[] = [];
    
    // If adding/editing a child, use parent's children
    if ((categoryModalType.value === 'addChild' || categoryModalType.value === 'editChild') && selectedParentCategory.value) {
        items = selectedParentCategory.value.children || [];
    } else if (categoryModalType.value === 'edit' && selectedCategory.value?.parent_id) {
        // If editing a category that has a parent, find the parent and use its children
        const parent = props.categories.find(c => c.id === selectedCategory.value?.parent_id);
        items = parent?.children || [];
    } else {
        // For parent categories
        items = props.categories;
    }
    
    items.forEach((c, index) => {
        const position = index + 1;
        options.push({
            position,
            seq: c.seq,
            label: `${position} - ${c.title}`,
        });
    });
    
    const lastPosition = items.length + 1;
    options.push({
        position: lastPosition,
        seq: lastPosition,
        label: `${lastPosition} - (Terakhir)`,
    });
    
    return options;
});

const categorySelectedPosition = computed(() => {
    return categoryForm.seq;
});

const selectCategoryUrutan = (position: number) => {
    categoryForm.seq = position;
};

// Urutan options for unduh items
const unduhUrutanOptions = computed(() => {
    const options: { position: number; seq: number; label: string }[] = [];
    
    if (!selectedCategoryForUnduh.value) return options;
    
    const category = props.categories.find(c => c.id === selectedCategoryForUnduh.value);
    const items = category?.unduh_items || [];
    
    items.forEach((item, index) => {
        const position = index + 1;
        options.push({
            position,
            seq: item.seq,
            label: `${position} - ${item.title}`,
        });
    });
    
    const lastPosition = items.length + 1;
    options.push({
        position: lastPosition,
        seq: lastPosition,
        label: `${lastPosition} - (Terakhir)`,
    });
    
    return options;
});

const unduhSelectedPosition = computed(() => {
    return unduhForm.seq;
});

const selectUnduhUrutan = (position: number) => {
    unduhForm.seq = position;
};

// Category modal handlers
const openCategoryModal = (type: 'add' | 'edit' | 'delete' | 'addChild' | 'editChild' | 'deleteChild', category?: Category) => {
    categoryModalType.value = type;
    selectedCategory.value = category || null;
    
    if (type === 'add') {
        categoryForm.reset();
        categoryForm.seq = props.categories.length + 1;
        categoryForm.parent_id = null;
        selectedParentCategory.value = null;
    } else if (type === 'addChild' && category) {
        categoryForm.reset();
        categoryForm.parent_id = category.id;
        categoryForm.seq = (category.children?.length || 0) + 1;
        selectedParentCategory.value = category;
    } else if (type === 'edit' && category) {
        categoryForm.title = category.title;
        categoryForm.parent_id = category.parent_id;
        const categoryIndex = props.categories.findIndex(c => c.id === category.id);
        categoryForm.seq = categoryIndex !== -1 ? categoryIndex + 1 : category.seq;
        selectedParentCategory.value = null;
    } else if (type === 'editChild' && category) {
        categoryForm.title = category.title;
        categoryForm.parent_id = category.parent_id;
        
        // Find parent and set it
        selectedParentCategory.value = props.categories.find(c => c.id === category.parent_id) || null;
        
        // Find position in parent's children
        const siblings = selectedParentCategory.value?.children || [];
        const childIndex = siblings.findIndex(c => c.id === category.id);
        categoryForm.seq = childIndex !== -1 ? childIndex + 1 : category.seq;
    }
    
    categoryModalOpen.value = true;
};

const handleCategorySubmit = () => {
    if (categoryModalType.value === 'add' || categoryModalType.value === 'addChild') {
        categoryForm.post('/unduh/category', {
            onSuccess: () => {
                categoryModalOpen.value = false;
                categoryForm.reset();
            },
        });
    } else if ((categoryModalType.value === 'edit' || categoryModalType.value === 'editChild') && selectedCategory.value) {
        categoryForm.put(`/unduh/category/${selectedCategory.value.id}`, {
            onSuccess: () => {
                categoryModalOpen.value = false;
                categoryForm.reset();
            },
        });
    }
};

const handleCategoryDelete = () => {
    if (selectedCategory.value) {
        categoryForm.delete(`/unduh/category/${selectedCategory.value.id}`, {
            onSuccess: () => {
                categoryModalOpen.value = false;
            },
        });
    }
};

// Unduh modal handlers
const openUnduhModal = (type: 'add' | 'edit' | 'delete', categoryId?: number, unduh?: UnduhItem) => {
    unduhModalType.value = type;
    selectedUnduh.value = unduh || null;
    selectedCategoryForUnduh.value = categoryId || null;
    
    if (type === 'add' && categoryId) {
        unduhForm.reset();
        unduhForm.unduh_category_id = categoryId;
        const category = props.categories.find(c => c.id === categoryId);
        unduhForm.seq = (category?.unduh_items?.length || 0) + 1;
    } else if (type === 'edit' && unduh) {
        unduhForm.unduh_category_id = unduh.unduh_category_id;
        unduhForm.title = unduh.title;
        unduhForm.cover = null;
        unduhForm.url = unduh.url || '';
        unduhForm.link_url = unduh.link_url || '';
        
        const category = props.categories.find(c => c.id === unduh.unduh_category_id);
        const items = category?.unduh_items || [];
        const unduhIndex = items.findIndex(item => item.id === unduh.id);
        unduhForm.seq = unduhIndex !== -1 ? unduhIndex + 1 : unduh.seq;
    }
    
    unduhModalOpen.value = true;
};

const handleUnduhSubmit = () => {
    if (unduhModalType.value === 'add') {
        unduhForm.post('/unduh', {
            forceFormData: true,
            onSuccess: () => {
                unduhModalOpen.value = false;
                unduhForm.reset();
            },
        });
    } else if (unduhModalType.value === 'edit' && selectedUnduh.value) {
        unduhForm.post(`/unduh/${selectedUnduh.value.id}`, {
            forceFormData: true,
            onSuccess: () => {
                unduhModalOpen.value = false;
                unduhForm.reset();
            },
        });
    }
};

const handleUnduhDelete = () => {
    if (selectedUnduh.value) {
        unduhForm.delete(`/unduh/${selectedUnduh.value.id}`, {
            onSuccess: () => {
                unduhModalOpen.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="Unduh" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-600">Unduh</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            @click="openCategoryModal('add')"
                            class="bg-[#337ab7] hover:bg-[#286090]"
                            size="sm"
                        >
                            <Icon icon="mdi:folder-plus" class="mr-1 h-4 w-4" />
                            Tambah Kategori
                        </Button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-auto p-6">
                    <div v-if="categories.length === 0" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <Icon icon="mdi:download-outline" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                            <p class="mb-4 text-gray-600">Belum ada kategori unduh</p>
                            <Button 
                                @click="openCategoryModal('add')"
                                class="bg-[#337ab7] hover:bg-[#286090]"
                            >
                                <Icon icon="mdi:folder-plus" class="mr-1 h-4 w-4" />
                                Tambah Kategori
                            </Button>
                        </div>
                    </div>

                    <div v-else class="space-y-6">
                        <div 
                            v-for="category in categories" 
                            :key="category.id"
                            class="rounded-lg border border-gray-200 bg-white shadow-sm"
                        >
                            <!-- Parent Category Header -->
                            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Icon icon="mdi:folder" class="h-5 w-5 text-gray-600" />
                                    <span class="font-medium text-gray-700">{{ category.title }}</span>
                                    <span class="text-xs text-gray-500">(Seq: {{ category.seq }})</span>
                                </div>
                                <div class="flex gap-1">
                                    <button
                                        @click="openCategoryModal('addChild', category)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                        title="Tambah Sub Kategori"
                                    >
                                        <Icon icon="mdi:folder-plus" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openCategoryModal('edit', category)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        title="Edit Kategori"
                                    >
                                        <Icon icon="mdi:pencil" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openCategoryModal('delete', category)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        title="Hapus Kategori"
                                    >
                                        <Icon icon="mdi:delete" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Parent Category Unduh Items (removed - parent can't have items) -->

                            <!-- Child Categories -->
                            <div v-if="category.children && category.children.length > 0" class="p-4">
                                <div 
                                    v-for="child in category.children" 
                                    :key="child.id"
                                    class="mb-4 rounded-lg border border-gray-300 bg-white last:mb-0"
                                >
                                    <!-- Child Category Header -->
                                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-100 px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            <Icon icon="mdi:folder-outline" class="h-4 w-4 text-gray-600" />
                                            <span class="text-sm font-medium text-gray-700">{{ child.title }}</span>
                                            <span class="text-xs text-gray-500">(Seq: {{ child.seq }})</span>
                                        </div>
                                        <div class="flex gap-1">
                                            <button
                                                @click="openUnduhModal('add', child.id)"
                                                class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                                title="Tambah Item"
                                            >
                                                <Icon icon="mdi:plus" class="h-3 w-3" />
                                            </button>
                                            <button
                                                @click="openCategoryModal('editChild', child)"
                                                class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                title="Edit Sub Kategori"
                                            >
                                                <Icon icon="mdi:pencil" class="h-3 w-3" />
                                            </button>
                                            <button
                                                @click="openCategoryModal('deleteChild', child)"
                                                class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                title="Hapus Sub Kategori"
                                            >
                                                <Icon icon="mdi:delete" class="h-3 w-3" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Child Category Unduh Items -->
                                    <div class="p-3">
                                        <div v-if="!child.unduh_items || child.unduh_items.length === 0" class="py-4 text-center text-xs text-gray-500">
                                            Belum ada item unduh
                                        </div>
                                        <div v-else class="space-y-2">
                                            <div 
                                                v-for="item in child.unduh_items" 
                                                :key="item.id"
                                                class="flex items-center justify-between rounded border border-gray-200 bg-gray-50 p-2"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <div v-if="item.cover" class="h-10 w-10 flex-shrink-0 overflow-hidden rounded border border-gray-200">
                                                        <img 
                                                            :src="item.cover"
                                                            :alt="item.title"
                                                            class="h-full w-full object-cover"
                                                        />
                                                    </div>
                                                    <Icon 
                                                        v-else
                                                        :icon="item.is_pdf ? 'mdi:file-pdf-box' : 'mdi:file-document'" 
                                                        class="h-6 w-6"
                                                        :class="item.is_pdf ? 'text-red-500' : 'text-blue-500'"
                                                    />
                                                    <div>
                                                        <p class="text-xs font-medium text-gray-700">{{ item.title }}</p>
                                                        <div class="flex items-center gap-2 text-xs text-gray-500">
                                                            <span>Seq: {{ item.seq }}</span>
                                                            <span v-if="item.is_pdf" class="rounded bg-red-100 px-1 py-0.5 text-red-700">PDF</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex gap-1">
                                                    <button
                                                        @click="openUnduhModal('edit', child.id, item)"
                                                        class="flex h-6 w-6 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                    >
                                                        <Icon icon="mdi:pencil" class="h-3 w-3" />
                                                    </button>
                                                    <button
                                                        @click="openUnduhModal('delete', child.id, item)"
                                                        class="flex h-6 w-6 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                    >
                                                        <Icon icon="mdi:delete" class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state for parent category -->
                            <div v-if="!category.children || category.children.length === 0" class="p-4">
                                <p class="py-8 text-center text-sm text-gray-500">
                                    Belum ada sub kategori
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Modal -->
        <Dialog :open="categoryModalOpen" @update:open="categoryModalOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ categoryModalType === 'add' ? 'Tambah Kategori' : categoryModalType === 'addChild' ? 'Tambah Sub Kategori' : categoryModalType === 'edit' ? 'Edit Kategori' : categoryModalType === 'editChild' ? 'Edit Sub Kategori' : categoryModalType === 'delete' ? 'Hapus Kategori' : 'Hapus Sub Kategori' }}
                    </DialogTitle>
                </DialogHeader>

                <form v-if="categoryModalType !== 'delete' && categoryModalType !== 'deleteChild'" @submit.prevent="handleCategorySubmit" class="space-y-4">
                    <div>
                        <label for="category-title" class="mb-1 block text-sm font-medium text-gray-700">
                            Judul <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="category-title"
                            v-model="categoryForm.title"
                            type="text"
                            placeholder="Masukkan judul kategori"
                            required
                        />
                        <p v-if="categoryForm.errors.title" class="mt-1 text-sm text-red-600">
                            {{ categoryForm.errors.title }}
                        </p>
                    </div>

                    <div>
                        <label for="category-seq" class="mb-1 block text-sm font-medium text-gray-700">
                            Urutan <span class="text-red-500">*</span>
                        </label>
                        <select
                            :value="categorySelectedPosition"
                            @change="selectCategoryUrutan(Number(($event.target as HTMLSelectElement).value))"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            required
                        >
                            <option v-for="option in categoryUrutanOptions" :key="option.position" :value="option.position">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="categoryForm.errors.seq" class="mt-1 text-sm text-red-600">
                            {{ categoryForm.errors.seq }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button 
                            type="button"
                            variant="outline"
                            @click="categoryModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit"
                            :disabled="categoryForm.processing"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        >
                            {{ categoryModalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>

                <div v-else>
                    <p class="mb-4 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus {{ categoryModalType === 'deleteChild' ? 'sub kategori' : 'kategori' }} <strong>{{ selectedCategory?.title }}</strong>?
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button 
                            type="button"
                            variant="outline"
                            @click="categoryModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="button"
                            @click="handleCategoryDelete"
                            :disabled="categoryForm.processing"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                        >
                            Hapus
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Unduh Item Modal -->
        <Dialog :open="unduhModalOpen" @update:open="unduhModalOpen = $event">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ unduhModalType === 'add' ? 'Tambah Item Unduh' : unduhModalType === 'edit' ? 'Edit Item Unduh' : 'Hapus Item Unduh' }}
                    </DialogTitle>
                </DialogHeader>

                <form v-if="unduhModalType !== 'delete'" @submit.prevent="handleUnduhSubmit" class="space-y-4">
                    <div>
                        <label for="unduh-title" class="mb-1 block text-sm font-medium text-gray-700">
                            Judul <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="unduh-title"
                            v-model="unduhForm.title"
                            type="text"
                            placeholder="Masukkan judul"
                            required
                        />
                        <p v-if="unduhForm.errors.title" class="mt-1 text-sm text-red-600">
                            {{ unduhForm.errors.title }}
                        </p>
                    </div>

                    <div>
                        <label for="unduh-cover" class="mb-1 block text-sm font-medium text-gray-700">
                            Cover Image
                        </label>
                        <input
                            id="unduh-cover"
                            type="file"
                            accept="image/*"
                            @change="(e) => unduhForm.cover = (e.target as HTMLInputElement).files?.[0] || null"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        />
                        <p v-if="unduhModalType === 'edit' && selectedUnduh?.cover" class="mt-1 text-xs text-gray-500">
                            Current: {{ selectedUnduh.cover.split('/').pop() }}
                        </p>
                        <p v-if="unduhForm.errors.cover" class="mt-1 text-sm text-red-600">
                            {{ unduhForm.errors.cover }}
                        </p>
                    </div>

                    <div>
                        <label for="unduh-url" class="mb-1 block text-sm font-medium text-gray-700">
                            URL
                        </label>
                        <Input
                            id="unduh-url"
                            v-model="unduhForm.url"
                            type="text"
                            placeholder="Masukkan URL (e.g., file.pdf)"
                        />
                        <p class="mt-1 text-xs text-gray-500">PDF akan terdeteksi otomatis jika URL berakhiran .pdf</p>
                    </div>

                    <div>
                        <label for="unduh-link-url" class="mb-1 block text-sm font-medium text-gray-700">
                            Link URL
                        </label>
                        <Input
                            id="unduh-link-url"
                            v-model="unduhForm.link_url"
                            type="text"
                            placeholder="Masukkan link URL (e.g., file.pdf)"
                        />
                        <p class="mt-1 text-xs text-gray-500">PDF akan terdeteksi otomatis jika Link URL berakhiran .pdf</p>
                    </div>

                    <div>
                        <label for="unduh-seq" class="mb-1 block text-sm font-medium text-gray-700">
                            Urutan <span class="text-red-500">*</span>
                        </label>
                        <select
                            :value="unduhSelectedPosition"
                            @change="selectUnduhUrutan(Number(($event.target as HTMLSelectElement).value))"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            required
                        >
                            <option v-for="option in unduhUrutanOptions" :key="option.position" :value="option.position">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="unduhForm.errors.seq" class="mt-1 text-sm text-red-600">
                            {{ unduhForm.errors.seq }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button 
                            type="button"
                            variant="outline"
                            @click="unduhModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit"
                            :disabled="unduhForm.processing"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        >
                            {{ unduhModalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>

                <div v-else>
                    <p class="mb-4 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus item <strong>{{ selectedUnduh?.title }}</strong>?
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button 
                            type="button"
                            variant="outline"
                            @click="unduhModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="button"
                            @click="handleUnduhDelete"
                            :disabled="unduhForm.processing"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                        >
                            Hapus
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

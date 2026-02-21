<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface Topic {
    id: number;
    category_id: number;
    title: string;
    short_title: string;
    seq: number;
    icon: number;
}

interface TopicCategory {
    id: number;
    parent_id: number | null;
    topics_id: number;
    title: string;
    seq: number;
    have_child: number;
    children?: TopicCategory[];
}

interface Category {
    id: number;
    languange: string;
}

const props = defineProps<{
    category: Category;
    topics: Topic[];
    selectedTopic: Topic | null;
    topicCategories: TopicCategory[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Page title
const pageTitle = 'Topik 1';

// Topic category panel title
const topicCategoryTitle = computed(() => props.selectedTopic?.title || '');

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete' | 'addGroup' | 'addSingle' | 'editCategory' | 'deleteCategory' | 'addChild' | 'editChild' | 'deleteChild'>('add');
const modalTitle = ref('');
const selectedItem = ref<Topic | TopicCategory | null>(null);
const selectedParentCategory = ref<TopicCategory | null>(null);
const modalContext = ref<'topic' | 'category'>('topic');

// Topic form
const topicForm = useForm({
    title: '',
    short_title: '',
    icon: '' as string | number,
    seq: null as number | null,
});

// Category form
const categoryForm = useForm({
    title: '',
    seq: null as number | null,
    have_child: 1,
    parent_id: null as number | null,
});

// Urutan options for topics
const topicUrutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    props.topics.forEach((t, index) => {
        const position = index + 1;
        options.push({
            position,
            label: `${position} - ${t.title}`,
        });
    });
    
    const lastPosition = props.topics.length + 1;
    options.push({
        position: lastPosition,
        label: `${lastPosition} - (Terakhir)`,
    });
    
    return options;
});

// Urutan options for categories
const categoryUrutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    props.topicCategories.forEach((c, index) => {
        const position = index + 1;
        options.push({
            position,
            label: `${position} - ${c.title}`,
        });
    });
    
    const lastPosition = props.topicCategories.length + 1;
    options.push({
        position: lastPosition,
        label: `${lastPosition} - (Terakhir)`,
    });
    
    return options;
});

const selectTopicUrutan = (position: number) => {
    const option = topicUrutanOptions.value.find(o => o.position === position);
    if (option) {
        const match = option.label.match(/^(\d+) - (.+)$/);
        if (match) {
            const seq = parseInt(match[1]);
            topicForm.seq = seq;
        }
    }
};

const selectCategoryUrutan = (position: number) => {
    const option = categoryUrutanOptions.value.find(o => o.position === position);
    if (option) {
        const match = option.label.match(/^(\d+) - (.+)$/);
        if (match) {
            const seq = parseInt(match[1]);
            categoryForm.seq = seq;
        }
    }
};

const openModal = (type: typeof modalType.value, item?: Topic | TopicCategory) => {
    modalType.value = type;
    selectedItem.value = item || null;
    modalContext.value = 'topic';

    switch (type) {
        case 'add':
            modalTitle.value = 'Form Topik';
            topicForm.reset();
            topicForm.seq = props.topics.length + 1;
            break;
        case 'edit':
            modalTitle.value = 'Edit Topik';
            const topic = item as Topic;
            topicForm.title = topic.title;
            topicForm.short_title = topic.short_title;
            topicForm.icon = topic.icon;
            topicForm.seq = topic.seq;
            break;
        case 'delete':
            modalTitle.value = 'Hapus Topik';
            break;
    }

    modalOpen.value = true;
};

const openCategoryModal = (type: 'addGroup' | 'addSingle' | 'editCategory' | 'deleteCategory' | 'addChild' | 'editChild' | 'deleteChild', item?: TopicCategory) => {
    modalType.value = type;
    selectedItem.value = item || null;
    modalContext.value = 'category';

    switch (type) {
        case 'addGroup':
            modalTitle.value = 'Form Group';
            categoryForm.reset();
            categoryForm.have_child = 1;
            categoryForm.seq = props.topicCategories.length + 1;
            categoryForm.parent_id = null;
            selectedParentCategory.value = null;
            break;
        case 'addSingle':
            modalTitle.value = 'Form Single Item';
            categoryForm.reset();
            categoryForm.have_child = 0;
            categoryForm.seq = props.topicCategories.length + 1;
            categoryForm.parent_id = null;
            selectedParentCategory.value = null;
            break;
        case 'addChild':
            modalTitle.value = 'Tambah Item';
            categoryForm.reset();
            categoryForm.have_child = 0;
            categoryForm.parent_id = item!.id;
            categoryForm.seq = (item!.children?.length || 0) + 1;
            selectedParentCategory.value = item!;
            break;
        case 'editCategory':
            modalTitle.value = 'Edit Category';
            const category = item as TopicCategory;
            categoryForm.title = category.title;
            categoryForm.seq = category.seq;
            categoryForm.parent_id = category.parent_id;
            selectedParentCategory.value = null;
            break;
        case 'editChild':
            modalTitle.value = 'Edit Item';
            const child = item as TopicCategory;
            categoryForm.title = child.title;
            categoryForm.seq = child.seq;
            categoryForm.parent_id = child.parent_id;
            selectedParentCategory.value = null;
            break;
        case 'deleteCategory':
            modalTitle.value = 'Hapus Category';
            selectedParentCategory.value = null;
            break;
        case 'deleteChild':
            modalTitle.value = 'Hapus Item';
            selectedParentCategory.value = null;
            break;
    }

    modalOpen.value = true;
};

const handleTopicSubmit = () => {
    if (modalType.value === 'add') {
        topicForm.post('/topic', {
            onSuccess: () => {
                modalOpen.value = false;
                topicForm.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        topicForm.put(`/topic/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                topicForm.reset();
            },
        });
    }
};

const handleTopicDelete = () => {
    if (selectedItem.value) {
        router.delete(`/topic/${selectedItem.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const handleCategorySubmit = () => {
    if (!props.selectedTopic) return;

    if (modalType.value === 'addGroup' || modalType.value === 'addSingle' || modalType.value === 'addChild') {
        categoryForm.post(`/topic/${props.selectedTopic.id}/category`, {
            onSuccess: () => {
                modalOpen.value = false;
                categoryForm.reset();
            },
        });
    } else if ((modalType.value === 'editCategory' || modalType.value === 'editChild') && selectedItem.value) {
        categoryForm.put(`/topic/category/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                categoryForm.reset();
            },
        });
    }
};

const handleCategoryDelete = () => {
    if (selectedItem.value) {
        router.delete(`/topic/category/${selectedItem.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const selectTopic = (topicId: number) => {
    router.get('/topic', { topic_id: topicId }, { preserveState: true });
};

const navigateToDetail = (category: TopicCategory) => {
    router.get(`/topic/category/${category.id}/detail`);
};
</script>

<template>
    <Head :title="pageTitle" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <h1 class="mb-6 text-xl text-gray-600">{{ pageTitle }}</h1>

            <div class="flex flex-1 gap-6 overflow-hidden">
                <!-- Left Panel: Topics List -->
                <div class="flex flex-col overflow-hidden" :class="selectedTopic ? 'w-1/2' : 'w-full max-w-3xl mx-auto'">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">Topik</span>
                        <button
                            @click="openModal('add')"
                            class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Topics List -->
                    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                        <template v-if="topics.length">
                            <Card
                                v-for="topic in topics"
                                :key="topic.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                                :class="{ 'ring-2 ring-blue-400': selectedTopic?.id === topic.id }"
                                @click="selectTopic(topic.id)"
                            >
                                <div class="flex items-center justify-between px-4 py-3">
                                    <span class="font-medium text-gray-700">{{ topic.title }}</span>
                                    <div class="flex items-center gap-1" @click.stop>
                                        <button
                                            @click="openModal('edit', topic)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('delete', topic)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </Card>
                        </template>
                        <div v-else class="py-8 text-center text-gray-500">
                            Belum ada topik
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Topic Categories (shown when topic is selected) -->
                <div v-if="selectedTopic" class="flex w-1/2 flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">{{ topicCategoryTitle }}</span>
                        <div class="flex items-center gap-2">
                            <button
                                @click="openCategoryModal('addGroup')"
                                class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                title="Add Group"
                            >
                                <Icon icon="mdi:plus" class="h-4 w-4" />
                            </button>
                            <button
                                @click="openCategoryModal('addSingle')"
                                class="flex h-6 w-6 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                title="Add Single"
                            >
                                <Icon icon="mdi:plus" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Categories List -->
                    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                        <template v-if="topicCategories.length">
                            <Card
                                v-for="category in topicCategories"
                                :key="category.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                            >
                                <!-- Category Header -->
                                <div 
                                    class="flex items-center justify-between border-b border-gray-200 px-4 py-3"
                                    :class="category.have_child === 0 ? 'cursor-pointer hover:bg-gray-50' : ''"
                                    @click="category.have_child === 0 ? navigateToDetail(category) : null"
                                >
                                    <span class="font-medium text-gray-700">{{ category.title }}</span>
                                    <div class="flex items-center gap-1" @click.stop>
                                        <button
                                            v-if="category.have_child === 1"
                                            @click="openCategoryModal('addChild', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Tambah Item"
                                        >
                                            <Icon icon="mdi:plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openCategoryModal('editCategory', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            title="Edit Category"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openCategoryModal('deleteCategory', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            title="Hapus Category"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Children Items (only for have_child = 1) -->
                                <div v-if="category.have_child === 1 && category.children?.length">
                                    <div
                                        v-for="child in category.children"
                                        :key="child.id"
                                        class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0 cursor-pointer hover:bg-gray-50"
                                        @click="navigateToDetail(child)"
                                    >
                                        <span class="text-sm text-gray-600">{{ child.title }}</span>
                                        <div class="flex items-center gap-1" @click.stop>
                                            <button
                                                @click="openCategoryModal('editChild', child)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                title="Edit Item"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openCategoryModal('deleteChild', child)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                title="Hapus Item"
                                            >
                                                <Icon icon="mdi:delete" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </template>
                        <div v-else class="py-8 text-center text-gray-500">
                            Belum ada kategori
                        </div>
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
                    <!-- Topic Add/Edit Form -->
                    <form v-if="modalContext === 'topic' && (modalType === 'add' || modalType === 'edit')" @submit.prevent="handleTopicSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <Input v-model="topicForm.title" placeholder="Enter title" required />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                            <div class="relative">
                                <select
                                    :value="topicForm.seq"
                                    @change="selectTopicUrutan(Number(($event.target as HTMLSelectElement).value))"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option v-for="option in topicUrutanOptions" :key="option.position" :value="option.position">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'" 
                                :disabled="topicForm.processing"
                            >
                                {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Topic Delete Form -->
                    <div v-else-if="modalContext === 'topic' && modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus topik ini?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleTopicDelete">
                                Hapus
                            </Button>
                        </div>
                    </div>

                    <!-- Category Add/Edit Form -->
                    <form v-else-if="modalContext === 'category' && (modalType === 'addGroup' || modalType === 'addSingle' || modalType === 'addChild' || modalType === 'editCategory' || modalType === 'editChild')" @submit.prevent="handleCategorySubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <Input v-model="categoryForm.title" placeholder="Enter title" required />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                            <div class="relative">
                                <select
                                    :value="categoryForm.seq"
                                    @change="selectCategoryUrutan(Number(($event.target as HTMLSelectElement).value))"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option v-for="option in categoryUrutanOptions" :key="option.position" :value="option.position">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                :class="(modalType === 'editCategory' || modalType === 'editChild') ? 'bg-[#f0ad4e] hover:bg-[#eea236]' : 'bg-[#5cb85c] hover:bg-[#4cae4c]'" 
                                :disabled="categoryForm.processing"
                            >
                                {{ (modalType === 'editCategory' || modalType === 'editChild') ? 'Update' : 'Simpan' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Category Delete Form -->
                    <div v-else-if="modalContext === 'category' && (modalType === 'deleteCategory' || modalType === 'deleteChild')" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus {{ modalType === 'deleteCategory' ? 'category' : 'item' }} ini?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="handleCategoryDelete">
                                Hapus
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

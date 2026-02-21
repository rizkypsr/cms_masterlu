<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { cn } from '@/lib/utils';

interface Category {
    id: number;
    title: string;
    seq: number;
    parent_id: number | null;
    parent?: Category | null;
    topics2?: Topic2[];
}

interface Topic2 {
    id: number;
    title: string;
    seq: number;
    book_category_id?: number;
}

interface Topic2Chapter {
    id: number;
    title: string;
    seq: number;
    have_child: number;
    parent_id: number | null;
    children?: Topic2Chapter[];
}

const props = defineProps<{
    categories: Category[];
    storeUrl: string;
    chapters?: Topic2Chapter[];
    selectedTopic2?: Topic2 | null;
}>();

const page = usePage();
const user = page.props.auth?.user;

// Modal states
const modalOpen = ref(false);
const modalType = ref<'view' | 'add' | 'edit' | 'delete'>('view');
const modalTitle = ref('');
const selectedItem = ref<Category | null>(null);
const modalContext = ref<'category' | 'topic2' | 'chapter'>('category');

// Topic2 modal states
const selectedTopic2ForModal = ref<Topic2 | null>(null);

// Chapter modal states
const selectedChapter = ref<Topic2Chapter | null>(null);
const selectedChapterParent = ref<Topic2Chapter | null>(null);

// Combobox state
const comboboxOpen = ref(false);

// Form
const form = useForm({
    title: '',
    parent_id: null as number | null,
    seq: null as number | null,
});

// Topic2 form (without file fields)
const topic2Form = useForm({
    title: '',
    seq: null as number | null,
});

// Chapter form
const chapterForm = useForm({
    title: '',
    seq: null as number | null,
    have_child: 1 as number,
    parent_id: null as number | null,
});

// Urutan options for categories
const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    // Helper function to truncate text
    const truncateText = (text: string, maxLength: number = 50) => {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    };
    
    if (modalContext.value === 'topic2') {
        // For topic2 modal, use the category's topics2
        const items = selectedItem.value?.topics2 || [];
        
        items.forEach((topic2, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${truncateText(topic2.title)}`,
            });
        });
        
        const lastPosition = items.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    } else if (modalContext.value === 'chapter') {
        // For chapter modal
        let items: Topic2Chapter[] = [];
        
        if (selectedChapterParent.value) {
            // Adding/editing child chapter
            items = selectedChapterParent.value.children || [];
        } else {
            // Adding/editing parent chapter
            items = props.chapters || [];
        }
        
        items.forEach((chapter, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${truncateText(chapter.title)}`,
            });
        });
        
        const lastPosition = items.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    } else {
        // For category modal
        const items = props.categories;
        
        items.forEach((cat, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${truncateText(cat.title)}`,
            });
        });
        
        const lastPosition = items.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    }
    
    return options;
});

const selectedUrutanLabel = computed(() => {
    let seq: number | null = null;
    if (modalContext.value === 'topic2') {
        seq = topic2Form.seq;
    } else if (modalContext.value === 'chapter') {
        seq = chapterForm.seq;
    } else {
        seq = form.seq;
    }
    const option = urutanOptions.value.find(opt => opt.position === seq);
    return option?.label || 'Pilih urutan...';
});

const selectUrutan = (position: number) => {
    if (modalContext.value === 'topic2') {
        topic2Form.seq = position;
    } else if (modalContext.value === 'chapter') {
        chapterForm.seq = position;
    } else {
        form.seq = position;
    }
    comboboxOpen.value = false;
};

// Category modal functions
const openModal = (type: 'view' | 'add' | 'edit' | 'delete', item?: Category) => {
    modalContext.value = 'category';
    modalType.value = type;
    selectedItem.value = item || null;

    switch (type) {
        case 'view':
            modalTitle.value = 'Lihat Detail';
            break;
        case 'add':
            modalTitle.value = 'Tambah Kategori';
            form.reset();
            form.parent_id = null;
            form.seq = props.categories.length + 1;
            break;
        case 'edit':
            modalTitle.value = 'Edit';
            form.title = item?.title || '';
            const currentPosition = props.categories.findIndex(c => c.id === item?.id) + 1;
            form.seq = currentPosition || null;
            break;
        case 'delete':
            modalTitle.value = 'Hapus';
            break;
    }

    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        form.post(props.storeUrl, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        form.put(`/topic2/category/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/topic2/category/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};

// Topic2 modal functions
const openTopic2Modal = (type: 'add' | 'edit' | 'delete', topic2?: Topic2, category?: Category) => {
    modalContext.value = 'topic2';
    modalType.value = type;
    selectedTopic2ForModal.value = topic2 || null;
    
    if (type === 'add') {
        modalTitle.value = 'Form Topik 2';
        topic2Form.reset();
        topic2Form.seq = (category?.topics2?.length || 0) + 1;
        selectedItem.value = category || null; // Store category for submission
    } else if (type === 'edit') {
        modalTitle.value = 'Edit Topik 2';
        topic2Form.title = topic2!.title;
        // Find which category this topic2 belongs to
        const parentCategory = props.categories.find(c => c.topics2?.some(t => t.id === topic2!.id));
        const pos = (parentCategory?.topics2?.findIndex(t => t.id === topic2!.id) ?? -1) + 1;
        topic2Form.seq = pos || null;
    } else if (type === 'delete') {
        modalTitle.value = 'Hapus';
    }
    
    modalOpen.value = true;
};

const handleTopic2Submit = () => {
    const categoryId = selectedItem.value?.id; // Use selectedItem which stores the category
    if (!categoryId && modalType.value === 'add') return;
    
    if (modalType.value === 'add') {
        topic2Form.post(`/topic2/category/${categoryId}/topic2`, {
            onSuccess: () => {
                modalOpen.value = false;
                topic2Form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedTopic2ForModal.value) {
        topic2Form.put(`/topic2/${selectedTopic2ForModal.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                topic2Form.reset();
            },
        });
    }
};

const handleTopic2Delete = () => {
    if (selectedTopic2ForModal.value) {
        router.delete(`/topic2/${selectedTopic2ForModal.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};


// Chapter modal functions
const openChapterModal = (type: 'add' | 'edit' | 'delete', formType?: 'group' | 'noChild', chapter?: Topic2Chapter, parent?: Topic2Chapter) => {
    modalContext.value = 'chapter';
    modalType.value = type;
    selectedChapter.value = chapter || null;
    selectedChapterParent.value = parent || null;
    
    if (type === 'add') {
        if (formType === 'group') {
            if (parent) {
                // Green button on parent chapter: Add child chapter that CAN have children
                modalTitle.value = 'Form Chapter (with children)';
                chapterForm.reset();
                chapterForm.parent_id = parent.id;
                chapterForm.seq = (parent.children?.length || 0) + 1;
                chapterForm.have_child = 1; // Can have children
            } else {
                // Green button on header: Add root chapter that CAN have children
                modalTitle.value = 'Form Chapter (with children)';
                chapterForm.reset();
                chapterForm.seq = (props.chapters?.length || 0) + 1;
                chapterForm.have_child = 1; // Can have children
                chapterForm.parent_id = null;
            }
        } else if (formType === 'noChild') {
            if (parent) {
                // Blue button on parent chapter: Add child chapter that CANNOT have children
                modalTitle.value = 'Form Single Chapter';
                chapterForm.reset();
                chapterForm.parent_id = parent.id;
                chapterForm.seq = (parent.children?.length || 0) + 1;
                chapterForm.have_child = 0; // Cannot have children
            } else {
                // Blue button on header: Add root chapter that CANNOT have children
                modalTitle.value = 'Form Single Chapter';
                chapterForm.reset();
                chapterForm.seq = (props.chapters?.length || 0) + 1;
                chapterForm.have_child = 0; // Cannot have children
                chapterForm.parent_id = null;
            }
        }
    } else if (type === 'edit') {
        modalTitle.value = 'Edit Chapter';
        chapterForm.title = chapter!.title;
        if (chapter!.parent_id) {
            const parentChapter = props.chapters?.find(c => c.id === chapter!.parent_id);
            selectedChapterParent.value = parentChapter || null;
            const pos = (parentChapter?.children?.findIndex(c => c.id === chapter!.id) ?? -1) + 1;
            chapterForm.seq = pos || null;
        } else {
            const pos = (props.chapters?.findIndex(c => c.id === chapter!.id) ?? -1) + 1;
            chapterForm.seq = pos || null;
        }
    } else if (type === 'delete') {
        modalTitle.value = 'Hapus';
    }
    
    modalOpen.value = true;
};

const handleChapterSubmit = () => {
    const topic2Id = props.selectedTopic2?.id;
    if (!topic2Id) return;
    
    if (modalType.value === 'add') {
        chapterForm.post(`/topic2/${topic2Id}/chapter`, {
            onSuccess: () => {
                modalOpen.value = false;
                chapterForm.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedChapter.value) {
        chapterForm.put(`/topic2/chapter/${selectedChapter.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                chapterForm.reset();
            },
        });
    }
};

const handleChapterDelete = () => {
    if (selectedChapter.value) {
        router.delete(`/topic2/chapter/${selectedChapter.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const navigateToContent = (chapterId: number) => {
    router.visit(`/topic2/chapter/${chapterId}/content`);
};
</script>

<template>
    <Head title="Topik 2" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <h1 class="mb-6 text-xl text-gray-600">Topik 2</h1>

            <div class="flex flex-1 gap-6 overflow-hidden">
                <!-- Left Panel: Category List -->
                <div class="flex flex-col overflow-hidden" :class="props.selectedTopic2 ? 'w-1/2' : 'w-full max-w-3xl mx-auto'">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">Kategori</span>
                        <button
                            @click="openModal('add')"
                            class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Category List -->
                    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                        <template v-if="categories.length">
                            <Card
                                v-for="category in categories"
                                :key="category.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                            >
                                <!-- Category Header -->
                                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                    <span class="font-medium text-gray-700">{{ category.title }}</span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openTopic2Modal('add', undefined, category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Add Topic2"
                                        >
                                            <Icon icon="mdi:navigation-variant" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openTopic2Modal('add', undefined, category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Add Topic2"
                                        >
                                            <Icon icon="mdi:plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('edit', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            title="Edit"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openModal('delete', category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            title="Delete"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Topics2 (Children) -->
                                <div v-if="category.topics2?.length">
                                    <div
                                        v-for="topic2 in category.topics2"
                                        :key="topic2.id"
                                        class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0 cursor-pointer hover:bg-gray-50"
                                        :class="{ 'bg-blue-50 ring-2 ring-blue-200': props.selectedTopic2?.id === topic2.id }"
                                        @click="router.visit(`/topic2/${topic2.id}`, { preserveState: false })"
                                    >
                                        <span class="text-sm text-gray-600">{{ topic2.title }}</span>
                                        <div class="flex items-center gap-1" @click.stop>
                                            <button
                                                @click="router.visit(`/topic2/${topic2.id}`, { preserveState: false })"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                            >
                                                <Icon icon="mdi:navigation-variant" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openTopic2Modal('edit', topic2, category)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openTopic2Modal('delete', topic2)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
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

                <!-- Right Panel: Chapter List (shown when topic2 is selected) -->
                <div v-if="props.selectedTopic2" :key="`topic2-${props.selectedTopic2.id}`" class="flex w-1/2 flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">
                            {{ categories.find(c => c.id === props.selectedTopic2?.book_category_id)?.title || '' }}
                            {{ props.selectedTopic2?.book_category_id ? ' / ' : '' }}
                            {{ props.selectedTopic2?.title || '' }}
                        </span>
                        <button
                            @click="openChapterModal('add', 'group')"
                            class="flex h-6 w-6 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                            title="Add Chapter"
                        >
                            <Icon icon="mdi:plus" class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Chapter List -->
                    <div class="flex-1 space-y-4 overflow-y-auto bg-white p-4">
                        <template v-if="props.chapters?.length">
                            <Card
                                v-for="chapter in props.chapters"
                                :key="chapter.id"
                                class="overflow-hidden rounded-none border-t-4 border-t-[#f0ad4e] shadow-sm"
                            >
                                <!-- Parent Chapter Header -->
                                <div 
                                    class="flex items-center justify-between border-b border-gray-200 px-4 py-3"
                                    :class="chapter.have_child === 0 ? 'cursor-pointer hover:bg-gray-50' : ''"
                                    @click="chapter.have_child === 0 ? navigateToContent(chapter.id) : null"
                                >
                                    <span class="font-medium text-gray-700">{{ chapter.title }}</span>
                                    <div class="flex items-center gap-1" @click.stop>
                                        <button
                                            v-if="chapter.have_child === 1"
                                            @click="openChapterModal('add', 'group', undefined, chapter)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Add Chapter (with children)"
                                        >
                                            <Icon icon="mdi:plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="chapter.have_child === 1"
                                            @click="openChapterModal('add', 'noChild', undefined, chapter)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                            title="Add Single Chapter (no children)"
                                        >
                                            <Icon icon="mdi:plus" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openChapterModal('edit', undefined, chapter)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            title="Edit"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openChapterModal('delete', undefined, chapter)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                            title="Delete"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Child Chapters (if any) -->
                                <div v-if="chapter.children?.length" class="space-y-4 p-4">
                                    <template v-for="child in chapter.children" :key="child.id">
                                        <!-- Child with have_child = 1: Display as Card -->
                                        <Card v-if="child.have_child === 1" class="overflow-hidden rounded-none border-t-4 border-t-[#5bc0de] shadow-sm">
                                            <!-- Child Chapter Header -->
                                            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                                <span class="font-medium text-gray-700">{{ child.title }}</span>
                                                <div class="flex items-center gap-1" @click.stop>
                                                    <button
                                                        @click="openChapterModal('add', 'noChild', undefined, child)"
                                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                                        title="Add final chapter"
                                                    >
                                                        <Icon icon="mdi:plus" class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        @click="openChapterModal('edit', undefined, child)"
                                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                        title="Edit"
                                                    >
                                                        <Icon icon="mdi:pencil" class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        @click="openChapterModal('delete', undefined, child)"
                                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                        title="Delete"
                                                    >
                                                        <Icon icon="mdi:delete" class="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Grandchildren (if any) -->
                                            <div v-if="child.children?.length">
                                                <div
                                                    v-for="grandchild in child.children"
                                                    :key="grandchild.id"
                                                    class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0 cursor-pointer hover:bg-gray-50"
                                                    @click="navigateToContent(grandchild.id)"
                                                >
                                                    <span class="text-sm text-gray-600">{{ grandchild.title }}</span>
                                                    <div class="flex items-center gap-1" @click.stop>
                                                        <button
                                                            @click="openChapterModal('edit', undefined, grandchild)"
                                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                            title="Edit"
                                                        >
                                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            @click="openChapterModal('delete', undefined, grandchild)"
                                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                            title="Delete"
                                                        >
                                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </Card>

                                        <!-- Child with have_child = 0: Display as simple list item -->
                                        <div
                                            v-else
                                            class="flex items-center justify-between border-b border-gray-100 px-4 py-2 cursor-pointer hover:bg-gray-50"
                                            @click="navigateToContent(child.id)"
                                        >
                                            <span class="text-sm text-gray-600">{{ child.title }}</span>
                                            <div class="flex items-center gap-1" @click.stop>
                                                <button
                                                    @click="openChapterModal('edit', undefined, child)"
                                                    class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                    title="Edit"
                                                >
                                                    <Icon icon="mdi:pencil" class="h-4 w-4" />
                                                </button>
                                                <button
                                                    @click="openChapterModal('delete', undefined, child)"
                                                    class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                    title="Delete"
                                                >
                                                    <Icon icon="mdi:delete" class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </Card>
                        </template>
                        <div v-else class="py-8 text-center text-gray-500">
                            Belum ada chapter
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] max-w-[95vw] translate-y-0 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="truncate">{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="w-full overflow-hidden py-4">
                    <!-- View Modal -->
                    <div v-if="modalType === 'view'">
                        <p class="text-sm text-gray-600">
                            Detail: <strong>{{ selectedItem?.title }}</strong>
                        </p>
                    </div>

                    <!-- Category Add/Edit Modal -->
                    <form v-else-if="(modalType === 'add' || modalType === 'edit') && modalContext === 'category'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nama Kategori</label>
                            <Input v-model="form.title" placeholder="Masukkan nama kategori" />
                        </div>
                        
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                            <Popover :open="comboboxOpen" @update:open="comboboxOpen = $event">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="comboboxOpen"
                                        class="w-full justify-between overflow-hidden"
                                    >
                                        <span class="truncate">{{ selectedUrutanLabel }}</span>
                                        <Icon icon="mdi:unfold-more-horizontal" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                    <Command>
                                        <CommandInput placeholder="Cari urutan..." />
                                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="option in urutanOptions"
                                                    :key="option.position"
                                                    :value="option.label"
                                                    @select="selectUrutan(option.position)"
                                                    class="overflow-hidden"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="cn('mr-2 h-4 w-4', form.seq === option.position ? 'opacity-100' : 'opacity-0')"
                                                    />
                                                    <span class="truncate">{{ option.label }}</span>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
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

                    <!-- Topic2 Add/Edit Modal -->
                    <form v-else-if="(modalType === 'add' || modalType === 'edit') && modalContext === 'topic2'" @submit.prevent="handleTopic2Submit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Judul <span class="text-red-500">*</span></label>
                            <Input v-model="topic2Form.title" placeholder="Masukkan judul" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                            <Popover :open="comboboxOpen" @update:open="comboboxOpen = $event">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="comboboxOpen"
                                        class="w-full justify-between overflow-hidden"
                                    >
                                        <span class="truncate">{{ selectedUrutanLabel }}</span>
                                        <Icon icon="mdi:unfold-more-horizontal" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                    <Command>
                                        <CommandInput placeholder="Cari urutan..." />
                                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="option in urutanOptions"
                                                    :key="option.position"
                                                    :value="option.label"
                                                    @select="selectUrutan(option.position)"
                                                    class="overflow-hidden"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="cn('mr-2 h-4 w-4', topic2Form.seq === option.position ? 'opacity-100' : 'opacity-0')"
                                                    />
                                                    <span class="truncate">{{ option.label }}</span>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'" 
                                :disabled="topic2Form.processing"
                            >
                                {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Chapter Add/Edit Modal -->
                    <form v-else-if="(modalType === 'add' || modalType === 'edit') && modalContext === 'chapter'" @submit.prevent="handleChapterSubmit" class="w-full space-y-4">
                        <div class="w-full">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Chapter</label>
                            <Input v-model="chapterForm.title" placeholder="Masukkan chapter" class="w-full" />
                        </div>

                        <div class="w-full">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
                            <Popover :open="comboboxOpen" @update:open="comboboxOpen = $event">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="comboboxOpen"
                                        class="w-full justify-between overflow-hidden"
                                    >
                                        <span class="truncate">{{ selectedUrutanLabel }}</span>
                                        <Icon icon="mdi:unfold-more-horizontal" class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
                                    <Command>
                                        <CommandInput placeholder="Cari urutan..." />
                                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="option in urutanOptions"
                                                    :key="option.position"
                                                    :value="option.label"
                                                    @select="selectUrutan(option.position)"
                                                    class="overflow-hidden"
                                                >
                                                    <Icon
                                                        icon="mdi:check"
                                                        :class="cn('mr-2 h-4 w-4', chapterForm.seq === option.position ? 'opacity-100' : 'opacity-0')"
                                                    />
                                                    <span class="truncate">{{ option.label }}</span>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                :class="modalType === 'add' ? 'bg-[#5cb85c] hover:bg-[#4cae4c]' : 'bg-[#f0ad4e] hover:bg-[#eea236]'" 
                                :disabled="chapterForm.processing"
                            >
                                {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Delete Modal -->
                    <div v-else-if="modalType === 'delete'" class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <strong>{{ modalContext === 'chapter' ? selectedChapter?.title : (modalContext === 'topic2' ? selectedTopic2ForModal?.title : selectedItem?.title) }}</strong>?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="modalContext === 'chapter' ? handleChapterDelete() : (modalContext === 'topic2' ? handleTopic2Delete() : handleDelete())">
                                Hapus
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

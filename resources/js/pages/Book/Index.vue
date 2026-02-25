<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import InputError from '@/components/InputError.vue';
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
    books?: Book[];
}

interface Book {
    id: number;
    title: string;
    seq: number;
    book_category_id?: number;
    url?: string;
    url_pdf?: string;
}

interface BookChapter {
    id: number;
    title: string;
    seq: number;
    have_child: number;
    parent_id: number | null;
    children?: BookChapter[];
}

const props = defineProps<{
    categories: Category[];
    storeUrl: string;
    chapters?: BookChapter[];
    selectedBook?: Book | null;
}>();

const page = usePage();
const user = page.props.auth?.user;

// Modal states
const modalOpen = ref(false);
const modalType = ref<'view' | 'add' | 'edit' | 'delete'>('view');
const modalTitle = ref('');
const selectedItem = ref<Category | null>(null);
const modalContext = ref<'category' | 'book' | 'chapter'>('category');

// Book modal states
const selectedBookForModal = ref<Book | null>(null);

// Chapter modal states
const selectedChapter = ref<BookChapter | null>(null);
const selectedChapterParent = ref<BookChapter | null>(null);

// Combobox state
const comboboxOpen = ref(false);

// Form
const form = useForm({
    title: '',
    parent_id: null as number | null,
    seq: null as number | null,
});

// Book form
const bookForm = useForm({
    title: '',
    url: null as File | null,
    url_pdf: null as File | null,
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
    
    if (modalContext.value === 'book') {
        // For book modal, use the category's books
        const items = selectedItem.value?.books || [];
        
        items.forEach((book, index) => {
            const position = index + 1;
            options.push({
                position,
                label: `${position} - ${truncateText(book.title)}`,
            });
        });
        
        const lastPosition = items.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    } else if (modalContext.value === 'chapter') {
        // For chapter modal
        let items: BookChapter[] = [];
        
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
    if (modalContext.value === 'book') {
        seq = bookForm.seq;
    } else if (modalContext.value === 'chapter') {
        seq = chapterForm.seq;
    } else {
        seq = form.seq;
    }
    const option = urutanOptions.value.find(opt => opt.position === seq);
    return option?.label || 'Pilih urutan...';
});

const selectUrutan = (position: number) => {
    if (modalContext.value === 'book') {
        bookForm.seq = position;
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
        form.put(`/book/category/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/book/category/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};

// Book modal functions
const openBookModal = (type: 'add' | 'edit' | 'delete', book?: Book, category?: Category) => {
    modalContext.value = 'book';
    modalType.value = type;
    selectedBookForModal.value = book || null;
    
    if (type === 'add') {
        modalTitle.value = 'Form Sub Buku';
        bookForm.reset();
        bookForm.seq = (category?.books?.length || 0) + 1;
        selectedItem.value = category || null; // Store category for submission
    } else if (type === 'edit') {
        modalTitle.value = 'Edit Sub Buku';
        bookForm.title = book!.title;
        bookForm.url = null; // File will be re-uploaded if changed
        bookForm.url_pdf = null; // File will be re-uploaded if changed
        // Find which category this book belongs to
        const parentCategory = props.categories.find(c => c.books?.some(b => b.id === book!.id));
        selectedItem.value = parentCategory || null; // Store category for urutan options
        const pos = (parentCategory?.books?.findIndex(b => b.id === book!.id) ?? -1) + 1;
        bookForm.seq = pos || null;
    } else if (type === 'delete') {
        modalTitle.value = 'Hapus';
    }
    
    modalOpen.value = true;
};

const handleBookSubmit = () => {
    const categoryId = selectedItem.value?.id; // Use selectedItem which stores the category
    if (!categoryId && modalType.value === 'add') return;
    
    if (modalType.value === 'add') {
        bookForm.post(`/book/category/${categoryId}/book`, {
            forceFormData: true,
            onSuccess: () => {
                modalOpen.value = false;
                bookForm.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedBookForModal.value) {
        bookForm
            .transform((data) => ({
                ...data,
                _method: 'put',
            }))
            .post(`/book/${selectedBookForModal.value.id}`, {
                forceFormData: true,
                onSuccess: () => {
                    modalOpen.value = false;
                    bookForm.reset();
                },
            });
    }
};

const handleBookCoverChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        bookForm.url = target.files[0];
    }
};

const handleBookPdfChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        bookForm.url_pdf = target.files[0];
    }
};

const handleBookDelete = () => {
    if (selectedBookForModal.value) {
        router.delete(`/book/${selectedBookForModal.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};


// Chapter modal functions
const openChapterModal = (type: 'add' | 'edit' | 'delete', formType?: 'group' | 'noChild', chapter?: BookChapter, parent?: BookChapter) => {
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
        
        // Determine if editing a child or parent chapter
        if (chapter!.parent_id) {
            // Editing a child chapter - find parent and position
            const parentChapter = props.chapters?.find(c => c.id === chapter!.parent_id);
            if (parentChapter) {
                selectedChapterParent.value = parentChapter;
                const pos = (parentChapter.children?.findIndex(c => c.id === chapter!.id) ?? -1) + 1;
                chapterForm.seq = pos || null;
            } else {
                // Parent not found in root chapters, might be a grandchild
                // Search in all children
                for (const rootChapter of props.chapters || []) {
                    const foundParent = rootChapter.children?.find(c => c.id === chapter!.parent_id);
                    if (foundParent) {
                        selectedChapterParent.value = foundParent;
                        const pos = (foundParent.children?.findIndex(c => c.id === chapter!.id) ?? -1) + 1;
                        chapterForm.seq = pos || null;
                        break;
                    }
                }
            }
        } else {
            // Editing a root chapter
            selectedChapterParent.value = null;
            const pos = (props.chapters?.findIndex(c => c.id === chapter!.id) ?? -1) + 1;
            chapterForm.seq = pos || null;
        }
    } else if (type === 'delete') {
        modalTitle.value = 'Hapus';
    }
    
    modalOpen.value = true;
};

const handleChapterSubmit = () => {
    const bookId = props.selectedBook?.id;
    if (!bookId) return;
    
    if (modalType.value === 'add') {
        chapterForm.post(`/book/${bookId}/chapter`, {
            onSuccess: () => {
                modalOpen.value = false;
                chapterForm.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedChapter.value) {
        chapterForm.put(`/book/chapter/${selectedChapter.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                chapterForm.reset();
            },
        });
    }
};

const handleChapterDelete = () => {
    if (selectedChapter.value) {
        router.delete(`/book/chapter/${selectedChapter.value.id}`, {
            onSuccess: () => modalOpen.value = false,
        });
    }
};

const navigateToContent = (chapterId: number) => {
    window.open(`/book/chapter/${chapterId}/content`, '_blank');
};

// Scroll preservation
const leftPanelScroll = ref<HTMLElement | null>(null);

onMounted(() => {
    if (leftPanelScroll.value) {
        const savedScroll = sessionStorage.getItem('book-category-left-panel');
        if (savedScroll) {
            leftPanelScroll.value.scrollTop = parseInt(savedScroll);
        }
    }
});

const saveLeftPanelScroll = () => {
    if (leftPanelScroll.value) {
        sessionStorage.setItem('book-category-left-panel', leftPanelScroll.value.scrollTop.toString());
    }
};
</script>

<template>
    <Head title="Buku" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <h1 class="mb-6 text-xl text-gray-600">Buku</h1>

            <div class="flex flex-1 gap-6 overflow-hidden">
                <!-- Left Panel: Category List -->
                <div class="flex flex-col overflow-hidden" :class="props.selectedBook ? 'w-1/2' : 'w-full max-w-3xl mx-auto'">
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
                    <div ref="leftPanelScroll" class="flex-1 space-y-4 overflow-y-auto bg-white p-4" @scroll="saveLeftPanelScroll">
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
                                            @click="openBookModal('add', undefined, category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Add Book"
                                        >
                                            <Icon icon="mdi:navigation-variant" class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openBookModal('add', undefined, category)"
                                            class="flex h-7 w-7 items-center justify-center rounded bg-[#5cb85c] text-white hover:bg-[#4cae4c]"
                                            title="Add Book"
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

                                <!-- Books (Children) -->
                                <div v-if="category.books?.length">
                                    <div
                                        v-for="book in category.books"
                                        :key="book.id"
                                        class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0 cursor-pointer hover:bg-gray-50"
                                        :class="{ 'bg-blue-50 ring-2 ring-blue-200': props.selectedBook?.id === book.id }"
                                        @click="router.visit(`/book/${book.id}`, { preserveScroll: true })"
                                    >
                                        <span class="text-sm text-gray-600">{{ book.title }}</span>
                                        <div class="flex items-center gap-1" @click.stop>
                                            <button
                                                @click="router.visit(`/book/${book.id}`, { preserveScroll: true })"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#5bc0de] text-white hover:bg-[#46b8da]"
                                            >
                                                <Icon icon="mdi:navigation-variant" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openBookModal('edit', book, category)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openBookModal('delete', book)"
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

                <!-- Right Panel: Chapter List (shown when book is selected) -->
                <div v-if="props.selectedBook" :key="`book-${props.selectedBook.id}`" class="flex w-1/2 flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t bg-[#f0ad4e] px-4 py-2 text-white">
                        <span class="font-medium">
                            {{ categories.find(c => c.id === props.selectedBook?.book_category_id)?.title || '' }}
                            {{ props.selectedBook?.book_category_id ? ' / ' : '' }}
                            {{ props.selectedBook?.title || '' }}
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

                    <!-- Book Add/Edit Modal -->
                    <form v-else-if="(modalType === 'add' || modalType === 'edit') && modalContext === 'book'" @submit.prevent="handleBookSubmit" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Judul <span class="text-red-500">*</span></label>
                            <Input v-model="bookForm.title" placeholder="Masukkan judul" />
                            <InputError :message="bookForm.errors.title" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Foto Cover <span class="text-red-500">*</span></label>
                            <input 
                                type="file" 
                                accept="image/*"
                                @change="handleBookCoverChange"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                            <p class="mt-1 text-xs text-gray-500">Upload gambar cover buku (JPG, PNG, max 2MB)</p>
                            <InputError :message="bookForm.errors.url" />
                            <div v-if="modalType === 'edit' && selectedBookForModal?.url" class="mt-2">
                                <p class="text-xs text-gray-500">Current: {{ selectedBookForModal.url }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">File PDF</label>
                            <input 
                                type="file" 
                                accept=".pdf"
                                @change="handleBookPdfChange"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                            <p class="mt-1 text-xs text-gray-500">Upload file PDF buku (max 10MB)</p>
                            <InputError :message="bookForm.errors.url_pdf" />
                            <div v-if="modalType === 'edit' && selectedBookForModal?.url_pdf" class="mt-2">
                                <p class="text-xs text-gray-500">Current: {{ selectedBookForModal.url_pdf }}</p>
                            </div>
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
                                                        :class="cn('mr-2 h-4 w-4', bookForm.seq === option.position ? 'opacity-100' : 'opacity-0')"
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
                                :disabled="bookForm.processing"
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
                            Apakah Anda yakin ingin menghapus <strong>{{ modalContext === 'chapter' ? selectedChapter?.title : (modalContext === 'book' ? selectedBookForModal?.title : selectedItem?.title) }}</strong>?
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button class="bg-[#d9534f] hover:bg-[#d43f3a]" @click="modalContext === 'chapter' ? handleChapterDelete() : (modalContext === 'book' ? handleBookDelete() : handleDelete())">
                                Hapus
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

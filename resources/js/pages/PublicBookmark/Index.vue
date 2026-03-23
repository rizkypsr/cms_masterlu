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
import { useToast } from '@/components/ui/toast';

interface Bookmark {
    id: number;
    title: string;
    type: number;
    data: string | null;
    user_name: string;
}

interface BookmarkWithPengguna {
    id: number;
    title: string;
    type: number;
    data: string | null;
    pengguna: {
        id: number;
        name: string;
    } | null;
}

interface PublicBookmark {
    id: number;
    bookmark_id: number;
    seq: number;
    is_pinned: boolean;
    bookmark: BookmarkWithPengguna | null;
}

const props = defineProps<{
    publicBookmarks: PublicBookmark[];
    allBookmarks: Bookmark[];
}>();

const page = usePage();
const user = page.props.auth?.user;
const { toast } = useToast();

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const modalTitle = ref('');
const selectedItem = ref<PublicBookmark | null>(null);

// Search for bookmarks
const searchQuery = ref('');

const filteredBookmarks = computed(() => {
    if (!searchQuery.value) return props.allBookmarks;
    const query = searchQuery.value.toLowerCase();
    return props.allBookmarks.filter(b => 
        b.title.toLowerCase().includes(query) ||
        b.user_name.toLowerCase().includes(query)
    );
});

// Helper function to parse page from bookmark data
const getPageFromData = (bookmark: Bookmark | BookmarkWithPengguna): number | null => {
    if (!bookmark.data || (bookmark.type !== 3 && bookmark.type !== 5)) {
        return null;
    }
    try {
        const data = JSON.parse(bookmark.data);
        return data.page || null;
    } catch {
        return null;
    }
};

// Helper function to format title with page
const formatTitle = (bookmark: BookmarkWithPengguna | null): string => {
    if (!bookmark) return 'Untitled';
    const page = getPageFromData(bookmark);
    if (page) {
        return `${bookmark.title} - Halaman ${page}`;
    }
    return bookmark.title;
};

// Form
const form = useForm({
    bookmark_id: null as number | null,
    seq: null as number | null,
});

const urutanOptions = computed(() => {
    const options: { position: number; label: string }[] = [];
    
    if (modalType.value === 'add') {
        props.publicBookmarks.forEach((item, index) => {
            const position = index + 1;
            const title = item.bookmark?.title || 'Untitled';
            options.push({
                position,
                label: `${position} - ${title}`,
            });
        });
        const lastPosition = props.publicBookmarks.length + 1;
        options.push({
            position: lastPosition,
            label: `${lastPosition} - (Terakhir)`,
        });
    } else if (modalType.value === 'edit') {
        props.publicBookmarks.forEach((item, index) => {
            const position = index + 1;
            const title = item.bookmark?.title || 'Untitled';
            options.push({
                position,
                label: `${position} - ${title}`,
            });
        });
    }
    
    return options;
});

const openModal = (type: typeof modalType.value, item?: PublicBookmark) => {
    modalType.value = type;
    selectedItem.value = item || null;

    switch (type) {
        case 'add':
            modalTitle.value = 'Tambah Bookmark';
            form.reset();
            searchQuery.value = '';
            form.seq = props.publicBookmarks.length + 1;
            break;
        case 'edit':
            modalTitle.value = 'Edit Urutan';
            form.seq = item!.seq;
            break;
        case 'delete':
            modalTitle.value = 'Hapus Bookmark';
            break;
    }

    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        form.post('/public-bookmark', {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
                toast({
                    title: 'Berhasil',
                    description: 'Bookmark berhasil ditambahkan',
                });
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Gagal menambahkan bookmark',
                    variant: 'destructive',
                });
            },
        });
    } else if (modalType.value === 'edit' && selectedItem.value) {
        form.put(`/public-bookmark/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
                toast({
                    title: 'Berhasil',
                    description: 'Urutan berhasil diupdate',
                });
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Gagal mengupdate urutan',
                    variant: 'destructive',
                });
            },
        });
    }
};

const handleDelete = () => {
    if (selectedItem.value) {
        router.delete(`/public-bookmark/${selectedItem.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                toast({
                    title: 'Berhasil',
                    description: 'Bookmark berhasil dihapus',
                });
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Gagal menghapus bookmark',
                    variant: 'destructive',
                });
            },
        });
    }
};

const togglePin = (item: PublicBookmark) => {
    router.post(`/public-bookmark/${item.id}/toggle-pin`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: 'Berhasil',
                description: item.is_pinned ? 'Bookmark berhasil di-unpin' : 'Bookmark berhasil di-pin',
            });
        },
    });
};
</script>

<template>
    <Head title="Bookmark Publik" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-600">Bookmark Publik</span>
                    <Button 
                        @click="openModal('add')"
                        class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        size="sm"
                    >
                        <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                        Tambah Bookmark
                    </Button>
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-auto">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr class="border-b border-gray-200">
                                <th class="w-20 px-4 py-2 text-left text-sm font-medium text-gray-600"></th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Title</th>
                                <th class="w-48 px-4 py-2 text-left text-sm font-medium text-gray-600">User</th>
                                <th class="w-24 px-4 py-2 text-center text-sm font-medium text-gray-600">Pinned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="item in publicBookmarks" 
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
                                <td class="px-4 py-2 text-sm text-gray-600">{{ formatTitle(item.bookmark) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ item.bookmark?.pengguna?.name || '-' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <button
                                        @click="togglePin(item)"
                                        :class="[
                                            'flex h-6 w-6 items-center justify-center rounded mx-auto',
                                            item.is_pinned ? 'bg-[#f0ad4e] text-white' : 'bg-gray-200 text-gray-500'
                                        ]"
                                    >
                                        <Icon icon="mdi:pin" class="h-3 w-3" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="publicBookmarks.length === 0">
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
                        Total {{ publicBookmarks.length }} bookmark
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="top-[10%] translate-y-0 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                </DialogHeader>

                <div class="py-4">
                    <!-- Add Form -->
                    <form v-if="modalType === 'add'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <Label>Cari Bookmark</Label>
                            <Input 
                                v-model="searchQuery"
                                placeholder="Cari berdasarkan title atau user..."
                                class="mb-2"
                            />
                        </div>

                        <div>
                            <Label>Pilih Bookmark</Label>
                            <div class="max-h-64 overflow-y-auto rounded border border-gray-300">
                                <div
                                    v-for="bookmark in filteredBookmarks"
                                    :key="bookmark.id"
                                    @click="form.bookmark_id = bookmark.id"
                                    :class="[
                                        'cursor-pointer border-b border-gray-100 px-3 py-2 hover:bg-gray-50',
                                        form.bookmark_id === bookmark.id ? 'bg-blue-50' : ''
                                    ]"
                                >
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ bookmark.title }}
                                        <span v-if="getPageFromData(bookmark)">
                                            - Halaman {{ getPageFromData(bookmark) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500">{{ bookmark.user_name }}</div>
                                </div>
                                <div v-if="filteredBookmarks.length === 0" class="px-3 py-4 text-center text-sm text-gray-500">
                                    Tidak ada bookmark ditemukan
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                            <Button 
                                type="submit" 
                                class="bg-[#5cb85c] hover:bg-[#4cae4c]" 
                                :disabled="form.processing || !form.bookmark_id"
                            >
                                Simpan
                            </Button>
                        </div>
                    </form>

                    <!-- Edit Form -->
                    <form v-else-if="modalType === 'edit'" @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <Label>Urutan</Label>
                            <select
                                v-model="form.seq"
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
                            Apakah Anda yakin ingin menghapus bookmark ini?
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

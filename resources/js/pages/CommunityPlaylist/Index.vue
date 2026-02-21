<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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

interface User {
    id: number;
    name: string;
    email: string;
}

interface PlaylistItem {
    id: number;
    type: number;
    data: any;
    seq: number;
    type_name: string;
}

interface Playlist {
    id: number;
    user_id: number;
    title: string;
    description: string | null;
    is_pinned: boolean;
    created_at: string;
    user: User;
    items: PlaylistItem[];
    item_count: number;
}

interface PaginatedPlaylists {
    data: Playlist[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    playlists: PaginatedPlaylists;
    filters: {
        search?: string;
        pinned?: boolean;
    };
}>();

const page = usePage();
const user = page.props.auth?.user;

const searchQuery = ref(props.filters.search || '');
const modalOpen = ref(false);
const modalType = ref<'edit'>('edit');
const selectedPlaylist = ref<Playlist | null>(null);
const editForm = ref({
    title: '',
    description: '',
});

const handleSearch = () => {
    router.get('/community-playlist', {
        search: searchQuery.value,
        pinned: props.filters.pinned,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const togglePin = (playlist: Playlist) => {
    router.post(`/community-playlist/${playlist.id}/toggle-pin`, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openEditModal = (playlist: Playlist) => {
    selectedPlaylist.value = playlist;
    editForm.value = {
        title: playlist.title,
        description: playlist.description || '',
    };
    modalType.value = 'edit';
    modalOpen.value = true;
};

const handleEdit = () => {
    if (selectedPlaylist.value) {
        router.put(`/community-playlist/${selectedPlaylist.value.id}`, editForm.value, {
            onSuccess: () => {
                modalOpen.value = false;
                selectedPlaylist.value = null;
            },
        });
    }
};

const viewPlaylist = (playlistId: number) => {
    router.visit(`/community-playlist/${playlistId}`);
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getTypeColor = (type: number) => {
    switch (type) {
        case 1: return 'bg-purple-100 text-purple-800'; // Audio
        case 2: return 'bg-blue-100 text-blue-800';     // Video
        case 3: return 'bg-green-100 text-green-800';   // Book
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Community Playlist" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-xl text-gray-600">Community Playlist</h1>
            </div>

            <!-- Search Bar -->
            <div class="mb-4">
                <div class="flex gap-2">
                    <Input
                        v-model="searchQuery"
                        placeholder="Search by title or user name..."
                        class="flex-1 bg-white"
                        @keyup.enter="handleSearch"
                    />
                    <Button @click="handleSearch" class="bg-[#5cb85c] hover:bg-[#4cae4c]">
                        <Icon icon="mdi:magnify" class="h-5 w-5" />
                    </Button>
                </div>
            </div>

            <!-- Playlists List -->
            <div class="flex-1 overflow-y-auto bg-white p-4">
                <div v-if="playlists.data.length" class="space-y-3">
                    <Card
                        v-for="playlist in playlists.data"
                        :key="playlist.id"
                        class="relative overflow-hidden rounded-lg border shadow-sm transition-shadow hover:shadow-md"
                    >
                        <div class="flex items-center gap-4 px-4">
                            <!-- Left Section: Playlist Info -->
                            <div class="flex-1">
                                <div class="mb-2 flex items-center gap-2">
                                    <h3 class="text-lg font-semibold text-gray-800">{{ playlist.title }}</h3>
                                    <span
                                        v-if="playlist.is_pinned"
                                        class="rounded-full bg-yellow-400 px-2 py-1 text-xs font-semibold text-yellow-900"
                                    >
                                        <Icon icon="mdi:pin" class="inline h-3 w-3" /> Pinned
                                    </span>
                                </div>

                                <!-- Description -->
                                <p v-if="playlist.description" class="mb-2 line-clamp-1 text-sm text-gray-600">
                                    {{ playlist.description }}
                                </p>

                                <!-- Meta Info -->
                                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                    <div class="flex items-center gap-1">
                                        <Icon icon="mdi:account" class="h-4 w-4" />
                                        <span>{{ playlist.user?.name || 'Unknown User' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Icon icon="mdi:playlist-music" class="h-4 w-4" />
                                        <span>{{ playlist.items.length }} items</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Icon icon="mdi:calendar" class="h-4 w-4" />
                                        <span>{{ formatDate(playlist.created_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Section: Actions -->
                            <div class="flex gap-2">
                                <Button
                                    @click="viewPlaylist(playlist.id)"
                                    variant="outline"
                                    size="sm"
                                >
                                    <Icon icon="mdi:eye" class="mr-1 h-4 w-4" />
                                    View
                                </Button>
                                <Button
                                    @click="openEditModal(playlist)"
                                    class="bg-[#5bc0de] hover:bg-[#46b8da]"
                                    size="sm"
                                >
                                    <Icon icon="mdi:pencil" class="h-4 w-4" />
                                </Button>
                                <Button
                                    @click="togglePin(playlist)"
                                    :class="playlist.is_pinned ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-gray-500 hover:bg-gray-600'"
                                    size="sm"
                                >
                                    <Icon :icon="playlist.is_pinned ? 'mdi:pin-off' : 'mdi:pin'" class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </Card>
                </div>

                <div v-else class="py-12 text-center text-gray-500">
                    <Icon icon="mdi:playlist-music-outline" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                    <p class="text-lg">No playlists found</p>
                    <p class="text-sm">Try adjusting your search criteria</p>
                </div>

                <!-- Pagination -->
                <div v-if="playlists.last_page > 1" class="mt-6 flex justify-center gap-2">
                    <Button
                        v-for="pageNum in playlists.last_page"
                        :key="pageNum"
                        @click="router.get('/community-playlist', { page: pageNum, search: searchQuery })"
                        :class="pageNum === playlists.current_page ? 'bg-[#f0ad4e]' : 'bg-gray-300'"
                        size="sm"
                    >
                        {{ pageNum }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Playlist</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Title</label>
                        <Input
                            v-model="editForm.title"
                            placeholder="Playlist title"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Description</label>
                        <textarea
                            v-model="editForm.description"
                            placeholder="Playlist description (optional)"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            rows="4"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="modalOpen = false">Cancel</Button>
                        <Button class="bg-[#5cb85c] hover:bg-[#4cae4c]" @click="handleEdit">
                            Save Changes
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

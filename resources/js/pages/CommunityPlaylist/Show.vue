<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface User {
    id: number;
    name: string;
    email: string;
}

interface PlaylistItem {
    id: number;
    type: number;
    data: {
        audioId?: number;
        subtitleId?: number;
        videoId?: number;
        video_category_id?: number;
        book_id?: number;
        contentId?: number;
        page?: number;
        lang?: string;
    };
    seq: number;
    type_name: string;
    display_title?: string;
    navigate_url?: string;
    content?: any;
    created_at: string;
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
}

const props = defineProps<{
    playlist: Playlist;
}>();

const page = usePage();
const user = page.props.auth?.user;

const goBack = () => {
    router.visit('/community-playlist');
};

const getTypeIcon = (type: number) => {
    switch (type) {
        case 1: return 'mdi:volume-high';           // Audio
        case 2: return 'mdi:play-circle';           // Video
        case 3: return 'mdi:book-open-page-variant'; // Book
        default: return 'mdi:help-circle';
    }
};

const getTypeColor = (type: number) => {
    switch (type) {
        case 1: return 'bg-purple-100 text-purple-800'; // Audio
        case 2: return 'bg-blue-100 text-blue-800';     // Video
        case 3: return 'bg-green-100 text-green-800';   // Book
        default: return 'bg-gray-100 text-gray-800';
    }
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="playlist.title" />

    <DashboardLayout :user="user">
        <div class="flex h-[calc(100vh-48px)] flex-col overflow-hidden bg-[#d3dce6] p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Button @click="goBack" variant="outline" size="sm">
                    <Icon icon="mdi:arrow-left" class="h-5 w-5" />
                </Button>
                <h1 class="text-xl text-gray-600">Playlist Details</h1>
            </div>

            <div class="flex-1 overflow-y-auto">
                <!-- Playlist Info Card -->
                <Card class="mb-6 p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="mb-2 flex items-center gap-2">
                                <h2 class="text-2xl font-bold text-gray-800">{{ playlist.title }}</h2>
                                <span
                                    v-if="playlist.is_pinned"
                                    class="rounded-full bg-yellow-400 px-3 py-1 text-xs font-semibold text-yellow-900"
                                >
                                    <Icon icon="mdi:pin" class="inline h-3 w-3" /> Pinned
                                </span>
                            </div>

                            <p v-if="playlist.description" class="mb-4 text-gray-600">
                                {{ playlist.description }}
                            </p>

                            <div class="flex items-center gap-6 text-sm text-gray-500">
                                <div class="flex items-center gap-2">
                                    <Icon icon="mdi:account" class="h-5 w-5" />
                                    <span>{{ playlist.user?.name || 'Unknown User' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Icon icon="mdi:playlist-music" class="h-5 w-5" />
                                    <span>{{ playlist.items.length }} items</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Playlist Items -->
                <Card class="p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800">Playlist Items</h3>

                    <div v-if="playlist.items.length" class="space-y-3">
                        <div
                            v-for="(item, index) in playlist.items"
                            :key="item.id"
                            :class="[
                                'flex items-center gap-4 rounded-lg border p-4 transition-all',
                                item.navigate_url 
                                    ? 'cursor-pointer hover:bg-gray-50 hover:shadow-md' 
                                    : 'bg-gray-50 opacity-60'
                            ]"
                            @click="item.navigate_url && router.visit(item.navigate_url)"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100">
                                <span class="text-sm font-semibold text-gray-600">{{ index + 1 }}</span>
                            </div>

                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <Icon :icon="getTypeIcon(item.type)" class="h-6 w-6 text-gray-600" />
                                    <span class="text-sm text-black">{{ item.display_title }}</span>
                                </div>
                            </div>

                            <!-- Navigation indicator -->
                            <div v-if="item.navigate_url" class="flex items-center text-gray-400">
                                <Icon icon="mdi:chevron-right" class="h-6 w-6" />
                            </div>
                            <div v-else class="flex items-center text-gray-400">
                                <Icon icon="mdi:alert-circle" class="h-5 w-5" title="Content not found" />
                            </div>
                        </div>
                    </div>

                    <div v-else class="py-12 text-center text-gray-500">
                        <Icon icon="mdi:playlist-remove" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                        <p class="text-lg">No items in this playlist</p>
                    </div>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>

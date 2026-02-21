<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard, logout } from '@/routes';

defineProps<{
    user: {
        name: string;
        username: string;
    };
}>();

const mobileMenuOpen = ref(false);
const openMobileDropdown = ref<string | null>(null);

const toggleMobileDropdown = (label: string) => {
    openMobileDropdown.value = openMobileDropdown.value === label ? null : label;
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
    openMobileDropdown.value = null;
};

const navItems = [
    {
        label: 'Video',
        href: '#',
        hasDropdown: true,
        items: [
            { label: 'Daftar Isi', href: '/video/daftar-isi' },
            { label: 'Topik', href: '/video/topik' },
        ],
    },
    {
        label: 'Audio',
        href: '#',
        hasDropdown: true,
        items: [
            { label: 'Daftar Isi', href: '/audio/daftar-isi' },
            { label: 'Topik', href: '/audio/topik' },
        ],
    },
    { label: 'Buku', href: '/book' },
    {
        label: 'Topik',
        href: '#',
        hasDropdown: true,
        items: [
            { label: 'Topik 1', href: '/topic' },
            { label: 'Topik 2', href: '/topic2' },
            { label: 'Topik 3', href: '/topic3' },
        ],
    },
    { label: 'Playlist', href: '/community-playlist' },
    { label: 'Resep', href: '#' },
    { label: 'Komunitas', href: '#' },
    { label: 'Unduh', href: '#' },
    { label: 'Kontak', href: '#' },
    {
        label: 'Pengaturan',
        href: '#',
        hasDropdown: true,
        items: [
            { label: 'Pengguna', href: '#' },
            { label: 'Slider', href: '#' },
            { label: 'Tab Beranda', href: '#' },
            { label: 'Highlight Subtitle', href: '#' },
            { label: 'Breakline Subtitle', href: '#' },
            { label: 'Agenda', href: '#' },
            { label: 'Kontak WA Admin', href: '#' },
            { label: 'Ganti Subtitle', href: '#' },
            { label: 'Kata Kunci', href: '/keyword' },
            { label: 'Menu', href: '/menu-mobile' },
            { label: 'Notif Schedule', href: '#' },
        ],
    },
];
</script>

<template>
    <div class="min-h-screen bg-white">
        <!-- Header -->
        <header class="border-b border-gray-200">
            <div class="flex h-12 items-center justify-between px-4">
                <!-- Logo -->
                <Link :href="dashboard().url" class="text-base font-bold text-[#c9b717] lg:text-lg">
                    MASTER LU INDONESIA
                </Link>

                <!-- Desktop Navigation -->
                <nav class="hidden items-center gap-6 lg:flex">
                    <template v-for="item in navItems" :key="item.label">
                        <DropdownMenu v-if="item.hasDropdown">
                            <DropdownMenuTrigger
                                class="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
                                {{ item.label }}
                                <Icon icon="mdi:chevron-down" class="h-4 w-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <template v-if="item.items?.length">
                                    <DropdownMenuItem v-for="subItem in item.items" :key="subItem.label" as-child>
                                        <Link :href="subItem.href" class="w-full">
                                            {{ subItem.label }}
                                        </Link>
                                    </DropdownMenuItem>
                                </template>
                                <DropdownMenuItem v-else>Coming soon</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Link v-else :href="item.href" class="text-sm text-gray-600 hover:text-gray-900">
                            {{ item.label }}
                        </Link>
                    </template>
                </nav>

                <!-- Right Side Actions -->
                <div class="flex items-center gap-2">
                    <!-- User Menu (Desktop) -->
                    <DropdownMenu>
                        <DropdownMenuTrigger class="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
                            <Icon icon="mdi:account" class="h-4 w-4" />
                            <span class="hidden sm:inline">{{ user?.name || 'User' }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem as-child>
                                <Link :href="logout().url" method="post" as="button" class="w-full">
                                    Logout
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Mobile Menu Toggle -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="flex items-center justify-center text-gray-600 hover:text-gray-900 lg:hidden"
                        aria-label="Toggle menu"
                    >
                        <Icon :icon="mobileMenuOpen ? 'mdi:close' : 'mdi:menu'" class="h-6 w-6" />
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div
                v-if="mobileMenuOpen"
                class="border-t border-gray-200 bg-white lg:hidden"
            >
                <nav class="max-h-[calc(100vh-3rem)] overflow-y-auto px-4 py-2">
                    <template v-for="item in navItems" :key="item.label">
                        <div v-if="item.hasDropdown" class="border-b border-gray-100 py-2">
                            <button
                                @click="toggleMobileDropdown(item.label)"
                                class="flex w-full items-center justify-between text-sm text-gray-600 hover:text-gray-900"
                            >
                                {{ item.label }}
                                <Icon
                                    icon="mdi:chevron-down"
                                    class="h-4 w-4 transition-transform"
                                    :class="{ 'rotate-180': openMobileDropdown === item.label }"
                                />
                            </button>
                            <div
                                v-if="openMobileDropdown === item.label"
                                class="mt-2 space-y-2 pl-4"
                            >
                                <template v-if="item.items?.length">
                                    <Link
                                        v-for="subItem in item.items"
                                        :key="subItem.label"
                                        :href="subItem.href"
                                        @click="closeMobileMenu"
                                        class="block py-1 text-sm text-gray-500 hover:text-gray-900"
                                    >
                                        {{ subItem.label }}
                                    </Link>
                                </template>
                                <span v-else class="block py-1 text-sm text-gray-400">Coming soon</span>
                            </div>
                        </div>
                        <Link
                            v-else
                            :href="item.href"
                            @click="closeMobileMenu"
                            class="block border-b border-gray-100 py-2 text-sm text-gray-600 hover:text-gray-900"
                        >
                            {{ item.label }}
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main>
            <slot />
        </main>
    </div>
</template>

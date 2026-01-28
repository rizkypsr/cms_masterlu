<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';
import { dashboard, logout } from '@/routes';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

defineProps<{
    user: {
        name: string;
        username: string;
    };
}>();

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
    { label: 'Buku', href: '#' },
    { label: 'Topik', href: '#' },
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
            { label: 'Kata Kunci', href: '#' },
            { label: 'Menu Mobile', href: '#' },
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
                <!-- Logo + Navigation -->
                <div class="flex items-center gap-8">
                    <Link :href="dashboard().url" class="text-lg font-bold text-[#c9b717]">
                        MASTER LU INDONESIA
                    </Link>

                    <!-- Navigation -->
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
                </div>

                <!-- User Menu -->
                <DropdownMenu>
                    <DropdownMenuTrigger class="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
                        <Icon icon="mdi:account" class="h-4 w-4" />
                        {{ user?.name || 'User' }}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem as-child>
                            <Link :href="logout().url" method="post" as="button" class="w-full">
                                Logout
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <!-- Main Content -->
        <main>
            <slot />
        </main>
    </div>
</template>

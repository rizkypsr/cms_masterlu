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

interface Pengguna {
    id: number;
    name: string;
    email: string;
    username: string;
    created_at: string;
}

const props = defineProps<{
    penggunas: Pengguna[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const selectedPengguna = ref<Pengguna | null>(null);

// Form
const penggunaForm = useForm({
    name: '',
    email: '',
    username: '',
    password: '',
});

// Modal handlers
const openModal = (type: 'add' | 'edit' | 'delete', pengguna?: Pengguna) => {
    modalType.value = type;
    selectedPengguna.value = pengguna || null;
    
    if (type === 'add') {
        penggunaForm.reset();
    } else if (type === 'edit' && pengguna) {
        penggunaForm.name = pengguna.name;
        penggunaForm.email = pengguna.email;
        penggunaForm.username = pengguna.username;
        penggunaForm.password = '';
    }
    
    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        penggunaForm.post('/pengguna', {
            onSuccess: () => {
                modalOpen.value = false;
                penggunaForm.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedPengguna.value) {
        penggunaForm.put(`/pengguna/${selectedPengguna.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                penggunaForm.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedPengguna.value) {
        penggunaForm.delete(`/pengguna/${selectedPengguna.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Pengguna" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-600">Pengguna</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#337ab7] hover:bg-[#286090]"
                            size="sm"
                        >
                            <Icon icon="mdi:account-plus" class="mr-1 h-4 w-4" />
                            Tambah Pengguna
                        </Button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-auto p-6">
                    <div v-if="penggunas.length === 0" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <Icon icon="mdi:account-group-outline" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                            <p class="mb-4 text-gray-600">Belum ada pengguna</p>
                            <Button 
                                @click="openModal('add')"
                                class="bg-[#337ab7] hover:bg-[#286090]"
                            >
                                <Icon icon="mdi:account-plus" class="mr-1 h-4 w-4" />
                                Tambah Pengguna
                            </Button>
                        </div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Nama</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Email</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Username</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Dibuat</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="pengguna in penggunas" 
                                    :key="pengguna.id"
                                    class="border-b border-gray-100 hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ pengguna.name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ pengguna.email }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ pengguna.username }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(pengguna.created_at) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center gap-1">
                                            <button
                                                @click="openModal('edit', pengguna)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                title="Edit"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openModal('delete', pengguna)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                                title="Hapus"
                                            >
                                                <Icon icon="mdi:delete" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ modalType === 'add' ? 'Tambah Pengguna' : modalType === 'edit' ? 'Edit Pengguna' : 'Hapus Pengguna' }}
                    </DialogTitle>
                </DialogHeader>

                <form v-if="modalType !== 'delete'" @submit.prevent="handleSubmit" class="space-y-4">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-gray-700">
                            Nama <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="name"
                            v-model="penggunaForm.name"
                            type="text"
                            placeholder="Masukkan nama"
                            required
                        />
                        <p v-if="penggunaForm.errors.name" class="mt-1 text-sm text-red-600">
                            {{ penggunaForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="email"
                            v-model="penggunaForm.email"
                            type="email"
                            placeholder="Masukkan email"
                            required
                        />
                        <p v-if="penggunaForm.errors.email" class="mt-1 text-sm text-red-600">
                            {{ penggunaForm.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="username" class="mb-1 block text-sm font-medium text-gray-700">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="username"
                            v-model="penggunaForm.username"
                            type="text"
                            placeholder="Masukkan username"
                            required
                        />
                        <p v-if="penggunaForm.errors.username" class="mt-1 text-sm text-red-600">
                            {{ penggunaForm.errors.username }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-700">
                            Password <span v-if="modalType === 'add'" class="text-red-500">*</span>
                            <span v-else class="text-xs text-gray-500">(Kosongkan jika tidak ingin mengubah)</span>
                        </label>
                        <Input
                            id="password"
                            v-model="penggunaForm.password"
                            type="password"
                            placeholder="Masukkan password"
                            :required="modalType === 'add'"
                        />
                        <p v-if="penggunaForm.errors.password" class="mt-1 text-sm text-red-600">
                            {{ penggunaForm.errors.password }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button 
                            type="button"
                            variant="outline"
                            @click="modalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit"
                            :disabled="penggunaForm.processing"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        >
                            {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>

                <div v-else>
                    <p class="mb-4 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus pengguna <strong>{{ selectedPengguna?.name }}</strong>?
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button 
                            type="button"
                            variant="outline"
                            @click="modalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="button"
                            @click="handleDelete"
                            :disabled="penggunaForm.processing"
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

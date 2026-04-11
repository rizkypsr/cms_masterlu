<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface ContactWa {
    id: number;
    nama: string;
    no_wa: string;
    status: number;
}

const props = defineProps<{
    contacts: ContactWa[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const selectedContact = ref<ContactWa | null>(null);

// Form
const contactForm = useForm({
    nama: '',
    no_wa: '',
});

// Modal handlers
const openModal = (type: 'add' | 'edit' | 'delete', contact?: ContactWa) => {
    modalType.value = type;
    selectedContact.value = contact || null;
    
    if (type === 'add') {
        contactForm.reset();
    } else if (type === 'edit' && contact) {
        contactForm.nama = contact.nama;
        contactForm.no_wa = contact.no_wa;
    }
    
    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        contactForm.post('/contact-wa', {
            onSuccess: () => {
                modalOpen.value = false;
                contactForm.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedContact.value) {
        contactForm.put(`/contact-wa/${selectedContact.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                contactForm.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedContact.value) {
        contactForm.delete(`/contact-wa/${selectedContact.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="Kontak WA Admin" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-600">Kontak WA Admin</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#337ab7] hover:bg-[#286090]"
                            size="sm"
                        >
                            <Icon icon="mdi:whatsapp" class="mr-1 h-4 w-4" />
                            Tambah Kontak
                        </Button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-auto p-6">
                    <div v-if="contacts.length === 0" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <Icon icon="mdi:whatsapp" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                            <p class="mb-4 text-gray-600">Belum ada kontak WhatsApp</p>
                            <Button 
                                @click="openModal('add')"
                                class="bg-[#337ab7] hover:bg-[#286090]"
                            >
                                <Icon icon="mdi:whatsapp" class="mr-1 h-4 w-4" />
                                Tambah Kontak
                            </Button>
                        </div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Nama</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Nomor WhatsApp</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="contact in contacts" 
                                    :key="contact.id"
                                    class="border-b border-gray-100 hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ contact.nama }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <Icon icon="mdi:whatsapp" class="h-4 w-4 text-green-600" />
                                            <span>{{ contact.no_wa }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center gap-1">
                                            <button
                                                @click="openModal('edit', contact)"
                                                class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                                title="Edit"
                                            >
                                                <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="openModal('delete', contact)"
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
                        {{ modalType === 'add' ? 'Tambah Kontak WA' : modalType === 'edit' ? 'Edit Kontak WA' : 'Hapus Kontak WA' }}
                    </DialogTitle>
                </DialogHeader>

                <form v-if="modalType !== 'delete'" @submit.prevent="handleSubmit" class="space-y-4">
                    <div>
                        <label for="nama" class="mb-1 block text-sm font-medium text-gray-700">
                            Nama <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="nama"
                            v-model="contactForm.nama"
                            type="text"
                            placeholder="Masukkan nama"
                            required
                        />
                        <p v-if="contactForm.errors.nama" class="mt-1 text-sm text-red-600">
                            {{ contactForm.errors.nama }}
                        </p>
                    </div>

                    <div>
                        <label for="no_wa" class="mb-1 block text-sm font-medium text-gray-700">
                            Nomor WhatsApp <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="no_wa"
                            v-model="contactForm.no_wa"
                            type="text"
                            placeholder="Contoh: 6281281977739"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Format: 62xxxxxxxxxx (gunakan kode negara 62, tanpa 0 di awal)
                        </p>
                        <p v-if="contactForm.errors.no_wa" class="mt-1 text-sm text-red-600">
                            {{ contactForm.errors.no_wa }}
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
                            :disabled="contactForm.processing"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        >
                            {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>

                <div v-else>
                    <p class="mb-4 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus kontak <strong>{{ selectedContact?.nama }}</strong>?
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
                            :disabled="contactForm.processing"
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

<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

interface Information {
    id: number;
    description: string;
    type: string;
}

const props = defineProps<{
    information: Information[];
}>();

const page = usePage();
const user = page.props.auth?.user;

const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const selectedInformation = ref<Information | null>(null);

const form = useForm({
    description: '',
    type: '',
});

const openModal = (type: 'add' | 'edit' | 'delete', information?: Information) => {
    modalType.value = type;
    selectedInformation.value = information || null;
    
    if (type === 'add') {
        form.reset();
    } else if (type === 'edit' && information) {
        form.description = information.description;
        form.type = information.type;
    }
    
    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        form.post('/information', {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedInformation.value) {
        form.put(`/information/${selectedInformation.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedInformation.value) {
        form.delete(`/information/${selectedInformation.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};

const getAvailableTypes = () => {
    const allTypes = [
        { label: 'Audio', value: 'audio' },
        { label: 'Topik 1', value: 'topik1' }
    ];
    const usedTypes = props.information.map(info => info.type);
    
    if (modalType.value === 'edit' && selectedInformation.value) {
        return allTypes;
    }
    
    return allTypes.filter(type => !usedTypes.includes(type.value));
};
</script>

<template>
    <Head title="Information" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-600">Information</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                            size="sm"
                            :disabled="information.length >= 2"
                        >
                            <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                            Tambah Information
                        </Button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-auto p-6">
                    <div v-if="information.length === 0" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <Icon icon="mdi:information-outline" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                            <p class="mb-4 text-gray-600">Belum ada information</p>
                            <Button 
                                @click="openModal('add')"
                                class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                            >
                                <Icon icon="mdi:plus" class="mr-1 h-4 w-4" />
                                Tambah Information
                            </Button>
                        </div>
                    </div>

                    <div v-else class="grid gap-4 md:grid-cols-2">
                        <div 
                            v-for="info in information" 
                            :key="info.id"
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
                        >
                            <div class="mb-3 flex items-start justify-between">
                                <div>
                                    <span class="inline-block rounded bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">
                                        {{ info.type === 'audio' ? 'Audio' : info.type === 'topik1' ? 'Topik 1' : info.type }}
                                    </span>
                                </div>
                                <div class="flex gap-1">
                                    <button
                                        @click="openModal('edit', info)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#f0ad4e] text-white hover:bg-[#eea236]"
                                    >
                                        <Icon icon="mdi:pencil" class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openModal('delete', info)"
                                        class="flex h-7 w-7 items-center justify-center rounded bg-[#d9534f] text-white hover:bg-[#d43f3a]"
                                    >
                                        <Icon icon="mdi:delete" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <p class="whitespace-pre-wrap text-sm text-gray-700" v-html="info.description"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ modalType === 'add' ? 'Tambah Information' : modalType === 'edit' ? 'Edit Information' : 'Hapus Information' }}
                    </DialogTitle>
                </DialogHeader>

                <form v-if="modalType !== 'delete'" @submit.prevent="handleSubmit" class="space-y-4">
                    <div>
                        <label for="type" class="mb-1 block text-sm font-medium text-gray-700">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="type"
                            v-model="form.type"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            required
                            :disabled="modalType === 'edit'"
                        >
                            <option value="">Select type</option>
                            <option v-for="type in getAvailableTypes()" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <div>
                        <label for="description" class="mb-1 block text-sm font-medium text-gray-700">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <TiptapEditor v-model="form.description" height="300px" />
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                            {{ form.errors.description }}
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
                            :disabled="form.processing"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        >
                            <Icon icon="mdi:check" class="mr-1 h-4 w-4" />
                            {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>

                <div v-else>
                    <p class="mb-4 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus information untuk <strong>{{ selectedInformation?.type === 'audio' ? 'Audio' : selectedInformation?.type === 'topik1' ? 'Topik 1' : selectedInformation?.type }}</strong>?
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
                            :disabled="form.processing"
                            class="bg-[#d9534f] hover:bg-[#d43f3a]"
                        >
                            <Icon icon="mdi:delete" class="mr-1 h-4 w-4" />
                            Hapus
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>

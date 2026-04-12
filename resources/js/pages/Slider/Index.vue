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

interface Slider {
    id: number;
    name: string;
    url: string;
    seq: number;
}

const props = defineProps<{
    sliders: Slider[];
}>();

const page = usePage();
const user = page.props.auth?.user;

// View mode
const viewMode = ref<'grid' | 'list'>('grid');

// Modal states
const modalOpen = ref(false);
const modalType = ref<'add' | 'edit' | 'delete'>('add');
const selectedSlider = ref<Slider | null>(null);

// Form
const sliderForm = useForm({
    name: '',
    image: null as File | null,
    seq: null as number | null,
});

// Urutan options
const urutanOptions = computed(() => {
    const options: { position: number; seq: number; label: string }[] = [];
    
    props.sliders.forEach((slider, index) => {
        const position = index + 1;
        options.push({
            position,
            seq: slider.seq,
            label: `${position} - ${slider.name}`,
        });
    });
    
    const lastPosition = props.sliders.length + 1;
    options.push({
        position: lastPosition,
        seq: lastPosition,
        label: `${lastPosition} - (Terakhir)`,
    });
    
    return options;
});

const selectedPosition = computed(() => {
    return sliderForm.seq;
});

const selectUrutan = (position: number) => {
    sliderForm.seq = position;
};

// View image in new tab
const viewImage = (url: string) => {
    window.open(url, '_blank');
};

// Modal handlers
const openModal = (type: 'add' | 'edit' | 'delete', slider?: Slider) => {
    modalType.value = type;
    selectedSlider.value = slider || null;
    
    if (type === 'add') {
        sliderForm.reset();
        sliderForm.seq = props.sliders.length + 1;
    } else if (type === 'edit' && slider) {
        sliderForm.name = slider.name;
        sliderForm.image = null;
        const sliderIndex = props.sliders.findIndex(s => s.id === slider.id);
        sliderForm.seq = sliderIndex !== -1 ? sliderIndex + 1 : slider.seq;
    }
    
    modalOpen.value = true;
};

const handleSubmit = () => {
    if (modalType.value === 'add') {
        sliderForm.post('/slider', {
            forceFormData: true,
            onSuccess: () => {
                modalOpen.value = false;
                sliderForm.reset();
            },
        });
    } else if (modalType.value === 'edit' && selectedSlider.value) {
        sliderForm.put(`/slider/${selectedSlider.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                sliderForm.reset();
            },
        });
    }
};

const handleDelete = () => {
    if (selectedSlider.value) {
        sliderForm.delete(`/slider/${selectedSlider.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="Slider" />

    <DashboardLayout :user="user">
        <div class="h-[calc(100vh-48px)] overflow-hidden bg-[#d3dce6] p-6">
            <div class="flex h-full flex-col overflow-hidden rounded bg-white shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-600">Slider</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- View Toggle -->
                        <div class="flex rounded border border-gray-300">
                            <button
                                @click="viewMode = 'grid'"
                                :class="[
                                    'flex items-center gap-1 px-3 py-1.5 text-sm',
                                    viewMode === 'grid' 
                                        ? 'bg-[#337ab7] text-white' 
                                        : 'bg-white text-gray-600 hover:bg-gray-50'
                                ]"
                                title="Grid View"
                            >
                                <Icon icon="mdi:view-grid" class="h-4 w-4" />
                                Grid
                            </button>
                            <button
                                @click="viewMode = 'list'"
                                :class="[
                                    'flex items-center gap-1 border-l border-gray-300 px-3 py-1.5 text-sm',
                                    viewMode === 'list' 
                                        ? 'bg-[#337ab7] text-white' 
                                        : 'bg-white text-gray-600 hover:bg-gray-50'
                                ]"
                                title="List View"
                            >
                                <Icon icon="mdi:view-list" class="h-4 w-4" />
                                List
                            </button>
                        </div>
                        
                        <Button 
                            @click="openModal('add')"
                            class="bg-[#337ab7] hover:bg-[#286090]"
                            size="sm"
                        >
                            <Icon icon="mdi:image-plus" class="mr-1 h-4 w-4" />
                            Tambah Slider
                        </Button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-auto p-6">
                    <div v-if="sliders.length === 0" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <Icon icon="mdi:image-multiple-outline" class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                            <p class="mb-4 text-gray-600">Belum ada slider</p>
                            <Button 
                                @click="openModal('add')"
                                class="bg-[#337ab7] hover:bg-[#286090]"
                            >
                                <Icon icon="mdi:image-plus" class="mr-1 h-4 w-4" />
                                Tambah Slider
                            </Button>
                        </div>
                    </div>

                    <div v-else>
                        <!-- Grid View -->
                        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div 
                                v-for="slider in sliders" 
                                :key="slider.id"
                                class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                            >
                                <div class="aspect-[950/350] overflow-hidden bg-gray-100">
                                    <img 
                                        :src="slider.url" 
                                        :alt="slider.name"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div class="p-4">
                                    <div class="mb-2 flex items-center justify-between">
                                        <h3 class="font-medium text-gray-700">{{ slider.name }}</h3>
                                        <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">
                                            Seq: {{ slider.seq }}
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            @click="viewImage(slider.url)"
                                            class="flex flex-1 items-center justify-center gap-1 rounded bg-[#337ab7] px-3 py-2 text-sm text-white hover:bg-[#286090]"
                                            title="Lihat Gambar"
                                        >
                                            <Icon icon="mdi:eye" class="h-4 w-4" />
                                            Lihat
                                        </button>
                                        <button
                                            @click="openModal('edit', slider)"
                                            class="flex flex-1 items-center justify-center gap-1 rounded bg-[#f0ad4e] px-3 py-2 text-sm text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            Edit
                                        </button>
                                        <button
                                            @click="openModal('delete', slider)"
                                            class="flex flex-1 items-center justify-center gap-1 rounded bg-[#d9534f] px-3 py-2 text-sm text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- List View -->
                        <div v-else class="space-y-4">
                            <div 
                                v-for="slider in sliders" 
                                :key="slider.id"
                                class="flex gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
                            >
                                <div class="h-24 w-64 flex-shrink-0 overflow-hidden rounded bg-gray-100">
                                    <img 
                                        :src="slider.url" 
                                        :alt="slider.name"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div class="flex flex-1 items-center justify-between">
                                    <div>
                                        <h3 class="mb-1 font-medium text-gray-700">{{ slider.name }}</h3>
                                        <span class="inline-block rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">
                                            Seq: {{ slider.seq }}
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            @click="viewImage(slider.url)"
                                            class="flex items-center gap-1 rounded bg-[#337ab7] px-4 py-2 text-sm text-white hover:bg-[#286090]"
                                            title="Lihat Gambar"
                                        >
                                            <Icon icon="mdi:eye" class="h-4 w-4" />
                                            Lihat
                                        </button>
                                        <button
                                            @click="openModal('edit', slider)"
                                            class="flex items-center gap-1 rounded bg-[#f0ad4e] px-4 py-2 text-sm text-white hover:bg-[#eea236]"
                                        >
                                            <Icon icon="mdi:pencil" class="h-4 w-4" />
                                            Edit
                                        </button>
                                        <button
                                            @click="openModal('delete', slider)"
                                            class="flex items-center gap-1 rounded bg-[#d9534f] px-4 py-2 text-sm text-white hover:bg-[#d43f3a]"
                                        >
                                            <Icon icon="mdi:delete" class="h-4 w-4" />
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
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
                        {{ modalType === 'add' ? 'Tambah Slider' : modalType === 'edit' ? 'Edit Slider' : 'Hapus Slider' }}
                    </DialogTitle>
                </DialogHeader>

                <form v-if="modalType !== 'delete'" @submit.prevent="handleSubmit" class="space-y-4">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-gray-700">
                            Nama <span class="text-red-500">*</span>
                        </label>
                        <Input
                            id="name"
                            v-model="sliderForm.name"
                            type="text"
                            placeholder="Masukkan nama slider"
                            required
                        />
                        <p v-if="sliderForm.errors.name" class="mt-1 text-sm text-red-600">
                            {{ sliderForm.errors.name }}
                        </p>
                    </div>

                    <div v-if="modalType === 'add'">
                        <label for="image" class="mb-1 block text-sm font-medium text-gray-700">
                            Gambar <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="image"
                            type="file"
                            accept="image/*"
                            @change="(e) => sliderForm.image = (e.target as HTMLInputElement).files?.[0] || null"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Maksimal 15MB. Gambar akan otomatis diresize ke 950x350 dan dikonversi ke WebP
                        </p>
                        <p v-if="sliderForm.errors.image" class="mt-1 text-sm text-red-600">
                            {{ sliderForm.errors.image }}
                        </p>
                    </div>

                    <div>
                        <label for="seq" class="mb-1 block text-sm font-medium text-gray-700">
                            Urutan <span class="text-red-500">*</span>
                        </label>
                        <select
                            :value="selectedPosition"
                            @change="selectUrutan(Number(($event.target as HTMLSelectElement).value))"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            required
                        >
                            <option v-for="option in urutanOptions" :key="option.position" :value="option.position">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="sliderForm.errors.seq" class="mt-1 text-sm text-red-600">
                            {{ sliderForm.errors.seq }}
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
                            :disabled="sliderForm.processing"
                            class="bg-[#5cb85c] hover:bg-[#4cae4c]"
                        >
                            {{ modalType === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </div>
                </form>

                <div v-else>
                    <p class="mb-4 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus slider <strong>{{ selectedSlider?.name }}</strong>?
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
                            :disabled="sliderForm.processing"
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

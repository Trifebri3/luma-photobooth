<script setup>
import { ref, reactive, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    LayoutGrid, Plus, Save, ArrowLeft, Trash2, Sliders, Image, Check, AlertCircle,
    UploadCloud, Sparkles, Eye, EyeOff
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    event: Object,
    templates: Array,
});

const selectedTemplateId = ref(props.templates?.[0]?.id || null);
const selectedTemplate = computed(() => {
    return props.templates.find(t => t.id === selectedTemplateId.value) || props.templates?.[0] || null;
});

// Editable slots state
const activeSlots = ref(
    selectedTemplate.value ? JSON.parse(JSON.stringify(selectedTemplate.value.slots || [])) : []
);

// Toggle sample photo preview behind transparent frame
const showSamplePhotos = ref(true);

function formatOverlayUrl(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path.startsWith('/')) return path;
    return '/' + path;
}

function handleOverlayError(e) {
    console.warn('Frame overlay image failed to load, falling back:', e.target.src);
    // If it failed to load, fallback to default cute frame
    if (!e.target.src.endsWith('kawaii-strip-3.png')) {
        e.target.src = '/frames/kawaii-strip-3.png';
    } else {
        e.target.style.display = 'none';
    }
}

function switchTemplate(tmpl) {
    selectedTemplateId.value = tmpl.id;
    activeSlots.value = JSON.parse(JSON.stringify(tmpl.slots || []));
}

// Visual Scale factor to fit canvas into container
const scaleFactor = computed(() => {
    if (!selectedTemplate.value) return 0.28;
    const maxPreviewHeight = 540;
    return maxPreviewHeight / selectedTemplate.value.canvas_height;
});

const previewContainerWidth = computed(() => {
    if (!selectedTemplate.value) return 360;
    return Math.round(selectedTemplate.value.canvas_width * scaleFactor.value);
});

const previewContainerHeight = computed(() => {
    if (!selectedTemplate.value) return 540;
    return Math.round(selectedTemplate.value.canvas_height * scaleFactor.value);
});

// Slot coordination save
const isSavingSlots = ref(false);
const saveSuccessMessage = ref('');

async function saveSlotCoordinates() {
    if (!selectedTemplate.value) return;
    isSavingSlots.value = true;
    saveSuccessMessage.value = '';

    try {
        const res = await axios.post(
            `/organizer/events/${props.event.id}/templates/${selectedTemplate.value.id}/slots`,
            {
                slots: activeSlots.value,
            }
        );
        if (res.data.success) {
            saveSuccessMessage.value = res.data.message;
            setTimeout(() => { saveSuccessMessage.value = ''; }, 3000);
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Gagal menyimpan koordinat.');
    } finally {
        isSavingSlots.value = false;
    }
}

// Replace / Upload PNG Frame for Active Template
const isUpdatingOverlay = ref(false);
const overlayUpdateForm = useForm({
    overlay_image: null,
});
const overlayPreviewUrl = ref('');

function handleOverlayChangeForActive(e) {
    const file = e.target.files[0];
    if (file) {
        overlayUpdateForm.overlay_image = file;
        overlayPreviewUrl.value = URL.createObjectURL(file);
    }
}

function submitOverlayUpdate() {
    if (!overlayUpdateForm.overlay_image || !selectedTemplate.value) return;
    overlayUpdateForm.post(route('organizer.events.templates.overlay.update', [props.event.id, selectedTemplate.value.id]), {
        onSuccess: () => {
            isUpdatingOverlay.value = false;
            overlayUpdateForm.reset();
            overlayPreviewUrl.value = '';
        },
    });
}

// Add New Template Form with Presets
const isAddingTemplate = ref(false);
const selectedPreset = ref('strip_3');

const presets = [
    {
        id: 'strip_3',
        name: 'Photostrip 3 Foto (Vertical)',
        width: 600,
        height: 1800,
        orientation: 'strip',
        slots: 3,
        overlay: '/frames/kawaii-strip-3.png',
        description: 'Format Korea aesthetic dengan 3 pose vertikal',
    },
    {
        id: 'grid_4',
        name: 'Grid 4 Foto (2x2 Box)',
        width: 1200,
        height: 1600,
        orientation: 'portrait',
        slots: 4,
        overlay: '/frames/y2k-grid-4.png',
        description: 'Format kolase 4 foto kotak Y2K / party vibes',
    },
    {
        id: 'duo_2',
        name: 'Duo 2 Foto (Wide Stacked)',
        width: 1200,
        height: 1600,
        orientation: 'portrait',
        slots: 2,
        overlay: '/frames/cute-duo-2.png',
        description: 'Format elegan 2 foto landscape bertumpuk',
    },
    {
        id: 'polaroid_1',
        name: 'Polaroid 1 Foto (Solo Shot)',
        width: 1200,
        height: 1500,
        orientation: 'portrait',
        slots: 1,
        overlay: '/frames/polaroid-doodle-1.png',
        description: 'Format polaroid klasik dengan margin bawah lebar',
    },
    {
        id: 'strip_4',
        name: 'Retro Cine Strip 4 Foto',
        width: 600,
        height: 2000,
        orientation: 'strip',
        slots: 4,
        overlay: '/frames/retro-strip-4.png',
        description: 'Strip film bioskop retro panjang dengan 4 pose',
    },
];

const newTemplateForm = useForm({
    name: 'Photostrip 3 Foto Lucu',
    layout_preset: 'strip_3',
    orientation: 'strip',
    canvas_width: 600,
    canvas_height: 1800,
    slot_count: 3,
    overlay_image: null,
});

const newOverlayPreviewUrl = ref('/frames/kawaii-strip-3.png');

function applyPreset(preset) {
    selectedPreset.value = preset.id;
    newTemplateForm.layout_preset = preset.id;
    newTemplateForm.name = preset.name;
    newTemplateForm.orientation = preset.orientation;
    newTemplateForm.canvas_width = preset.width;
    newTemplateForm.canvas_height = preset.height;
    newTemplateForm.slot_count = preset.slots;
    newOverlayPreviewUrl.value = preset.overlay;
}

function handleOverlayUpload(e) {
    const file = e.target.files[0];
    if (file) {
        newTemplateForm.overlay_image = file;
        newOverlayPreviewUrl.value = URL.createObjectURL(file);
    }
}

function submitNewTemplate() {
    newTemplateForm.post(route('organizer.events.templates.store', props.event.id), {
        onSuccess: () => {
            isAddingTemplate.value = false;
            newTemplateForm.reset();
            newOverlayPreviewUrl.value = '';
        },
    });
}

function deleteTemplate(template) {
    if (confirm(`Apakah Anda yakin ingin menghapus template '${template.name}'?`)) {
        router.delete(route('organizer.events.templates.destroy', [props.event.id, template.id]));
    }
}
</script>

<template>
    <Head :title="`Frame Studio - ${event.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('organizer.events.show', event.id)"
                        class="p-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-extrabold text-slate-900">
                                Frame Studio Visual
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                PNG Overlay Transparan
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Unggah frame PNG tanpa background (transparan) dengan aneka layout lucu untuk event {{ event.name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        @click="isAddingTemplate = true"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition shadow-sm"
                    >
                        <Plus class="w-4 h-4 text-teal-400" />
                        <span>Input Frame PNG Baru</span>
                    </button>

                    <button
                        @click="saveSlotCoordinates"
                        :disabled="isSavingSlots || !selectedTemplate"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-extrabold rounded-xl shadow-md shadow-teal-600/20 transition disabled:opacity-50"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ isSavingSlots ? 'Menyimpan...' : 'Simpan Koordinat' }}</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Success Alert -->
                <div v-if="saveSuccessMessage" class="p-4 rounded-2xl bg-teal-50 border border-teal-200 text-teal-800 text-xs font-semibold flex items-center gap-2 shadow-xs">
                    <Check class="w-4 h-4 text-teal-600" />
                    <span>{{ saveSuccessMessage }}</span>
                </div>

                <!-- Template Selector Tabs -->
                <div class="flex items-center gap-3 overflow-x-auto pb-2 scrollbar-none">
                    <button
                        v-for="tmpl in templates"
                        :key="tmpl.id"
                        @click="switchTemplate(tmpl)"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-2.5 border',
                            selectedTemplate?.id === tmpl.id
                                ? 'bg-teal-600 text-white border-teal-600 shadow-md shadow-teal-600/20 scale-102'
                                : 'bg-white text-slate-700 border-slate-200 hover:border-teal-400 hover:bg-teal-50/50'
                        ]"
                    >
                        <LayoutGrid class="w-3.5 h-3.5" :class="selectedTemplate?.id === tmpl.id ? 'text-white' : 'text-teal-600'" />
                        <span>{{ tmpl.name }}</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full" :class="selectedTemplate?.id === tmpl.id ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600'">
                            {{ tmpl.slots?.length || 0 }} Foto
                        </span>
                    </button>
                </div>

                <!-- Studio Workspace Grid -->
                <div v-if="selectedTemplate" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    
                    <!-- Left: Visual Canvas Preview Area -->
                    <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm flex flex-col items-center">
                        <div class="w-full flex items-center justify-between text-xs text-slate-500 mb-4 pb-3 border-b border-slate-100">
                            <div>
                                <span class="font-extrabold text-sm text-slate-900 block">{{ selectedTemplate.name }}</span>
                                <span class="font-mono text-slate-400 text-[11px]">Resolusi: {{ selectedTemplate.canvas_width }} &times; {{ selectedTemplate.canvas_height }} px</span>
                            </div>

                            <button
                                @click="showSamplePhotos = !showSamplePhotos"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition"
                            >
                                <Eye v-if="showSamplePhotos" class="w-3.5 h-3.5 text-teal-600" />
                                <EyeOff v-else class="w-3.5 h-3.5 text-slate-400" />
                                <span>{{ showSamplePhotos ? 'Sembunyikan Foto Contoh' : 'Tampilkan Foto Contoh' }}</span>
                            </button>
                        </div>

                        <!-- Canvas Simulator with Checkerboard background for transparency -->
                        <div
                            class="relative rounded-2xl border-4 border-slate-300 shadow-2xl overflow-hidden bg-white"
                            :style="{
                                width: `${previewContainerWidth}px`,
                                height: `${previewContainerHeight}px`,
                                backgroundImage: `radial-gradient(#CBD5E1 0.75px, transparent 0.75px), radial-gradient(#CBD5E1 0.75px, #F8FAFC 0.75px)`,
                                backgroundSize: '16px 16px',
                                backgroundPosition: '0 0, 8px 8px',
                            }"
                        >
                            <!-- Sample Photos / Photo Slots underneath transparent frame -->
                            <div
                                v-for="(slot, idx) in activeSlots"
                                :key="idx"
                                class="absolute rounded-lg overflow-hidden flex flex-col items-center justify-center text-center transition-all shadow-md group"
                                :style="{
                                    left: `${Math.round(slot.x_position * scaleFactor)}px`,
                                    top: `${Math.round(slot.y_position * scaleFactor)}px`,
                                    width: `${Math.round(slot.width * scaleFactor)}px`,
                                    height: `${Math.round(slot.height * scaleFactor)}px`,
                                }"
                            >
                                <!-- Sample Photo Image if enabled -->
                                <img
                                    v-if="showSamplePhotos"
                                    :src="`https://picsum.photos/seed/luma${slot.slot_number + selectedTemplate.id * 5}/400/500`"
                                    alt="Sample"
                                    class="absolute inset-0 w-full h-full object-cover"
                                />

                                <!-- Slot Marker Overlay -->
                                <div class="absolute inset-0 border-2 border-dashed border-teal-500 bg-teal-500/15 flex flex-col items-center justify-center p-2">
                                    <span class="font-extrabold text-[11px] tracking-wider uppercase bg-teal-600 text-white px-2 py-0.5 rounded shadow">
                                        Slot #{{ slot.slot_number }}
                                    </span>
                                    <span class="text-[9px] text-teal-900 font-mono mt-1 font-bold bg-white/80 px-1 rounded">
                                        {{ slot.width }}&times;{{ slot.height }}
                                    </span>
                                </div>
                            </div>

                            <!-- Transparent PNG Frame Overlay on TOP of photos -->
                            <img
                                v-if="selectedTemplate.overlay_image_path"
                                :src="formatOverlayUrl(selectedTemplate.overlay_image_path)"
                                @error="handleOverlayError"
                                class="absolute inset-0 w-full h-full object-contain pointer-events-none z-10"
                            />
                        </div>

                        <div class="mt-4 flex items-center justify-center gap-3 text-xs text-slate-500">
                            <span class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-teal-500"></span> Slot Foto
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full border border-slate-300 bg-white"></span> PNG Frame Transparan
                            </span>
                        </div>
                    </div>

                    <!-- Right: Frame Controls & Slot Coordinates -->
                    <div class="lg:col-span-5 space-y-5">
                        
                        <!-- Card 1: Ganti / Upload PNG Frame -->
                        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-extrabold text-sm text-slate-900 flex items-center gap-2">
                                    <Image class="w-4 h-4 text-teal-600" />
                                    <span>Frame PNG Transparan</span>
                                </h3>

                                <span v-if="selectedTemplate.overlay_image_path" class="text-[11px] font-bold text-teal-600 bg-teal-50 px-2 py-0.5 rounded-full border border-teal-200">
                                    PNG Aktif Terpasang
                                </span>
                                <span v-else class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                    Belum Ada PNG
                                </span>
                            </div>

                            <p class="text-xs text-slate-500 leading-relaxed">
                                Frame harus berformat <strong>PNG transparan</strong> tanpa latar belakang. Bagian foto pengunjung akan tembus terlihat melalui lubang transparan frame.
                            </p>

                            <div v-if="selectedTemplate.overlay_image_path" class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div class="w-12 h-16 rounded-lg border border-slate-300 overflow-hidden bg-white flex items-center justify-center p-1">
                                    <img :src="formatOverlayUrl(selectedTemplate.overlay_image_path)" @error="handleOverlayError" class="w-full h-full object-contain" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-bold text-slate-800 truncate">{{ selectedTemplate.name }}.png</div>
                                    <div class="text-[11px] text-teal-600 font-semibold">Tembus Pandang / Transparent</div>
                                </div>
                            </div>

                            <form @submit.prevent="submitOverlayUpdate" class="space-y-3 pt-2">
                                <label class="block text-xs font-bold text-slate-700">
                                    {{ selectedTemplate.overlay_image_path ? 'Ganti dengan PNG Baru:' : 'Unggah File PNG Frame Transparan:' }}
                                </label>
                                
                                <input
                                    type="file"
                                    accept="image/png"
                                    @change="handleOverlayChangeForActive"
                                    class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 transition cursor-pointer"
                                />

                                <div v-if="overlayPreviewUrl" class="p-3 rounded-2xl bg-teal-50/60 border border-teal-200 flex items-center justify-between">
                                    <span class="text-xs text-teal-800 font-semibold">File baru siap diunggah</span>
                                    <button
                                        type="submit"
                                        :disabled="overlayUpdateForm.processing"
                                        class="px-4 py-1.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold transition shadow-sm"
                                    >
                                        {{ overlayUpdateForm.processing ? 'Mengunggah...' : 'Perbarui Frame' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Card 2: Slot Coordinates Adjuster -->
                        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-4">
                            <h3 class="font-extrabold text-sm text-slate-900 flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <Sliders class="w-4 h-4 text-teal-600" />
                                    <span>Posisi Slot Foto di Balik Frame</span>
                                </span>
                                <span class="text-xs font-bold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">
                                    {{ activeSlots.length }} Slot
                                </span>
                            </h3>

                            <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1">
                                <div
                                    v-for="(slot, idx) in activeSlots"
                                    :key="idx"
                                    class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2.5"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold text-slate-800">
                                        <span class="text-teal-700 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                                            FOTO SLOT #{{ slot.slot_number }}
                                        </span>
                                        <span class="text-[11px] text-slate-400 font-mono">Rasio: {{ slot.aspect_ratio || '4:3' }}</span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Posisi X (px)</label>
                                            <input
                                                v-model.number="slot.x_position"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Posisi Y (px)</label>
                                            <input
                                                v-model.number="slot.y_position"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Lebar Slot (px)</label>
                                            <input
                                                v-model.number="slot.width"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Tinggi Slot (px)</label>
                                            <input
                                                v-model.number="slot.height"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                <button
                                    @click="deleteTemplate(selectedTemplate)"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-xs text-rose-500 hover:text-rose-700 font-bold"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Hapus Template</span>
                                </button>

                                <button
                                    @click="saveSlotCoordinates"
                                    :disabled="isSavingSlots"
                                    type="button"
                                    class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-extrabold shadow-sm transition"
                                >
                                    Simpan Posisi
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Input Frame Baru dengan Berbagai Layout Lucu -->
                <div v-if="isAddingTemplate" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 sm:p-8 rounded-3xl bg-white border border-slate-200 shadow-2xl">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xl font-extrabold text-slate-900">Input Frame PNG Transparan Baru</h3>
                            <button @click="isAddingTemplate = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                                &times;
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mb-6">Pilih layout lucu dan unggah desain frame PNG tanpa background (transparan).</p>

                        <form @submit.prevent="submitNewTemplate" class="space-y-5">
                            
                            <!-- Pilih Preset Layout Lucu -->
                            <div>
                                <label class="block text-xs font-extrabold text-slate-800 mb-2">
                                    Pilih Gaya Layout Frame:
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    <div
                                        v-for="preset in presets"
                                        :key="preset.id"
                                        @click="applyPreset(preset)"
                                        :class="[
                                            'p-3 rounded-2xl border-2 cursor-pointer transition-all text-left flex items-center gap-3',
                                            selectedPreset === preset.id
                                                ? 'border-teal-500 bg-teal-50/60 text-teal-950 shadow-sm'
                                                : 'border-slate-200 bg-slate-50 hover:border-slate-300 text-slate-700'
                                        ]"
                                    >
                                        <div class="w-10 h-14 rounded-lg bg-white border border-slate-200 overflow-hidden shrink-0 p-0.5 flex items-center justify-center shadow-xs">
                                            <img :src="preset.overlay" class="w-full h-full object-contain" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between mb-0.5">
                                                <span class="font-extrabold text-xs text-slate-900 truncate">{{ preset.name }}</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded-full shrink-0" :class="selectedPreset === preset.id ? 'bg-teal-600 text-white' : 'bg-slate-200 text-slate-700'">
                                                    {{ preset.slots }} Foto
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-500 leading-tight line-clamp-2">
                                                {{ preset.description }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detail Template -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Frame *</label>
                                <input
                                    v-model="newTemplateForm.name"
                                    type="text"
                                    placeholder="Contoh: Kawaii Photostrip Pastel"
                                    class="w-full py-2.5 px-3.5 text-xs rounded-xl border border-slate-300 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none font-bold"
                                    required
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Lebar Canvas (px)</label>
                                    <input
                                        v-model.number="newTemplateForm.canvas_width"
                                        type="number"
                                        class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white font-mono font-bold"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Tinggi Canvas (px)</label>
                                    <input
                                        v-model.number="newTemplateForm.canvas_height"
                                        type="number"
                                        class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white font-mono font-bold"
                                    />
                                </div>
                            </div>

                            <!-- Unggah PNG Transparan -->
                            <div class="p-4 rounded-2xl bg-teal-50/50 border border-teal-200">
                                <label class="block text-xs font-extrabold text-teal-900 mb-1">
                                    Unggah File Frame PNG Transparan (Tanpa Background) *
                                </label>
                                <p class="text-[11px] text-teal-700 mb-3">
                                    File PNG dengan bagian foto yang tembus pandang (transparan), dihiasi stiker, bingkai, atau doodle lucu.
                                </p>

                                <input
                                    type="file"
                                    accept="image/png"
                                    @change="handleOverlayUpload"
                                    class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-teal-600 file:text-white hover:file:bg-teal-500 transition cursor-pointer"
                                />

                                <div v-if="newOverlayPreviewUrl" class="mt-3 flex items-center gap-3 p-2.5 rounded-xl bg-white border border-teal-200">
                                    <img :src="formatOverlayUrl(newOverlayPreviewUrl)" class="h-16 w-auto rounded border border-teal-300 bg-white object-contain" />
                                    <div>
                                        <span class="text-xs text-teal-800 font-bold block">Frame PNG Siap Digunakan</span>
                                        <span class="text-[10px] text-slate-500">Jika tidak memilih file custom, frame lucu bawaan preset ini otomatis terpasang.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                                <button
                                    type="button"
                                    @click="isAddingTemplate = false"
                                    class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="newTemplateForm.processing"
                                    class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-extrabold transition shadow-md shadow-teal-600/20"
                                >
                                    {{ newTemplateForm.processing ? 'Menyimpan...' : 'Tambahkan Frame' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    LayoutGrid, Plus, Save, ArrowLeft, Trash2, Move, Sliders, Image, Check, AlertCircle
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

function switchTemplate(tmpl) {
    selectedTemplateId.value = tmpl.id;
    activeSlots.value = JSON.parse(JSON.stringify(tmpl.slots || []));
}

// Visual Scale factor to fit 1200x1800 canvas into 400x600 preview container
const scaleFactor = computed(() => {
    if (!selectedTemplate.value) return 0.3;
    const maxPreviewHeight = 560;
    return maxPreviewHeight / selectedTemplate.value.canvas_height;
});

const previewContainerWidth = computed(() => {
    if (!selectedTemplate.value) return 360;
    return Math.round(selectedTemplate.value.canvas_width * scaleFactor.value);
});

const previewContainerHeight = computed(() => {
    if (!selectedTemplate.value) return 560;
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

// Add New Template Form
const isAddingTemplate = ref(false);
const newTemplateForm = useForm({
    name: '',
    orientation: 'portrait',
    canvas_width: 1200,
    canvas_height: 1800,
    slot_count: 3,
    overlay_image: null,
});

function handleOverlayUpload(e) {
    newTemplateForm.overlay_image = e.target.files[0];
}

function submitNewTemplate() {
    newTemplateForm.post(route('organizer.events.templates.store', props.event.id), {
        onSuccess: () => {
            isAddingTemplate.value = false;
            newTemplateForm.reset();
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
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('organizer.events.show', event.id)"
                        class="p-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                            Frame Studio Visual Editor
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Atur posisi slot foto, overlay PNG transparan, dan orientasi frame acara {{ event.name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="isAddingTemplate = true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl hover:bg-slate-700 transition"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Tambah Template Baru</span>
                    </button>

                    <button
                        @click="saveSlotCoordinates"
                        :disabled="isSavingSlots || !selectedTemplate"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition disabled:opacity-50"
                    >
                        <Save class="w-3.5 h-3.5" />
                        <span>{{ isSavingSlots ? 'Menyimpan...' : 'Simpan Koordinat Slot' }}</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Success Alert -->
                <div v-if="saveSuccessMessage" class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
                    <Check class="w-4 h-4" />
                    <span>{{ saveSuccessMessage }}</span>
                </div>

                <!-- Template Selector Tabs -->
                <div class="flex items-center gap-3 overflow-x-auto pb-2">
                    <button
                        v-for="tmpl in templates"
                        :key="tmpl.id"
                        @click="switchTemplate(tmpl)"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-2 border',
                            selectedTemplate?.id === tmpl.id
                                ? 'bg-indigo-600 text-white border-indigo-500 shadow-md shadow-indigo-600/30'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-indigo-400'
                        ]"
                    >
                        <LayoutGrid class="w-3.5 h-3.5" />
                        <span>{{ tmpl.name }}</span>
                        <span class="text-[10px] opacity-75 font-mono">({{ tmpl.slots?.length || 0 }} Foto)</span>
                    </button>
                </div>

                <!-- Studio Workspace Grid -->
                <div v-if="selectedTemplate" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    
                    <!-- Left: Visual Canvas Preview Area -->
                    <div class="lg:col-span-7 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-8 shadow-sm flex flex-col items-center">
                        <div class="w-full flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-4 pb-2 border-b border-gray-100 dark:border-gray-700">
                            <span class="font-bold text-gray-800 dark:text-white">{{ selectedTemplate.name }}</span>
                            <span class="font-mono">Resolusi Asli: {{ selectedTemplate.canvas_width }} &times; {{ selectedTemplate.canvas_height }} px</span>
                        </div>

                        <!-- Canvas Simulator -->
                        <div
                            class="relative rounded-2xl border-4 border-slate-700 shadow-2xl overflow-hidden bg-slate-950"
                            :style="{
                                width: `${previewContainerWidth}px`,
                                height: `${previewContainerHeight}px`,
                            }"
                        >
                            <!-- Background color simulation -->
                            <div class="absolute inset-0 bg-gradient-to-b from-indigo-950 to-slate-900 pointer-events-none"></div>

                            <!-- Interactive Photo Slot Boxes -->
                            <div
                                v-for="(slot, idx) in activeSlots"
                                :key="idx"
                                class="absolute border-2 border-dashed border-indigo-400 bg-indigo-500/20 rounded-lg flex flex-col items-center justify-center p-2 text-center text-white transition-all shadow-md group hover:bg-indigo-500/30"
                                :style="{
                                    left: `${Math.round(slot.x_position * scaleFactor)}px`,
                                    top: `${Math.round(slot.y_position * scaleFactor)}px`,
                                    width: `${Math.round(slot.width * scaleFactor)}px`,
                                    height: `${Math.round(slot.height * scaleFactor)}px`,
                                }"
                            >
                                <span class="font-extrabold text-xs tracking-wider uppercase text-indigo-300">Slot #{{ slot.slot_number }}</span>
                                <span class="text-[10px] text-white/70 font-mono">{{ slot.width }}&times;{{ slot.height }}</span>
                            </div>

                            <!-- Overlay Image if exists -->
                            <img
                                v-if="selectedTemplate.overlay_image_path"
                                :src="selectedTemplate.overlay_image_path"
                                class="absolute inset-0 w-full h-full object-contain pointer-events-none"
                            />

                            <!-- Footer watermark simulation -->
                            <div class="absolute bottom-2 inset-x-0 text-center text-[10px] text-white/50 font-bold pointer-events-none">
                                {{ event.name }} &bull; Luma Photobooth
                            </div>
                        </div>

                        <p class="text-[11px] text-gray-400 mt-4 text-center">
                            Ukuran tampilan disesuaikan secara proporsional. Nilai koordinat disimpan dalam resolusi cetak asli.
                        </p>
                    </div>

                    <!-- Right: Slot Coordinate Adjusters -->
                    <div class="lg:col-span-5 space-y-4">
                        <div class="p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center justify-between">
                                <span>Pengaturan Koordinat Slot</span>
                                <span class="text-xs text-indigo-500">{{ activeSlots.length }} Slot Terdeteksi</span>
                            </h3>

                            <div class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
                                <div
                                    v-for="(slot, idx) in activeSlots"
                                    :key="idx"
                                    class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700/80 space-y-3"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                        <span>FOTO SLOT #{{ slot.slot_number }}</span>
                                        <span class="text-[11px] text-gray-400 font-normal">Rasio: {{ slot.aspect_ratio || '4:3' }}</span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <label class="block text-[11px] text-gray-400 mb-0.5">Posisi X (px)</label>
                                            <input
                                                v-model.number="slot.x_position"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-950 text-xs font-mono font-bold"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-400 mb-0.5">Posisi Y (px)</label>
                                            <input
                                                v-model.number="slot.y_position"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-950 text-xs font-mono font-bold"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-400 mb-0.5">Lebar Slot (px)</label>
                                            <input
                                                v-model.number="slot.width"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-950 text-xs font-mono font-bold"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-400 mb-0.5">Tinggi Slot (px)</label>
                                            <input
                                                v-model.number="slot.height"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-950 text-xs font-mono font-bold"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <button
                                    @click="deleteTemplate(selectedTemplate)"
                                    class="inline-flex items-center gap-1.5 text-xs text-rose-500 hover:text-rose-600 font-semibold"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Hapus Template</span>
                                </button>

                                <button
                                    @click="saveSlotCoordinates"
                                    :disabled="isSavingSlots"
                                    class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm transition"
                                >
                                    Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Create New Frame Template -->
                <div v-if="isAddingTemplate" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="w-full max-w-lg p-8 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xl">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Tambah Template Frame Baru</h3>
                        <p class="text-xs text-gray-500 mb-6">Unggah frame overlay PNG transparan atau tentukan rasio canvas</p>

                        <form @submit.prevent="submitNewTemplate" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1">Nama Template *</label>
                                <input
                                    v-model="newTemplateForm.name"
                                    type="text"
                                    placeholder="Contoh: Classic 4-Strip Wedding"
                                    class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
                                    required
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Orientasi</label>
                                    <select
                                        v-model="newTemplateForm.orientation"
                                        class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
                                    >
                                        <option value="portrait">Portrait (Tegak)</option>
                                        <option value="strip">Strip (Kolom Panjang)</option>
                                        <option value="landscape">Landscape (Mendatar)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Jumlah Slot Foto</label>
                                    <input
                                        v-model.number="newTemplateForm.slot_count"
                                        type="number"
                                        min="1"
                                        max="6"
                                        class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Lebar Canvas (px)</label>
                                    <input
                                        v-model.number="newTemplateForm.canvas_width"
                                        type="number"
                                        class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Tinggi Canvas (px)</label>
                                    <input
                                        v-model.number="newTemplateForm.canvas_height"
                                        type="number"
                                        class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold mb-1">Unggah Frame PNG Transparan (Opsional)</label>
                                <input
                                    type="file"
                                    accept="image/png"
                                    @change="handleOverlayUpload"
                                    class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-600 dark:file:bg-indigo-900/40 dark:file:text-indigo-300"
                                />
                            </div>

                            <div class="pt-4 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    @click="isAddingTemplate = false"
                                    class="px-4 py-2 text-xs text-gray-500 hover:text-gray-800"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="newTemplateForm.processing"
                                    class="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50"
                                >
                                    {{ newTemplateForm.processing ? 'Menyimpan...' : 'Tambahkan Template' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>


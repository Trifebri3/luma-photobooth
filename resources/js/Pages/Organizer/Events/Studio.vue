<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    LayoutGrid, Plus, Save, ArrowLeft, Trash2, Sliders, Image, Check, AlertCircle,
    UploadCloud, Sparkles, Eye, EyeOff, Download, Move, Maximize2, Layers, AlignCenter
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

// Drag & drop, resize, and selection state
const selectedSlotIndex = ref(0);
const isDragging = ref(false);
const isResizing = ref(false);
const hasUnsavedChanges = ref(false);

let dragStartX = 0;
let dragStartY = 0;
let initialSlotX = 0;
let initialSlotY = 0;
let initialSlotW = 0;
let initialSlotH = 0;
let activeSlotIdx = -1;

// Visual options
const showSamplePhotos = ref(true);
const overlayOpacity = ref(1.0); // 1.0, 0.7, 0.4, 0.1

function formatOverlayUrl(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path.startsWith('/')) return path;
    return '/' + path;
}

function handleOverlayError(e) {
    console.warn('Frame overlay image failed to load, falling back:', e.target.src);
    if (!e.target.src.endsWith('kawaii-strip-3.png')) {
        e.target.src = '/frames/kawaii-strip-3.png';
    } else {
        e.target.style.display = 'none';
    }
}

function switchTemplate(tmpl) {
    if (hasUnsavedChanges.value) {
        const confirmSwitch = confirm('Ada perubahan posisi slot yang belum disimpan. Pindah template tanpa menyimpan?');
        if (!confirmSwitch) return;
    }
    selectedTemplateId.value = tmpl.id;
    activeSlots.value = JSON.parse(JSON.stringify(tmpl.slots || []));
    selectedSlotIndex.value = 0;
    hasUnsavedChanges.value = false;
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
const saveErrorMessage = ref('');

async function saveSlotCoordinates() {
    if (!selectedTemplate.value) return;
    isSavingSlots.value = true;
    saveSuccessMessage.value = '';
    saveErrorMessage.value = '';

    // Sanitize values to clean integers
    const payloadSlots = activeSlots.value.map(s => ({
        id: s.id,
        slot_number: Math.round(Number(s.slot_number)),
        x_position: Math.round(Number(s.x_position)),
        y_position: Math.round(Number(s.y_position)),
        width: Math.round(Number(s.width)),
        height: Math.round(Number(s.height)),
        aspect_ratio: s.aspect_ratio || '4:3',
    }));

    try {
        const res = await axios.post(
            `/organizer/events/${props.event.id}/templates/${selectedTemplate.value.id}/slots`,
            {
                slots: payloadSlots,
            }
        );
        if (res.data.success) {
            saveSuccessMessage.value = res.data.message || 'Koordinat slot foto berhasil disimpan!';
            hasUnsavedChanges.value = false;

            // Sync saved slots with in-memory templates
            if (res.data.slots && Array.isArray(res.data.slots)) {
                activeSlots.value = JSON.parse(JSON.stringify(res.data.slots));
                const tmpl = props.templates.find(t => t.id === selectedTemplate.value.id);
                if (tmpl) {
                    tmpl.slots = JSON.parse(JSON.stringify(res.data.slots));
                }
            } else {
                const tmpl = props.templates.find(t => t.id === selectedTemplate.value.id);
                if (tmpl) {
                    tmpl.slots = JSON.parse(JSON.stringify(payloadSlots));
                }
            }
            setTimeout(() => { saveSuccessMessage.value = ''; }, 3500);
        }
    } catch (err) {
        console.error('Error saving slot coordinates:', err);
        saveErrorMessage.value = err.response?.data?.message || 'Gagal menyimpan koordinat. Silakan coba lagi.';
        setTimeout(() => { saveErrorMessage.value = ''; }, 5000);
    } finally {
        isSavingSlots.value = false;
    }
}

// ----------------- DRAG & DROP AND RESIZE LOGIC -----------------

function selectSlot(idx) {
    selectedSlotIndex.value = idx;
}

function startDragSlot(idx, e) {
    if (e.button !== undefined && e.button !== 0) return; // Primary button only
    e.preventDefault();

    selectedSlotIndex.value = idx;
    activeSlotIdx = idx;
    isDragging.value = true;

    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;

    dragStartX = clientX;
    dragStartY = clientY;
    initialSlotX = activeSlots.value[idx].x_position;
    initialSlotY = activeSlots.value[idx].y_position;

    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup', onDragEnd);
    window.addEventListener('touchmove', onDragMove, { passive: false });
    window.addEventListener('touchend', onDragEnd);
}

function onDragMove(e) {
    if (!isDragging.value || activeSlotIdx === -1 || !selectedTemplate.value) return;
    if (e.cancelable) e.preventDefault();

    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;

    const deltaX = (clientX - dragStartX) / scaleFactor.value;
    const deltaY = (clientY - dragStartY) / scaleFactor.value;

    const slot = activeSlots.value[activeSlotIdx];
    const canvasW = selectedTemplate.value.canvas_width;
    const canvasH = selectedTemplate.value.canvas_height;

    let newX = Math.round(initialSlotX + deltaX);
    let newY = Math.round(initialSlotY + deltaY);

    // Keep slot within canvas boundaries
    newX = Math.max(0, Math.min(newX, canvasW - slot.width));
    newY = Math.max(0, Math.min(newY, canvasH - slot.height));

    if (slot.x_position !== newX || slot.y_position !== newY) {
        slot.x_position = newX;
        slot.y_position = newY;
        hasUnsavedChanges.value = true;
    }
}

function onDragEnd() {
    isDragging.value = false;
    activeSlotIdx = -1;
    window.removeEventListener('mousemove', onDragMove);
    window.removeEventListener('mouseup', onDragEnd);
    window.removeEventListener('touchmove', onDragMove);
    window.removeEventListener('touchend', onDragEnd);
}

function startResizeSlot(idx, e) {
    if (e.button !== undefined && e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();

    selectedSlotIndex.value = idx;
    activeSlotIdx = idx;
    isResizing.value = true;

    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;

    dragStartX = clientX;
    dragStartY = clientY;
    initialSlotW = activeSlots.value[idx].width;
    initialSlotH = activeSlots.value[idx].height;

    window.addEventListener('mousemove', onResizeMove);
    window.addEventListener('mouseup', onResizeEnd);
    window.addEventListener('touchmove', onResizeMove, { passive: false });
    window.addEventListener('touchend', onResizeEnd);
}

function onResizeMove(e) {
    if (!isResizing.value || activeSlotIdx === -1 || !selectedTemplate.value) return;
    if (e.cancelable) e.preventDefault();

    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;

    const deltaW = (clientX - dragStartX) / scaleFactor.value;
    const deltaH = (clientY - dragStartY) / scaleFactor.value;

    const slot = activeSlots.value[activeSlotIdx];
    const canvasW = selectedTemplate.value.canvas_width;
    const canvasH = selectedTemplate.value.canvas_height;

    const minSize = 80;
    const maxW = canvasW - slot.x_position;
    const maxH = canvasH - slot.y_position;

    let newW = Math.round(initialSlotW + deltaW);
    let newH = Math.round(initialSlotH + deltaH);

    newW = Math.max(minSize, Math.min(newW, maxW));
    newH = Math.max(minSize, Math.min(newH, maxH));

    if (slot.width !== newW || slot.height !== newH) {
        slot.width = newW;
        slot.height = newH;
        hasUnsavedChanges.value = true;
    }
}

function onResizeEnd() {
    isResizing.value = false;
    activeSlotIdx = -1;
    window.removeEventListener('mousemove', onResizeMove);
    window.removeEventListener('mouseup', onResizeEnd);
    window.removeEventListener('touchmove', onResizeMove);
    window.removeEventListener('touchend', onResizeEnd);
}

// Slot Alignment and Shape Helpers
function centerSlotHorizontally(idx) {
    if (!selectedTemplate.value || !activeSlots.value[idx]) return;
    const slot = activeSlots.value[idx];
    slot.x_position = Math.round((selectedTemplate.value.canvas_width - slot.width) / 2);
    hasUnsavedChanges.value = true;
}

function centerSlotVertically(idx) {
    if (!selectedTemplate.value || !activeSlots.value[idx]) return;
    const slot = activeSlots.value[idx];
    slot.y_position = Math.round((selectedTemplate.value.canvas_height - slot.height) / 2);
    hasUnsavedChanges.value = true;
}

function applyAspectRatio(idx, ratioW, ratioH) {
    if (!selectedTemplate.value || !activeSlots.value[idx]) return;
    const slot = activeSlots.value[idx];
    const newH = Math.round((slot.width * ratioH) / ratioW);
    const canvasH = selectedTemplate.value.canvas_height;

    if (slot.y_position + newH <= canvasH) {
        slot.height = newH;
    } else {
        slot.height = canvasH - slot.y_position;
        slot.width = Math.round((slot.height * ratioW) / ratioH);
    }
    slot.aspect_ratio = `${ratioW}:${ratioH}`;
    hasUnsavedChanges.value = true;
}

function addSlot() {
    if (!selectedTemplate.value) return;
    const nextNum = activeSlots.value.length + 1;
    const defaultW = Math.round(selectedTemplate.value.canvas_width * 0.85);
    const defaultH = Math.round(defaultW * 0.75); // 4:3
    const defaultX = Math.round((selectedTemplate.value.canvas_width - defaultW) / 2);
    const defaultY = Math.min(
        selectedTemplate.value.canvas_height - defaultH - 30,
        Math.max(40, 50 + (nextNum - 1) * 60)
    );

    activeSlots.value.push({
        slot_number: nextNum,
        x_position: defaultX,
        y_position: defaultY,
        width: defaultW,
        height: defaultH,
        aspect_ratio: '4:3',
    });
    selectedSlotIndex.value = activeSlots.value.length - 1;
    hasUnsavedChanges.value = true;
}

function removeSlot(idx) {
    if (activeSlots.value.length <= 1) {
        alert('Minimal harus ada 1 slot foto.');
        return;
    }
    if (confirm(`Apakah Anda yakin ingin menghapus Slot #${activeSlots.value[idx].slot_number}?`)) {
        activeSlots.value.splice(idx, 1);
        activeSlots.value.forEach((s, i) => {
            s.slot_number = i + 1;
        });
        selectedSlotIndex.value = Math.max(0, idx - 1);
        hasUnsavedChanges.value = true;
    }
}

// Keyboard shortcuts (Ctrl+S / Cmd+S) & navigation guard
function handleKeyDown(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        saveSlotCoordinates();
    }
}

function handleBeforeUnload(e) {
    if (hasUnsavedChanges.value) {
        e.preventDefault();
        e.returnValue = '';
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

// Download Template Acuan Desain (Canva / Photoshop / Figma)
function downloadDesignGuide(template) {
    if (!template) return;
    const canvasW = template.canvas_width || 600;
    const canvasH = template.canvas_height || 1800;

    const canvas = document.createElement('canvas');
    canvas.width = canvasW;
    canvas.height = canvasH;
    const ctx = canvas.getContext('2d');

    // 1. Background White
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, canvasW, canvasH);

    // 2. Outer Border
    ctx.lineWidth = 6;
    ctx.strokeStyle = '#0D9488';
    ctx.strokeRect(4, 4, canvasW - 8, canvasH - 8);

    // 3. Top Header Guidelines
    ctx.fillStyle = '#0F172A';
    ctx.font = 'bold 30px sans-serif';
    ctx.fillText(`ACUAN DESAIN: ${template.name.toUpperCase()}`, 30, 55);

    ctx.fillStyle = '#0D9488';
    ctx.font = 'bold 18px sans-serif';
    ctx.fillText(`Ukuran Kanvas: ${canvasW} x ${canvasH} px • Format Ekspor: PNG 24-bit Transparan`, 30, 90);

    ctx.fillStyle = '#64748B';
    ctx.font = '14px sans-serif';
    ctx.fillText('PETUNJUK: Masukkan elemen/desain di sekeliling kotak. Buat kotak foto TRANSPARAN saat diekspor.', 30, 115);

    // 4. Slots
    const slots = template.slots || activeSlots.value || [];
    slots.forEach(slot => {
        // Soft translucent fill for slot area
        ctx.fillStyle = 'rgba(13, 148, 136, 0.08)';
        ctx.fillRect(slot.x_position, slot.y_position, slot.width, slot.height);

        // Dashed border for slot
        ctx.lineWidth = 4;
        ctx.strokeStyle = '#0D9488';
        ctx.setLineDash([12, 8]);
        ctx.strokeRect(slot.x_position, slot.y_position, slot.width, slot.height);
        ctx.setLineDash([]);

        // Diagonal guides
        ctx.strokeStyle = 'rgba(13, 148, 136, 0.2)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(slot.x_position, slot.y_position);
        ctx.lineTo(slot.x_position + slot.width, slot.y_position + slot.height);
        ctx.moveTo(slot.x_position, slot.y_position + slot.height);
        ctx.lineTo(slot.x_position + slot.width, slot.y_position);
        ctx.stroke();

        // Slot Labels
        const cx = slot.x_position + (slot.width / 2);
        const cy = slot.y_position + (slot.height / 2);

        ctx.fillStyle = '#0D9488';
        ctx.font = 'bold 24px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(`AREA FOTO SLOT #${slot.slot_number}`, cx, cy - 20);

        ctx.fillStyle = '#0F172A';
        ctx.font = 'bold 20px monospace';
        ctx.fillText(`${slot.width} x ${slot.height} px (${slot.aspect_ratio || '4:3'})`, cx, cy + 12);

        ctx.fillStyle = '#E11D48'; // Rose 600
        ctx.font = 'bold 15px sans-serif';
        ctx.fillText('[ BUAT TRANSPARAN / BOLONG ]', cx, cy + 42);
        ctx.textAlign = 'start';
    });

    // 5. Footer Branding
    ctx.fillStyle = '#94A3B8';
    ctx.font = 'bold 16px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('Luma Photobooth • by YOTA INOVASI NUSANTARA', canvasW / 2, canvasH - 35);

    // 6. Download Trigger
    const safeTitle = (template.name || 'template').replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
    const link = document.createElement('a');
    link.download = `Acuan-Desain-${safeTitle}-${canvasW}x${canvasH}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function downloadPresetGuide(preset) {
    let slots = [];
    const count = preset.slots;
    const w = preset.width;
    const h = preset.height;

    if (preset.id === 'grid_4') {
        const slotW = Math.floor((w - 180) / 2);
        const slotH = Math.floor((h - 260) / 2);
        slots = [
            { slot_number: 1, x_position: 60, y_position: 80, width: slotW, height: slotH, aspect_ratio: '3:4' },
            { slot_number: 2, x_position: 120 + slotW, y_position: 80, width: slotW, height: slotH, aspect_ratio: '3:4' },
            { slot_number: 3, x_position: 60, y_position: 140 + slotH, width: slotW, height: slotH, aspect_ratio: '3:4' },
            { slot_number: 4, x_position: 120 + slotW, y_position: 140 + slotH, width: slotW, height: slotH, aspect_ratio: '3:4' },
        ];
    } else if (preset.id === 'polaroid_1') {
        const side = Math.min(w - 160, h - 350);
        slots = [
            { slot_number: 1, x_position: Math.floor((w - side) / 2), y_position: 100, width: side, height: side, aspect_ratio: '1:1' }
        ];
    } else if (preset.id === 'duo_2') {
        const slotH = Math.floor((h - 240) / 2);
        const slotW = w - 140;
        slots = [
            { slot_number: 1, x_position: 70, y_position: 80, width: slotW, height: slotH, aspect_ratio: '16:9' },
            { slot_number: 2, x_position: 70, y_position: 80 + slotH + 50, width: slotW, height: slotH, aspect_ratio: '16:9' },
        ];
    } else {
        const slotH = Math.floor((h - 120 - ((count - 1) * 30)) / count);
        const slotW = w - 80;
        for (let i = 1; i <= count; i++) {
            slots.push({
                slot_number: i,
                x_position: 40,
                y_position: 60 + ((i - 1) * (slotH + 30)),
                width: slotW,
                height: slotH,
                aspect_ratio: '4:3',
            });
        }
    }

    downloadDesignGuide({
        name: preset.name,
        canvas_width: preset.width,
        canvas_height: preset.height,
        slots: slots,
    });
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
                                Drag & Drop Editor
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Atur posisi kotak foto secara visual (tarik & geser) di balik frame PNG transparan untuk {{ event.name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        v-if="selectedTemplate"
                        @click="downloadDesignGuide(selectedTemplate)"
                        type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-slate-50 text-teal-700 text-xs font-bold rounded-xl border border-teal-200 shadow-xs transition hover:border-teal-400"
                        title="Download template gambar acuan untuk didesain di Canva, Photoshop, atau Figma"
                    >
                        <Download class="w-4 h-4 text-teal-600" />
                        <span>Download Acuan Desain</span>
                    </button>

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
                        :class="[
                            'inline-flex items-center gap-2 px-4 py-2 text-white text-xs font-extrabold rounded-xl shadow-md transition disabled:opacity-50',
                            hasUnsavedChanges
                                ? 'bg-teal-600 hover:bg-teal-500 shadow-teal-600/30 ring-2 ring-teal-400 ring-offset-1 animate-pulse'
                                : 'bg-teal-600 hover:bg-teal-500 shadow-teal-600/20'
                        ]"
                        title="Simpan posisi dan ukuran slot (Shortcut: Ctrl + S)"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ isSavingSlots ? 'Menyimpan...' : 'Simpan Koordinat' }}</span>
                        <span v-if="hasUnsavedChanges" class="w-2 h-2 rounded-full bg-amber-300"></span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Alert Feedback Messages -->
                <div v-if="saveSuccessMessage" class="p-4 rounded-2xl bg-teal-50 border border-teal-200 text-teal-800 text-xs font-bold flex items-center justify-between shadow-xs">
                    <div class="flex items-center gap-2">
                        <Check class="w-4 h-4 text-teal-600" />
                        <span>{{ saveSuccessMessage }}</span>
                    </div>
                    <span class="text-[10px] text-teal-600 bg-teal-100/70 px-2 py-0.5 rounded-full font-mono">Tersimpan</span>
                </div>

                <div v-if="saveErrorMessage" class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-xs">
                    <AlertCircle class="w-4 h-4 text-rose-600" />
                    <span>{{ saveErrorMessage }}</span>
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
                    
                    <!-- Left: Visual Canvas Preview Area with Drag & Drop -->
                    <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm flex flex-col items-center">
                        <div class="w-full flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 mb-4 pb-3 border-b border-slate-100">
                            <div>
                                <span class="font-extrabold text-sm text-slate-900 block">{{ selectedTemplate.name }}</span>
                                <span class="font-mono text-slate-400 text-[11px]">Resolusi Kanvas: {{ selectedTemplate.canvas_width }} &times; {{ selectedTemplate.canvas_height }} px</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Transparansi Frame Toggle -->
                                <div class="inline-flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-[11px] font-bold">
                                    <span class="px-2 text-slate-500 flex items-center gap-1">
                                        <Layers class="w-3 h-3 text-slate-400" />
                                        <span>Frame:</span>
                                    </span>
                                    <button
                                        type="button"
                                        @click="overlayOpacity = 1.0"
                                        :class="['px-2 py-0.5 rounded-lg transition', overlayOpacity === 1.0 ? 'bg-white text-teal-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900']"
                                    >
                                        100%
                                    </button>
                                    <button
                                        type="button"
                                        @click="overlayOpacity = 0.5"
                                        :class="['px-2 py-0.5 rounded-lg transition', overlayOpacity === 0.5 ? 'bg-white text-teal-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900']"
                                        title="Transparan 50% untuk melihat posisi slot di balik frame dengan jelas"
                                    >
                                        50%
                                    </button>
                                    <button
                                        type="button"
                                        @click="overlayOpacity = 0.05"
                                        :class="['px-2 py-0.5 rounded-lg transition', overlayOpacity === 0.05 ? 'bg-white text-teal-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900']"
                                        title="Sembunyikan frame sementara"
                                    >
                                        Off
                                    </button>
                                </div>

                                <button
                                    @click="showSamplePhotos = !showSamplePhotos"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition"
                                >
                                    <Eye v-if="showSamplePhotos" class="w-3.5 h-3.5 text-teal-600" />
                                    <EyeOff v-else class="w-3.5 h-3.5 text-slate-400" />
                                    <span>{{ showSamplePhotos ? 'Foto Contoh' : 'Polos' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Instructions Pill -->
                        <div class="w-full mb-4 px-3.5 py-2 rounded-2xl bg-teal-50/70 border border-teal-200 text-[11px] text-teal-900 flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 font-medium">
                                <Move class="w-3.5 h-3.5 text-teal-600 shrink-0" />
                                <span><strong>Drag & Drop Aktif:</strong> Klik & geser kotak foto langsung di kanvas. Tarik sudut kanan bawah untuk resize.</span>
                            </span>
                            <span v-if="hasUnsavedChanges" class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-extrabold shrink-0 border border-amber-300">
                                Belum Disimpan
                            </span>
                        </div>

                        <!-- Interactive Canvas Simulator -->
                        <div
                            class="relative rounded-2xl border-4 border-slate-300 shadow-2xl overflow-hidden bg-white select-none"
                            :style="{
                                width: `${previewContainerWidth}px`,
                                height: `${previewContainerHeight}px`,
                                backgroundImage: `radial-gradient(#CBD5E1 0.75px, transparent 0.75px), radial-gradient(#CBD5E1 0.75px, #F8FAFC 0.75px)`,
                                backgroundSize: '16px 16px',
                                backgroundPosition: '0 0, 8px 8px',
                            }"
                        >
                            <!-- Photo Slots (Draggable & Resizable) -->
                            <div
                                v-for="(slot, idx) in activeSlots"
                                :key="idx"
                                @mousedown="startDragSlot(idx, $event)"
                                @touchstart="startDragSlot(idx, $event)"
                                @click="selectSlot(idx)"
                                :class="[
                                    'absolute rounded-lg overflow-visible flex flex-col items-center justify-center text-center transition-shadow select-none cursor-move group',
                                    selectedSlotIndex === idx
                                        ? 'ring-2 ring-teal-500 shadow-xl z-20'
                                        : 'hover:ring-2 hover:ring-teal-400 shadow-md z-5'
                                ]"
                                :style="{
                                    left: `${Math.round(slot.x_position * scaleFactor)}px`,
                                    top: `${Math.round(slot.y_position * scaleFactor)}px`,
                                    width: `${Math.round(slot.width * scaleFactor)}px`,
                                    height: `${Math.round(slot.height * scaleFactor)}px`,
                                }"
                            >
                                <!-- Sample Photo Container -->
                                <div class="absolute inset-0 rounded-lg overflow-hidden pointer-events-none">
                                    <img
                                        v-if="showSamplePhotos"
                                        :src="`https://picsum.photos/seed/luma${slot.slot_number + selectedTemplate.id * 5}/400/500`"
                                        alt="Sample"
                                        class="w-full h-full object-cover pointer-events-none select-none"
                                        draggable="false"
                                    />
                                    <div v-else class="w-full h-full bg-slate-200/80 flex items-center justify-center"></div>
                                </div>

                                <!-- Slot Marker Overlay -->
                                <div
                                    :class="[
                                        'absolute inset-0 rounded-lg border-2 border-dashed flex flex-col items-center justify-center p-2 pointer-events-none transition-colors',
                                        selectedSlotIndex === idx
                                            ? 'border-teal-500 bg-teal-500/20'
                                            : 'border-teal-400/80 bg-teal-500/10 group-hover:bg-teal-500/15'
                                    ]"
                                >
                                    <div class="inline-flex items-center gap-1 font-extrabold text-[11px] tracking-wider uppercase bg-teal-600 text-white px-2 py-0.5 rounded shadow pointer-events-auto">
                                        <Move class="w-2.5 h-2.5 opacity-80" />
                                        <span>Slot #{{ slot.slot_number }}</span>
                                    </div>
                                    <span class="text-[9px] text-teal-950 font-mono mt-1 font-bold bg-white/95 px-1.5 py-0.5 rounded shadow-xs">
                                        {{ slot.width }}&times;{{ slot.height }}
                                    </span>
                                </div>

                                <!-- Resize Handle (Bottom-Right Corner) -->
                                <div
                                    @mousedown.stop="startResizeSlot(idx, $event)"
                                    @touchstart.stop="startResizeSlot(idx, $event)"
                                    class="absolute -bottom-1 -right-1 w-6 h-6 bg-teal-600 hover:bg-teal-700 text-white flex items-center justify-center rounded-tl-lg rounded-br cursor-se-resize shadow-lg z-30 transition hover:scale-115 active:scale-95 border border-white"
                                    title="Tarik untuk resize ukuran kotak foto"
                                >
                                    <svg class="w-3 h-3 text-white pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <line x1="21" y1="9" x2="9" y2="21" />
                                        <line x1="21" y1="15" x2="15" y2="21" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Transparent PNG Frame Overlay on TOP of photos -->
                            <img
                                v-if="selectedTemplate.overlay_image_path"
                                :src="formatOverlayUrl(selectedTemplate.overlay_image_path)"
                                @error="handleOverlayError"
                                class="absolute inset-0 w-full h-full object-contain pointer-events-none z-10 transition-opacity duration-200"
                                :style="{ opacity: overlayOpacity }"
                                draggable="false"
                            />
                        </div>

                        <!-- Canvas Legend and Branding -->
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-4 text-xs text-slate-500">
                            <span class="flex items-center gap-1.5 font-medium">
                                <span class="w-3 h-3 rounded-full bg-teal-500"></span> Kotak Slot Foto (Bisa Digeser)
                            </span>
                            <span class="flex items-center gap-1.5 font-medium">
                                <span class="w-3 h-3 rounded-full border border-slate-300 bg-white"></span> Frame PNG Transparan
                            </span>
                            <span class="text-[11px] text-slate-400 font-mono">
                                • by YOTA INOVASI NUSANTARA
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

                                <span v-if="selectedTemplate.overlay_image_path" class="text-[11px] font-bold text-teal-600 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">
                                    PNG Aktif Terpasang
                                </span>
                                <span v-else class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200">
                                    Belum Ada PNG
                                </span>
                            </div>

                            <p class="text-xs text-slate-500 leading-relaxed">
                                Frame harus berformat <strong>PNG transparan</strong> tanpa latar belakang. Bagian foto pengunjung akan tembus terlihat melalui lubang transparan frame.
                            </p>

                            <!-- Download Template Acuan Desain Box -->
                            <div class="p-3.5 rounded-2xl bg-teal-50/70 border border-teal-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <div class="text-xs font-bold text-teal-900 flex items-center gap-1.5">
                                        <Download class="w-3.5 h-3.5 text-teal-600" />
                                        <span>Download Template Acuan Ukuran</span>
                                    </div>
                                    <div class="text-[11px] text-teal-700 mt-0.5">
                                        Gunakan acuan ini di Canva atau Photoshop agar posisi lubang foto presisi.
                                    </div>
                                </div>
                                <button
                                    @click="downloadDesignGuide(selectedTemplate)"
                                    type="button"
                                    class="shrink-0 px-3.5 py-1.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5"
                                >
                                    <Download class="w-3.5 h-3.5" />
                                    <span>Unduh PNG</span>
                                </button>
                            </div>

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

                        <!-- Card 2: Interactive Slot Editor & Coordinates -->
                        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-extrabold text-sm text-slate-900 flex items-center gap-2">
                                    <Sliders class="w-4 h-4 text-teal-600" />
                                    <span>Pengaturan Slot Foto</span>
                                </h3>
                                
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="addSlot"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-700 text-xs font-bold transition border border-teal-200"
                                        title="Tambah slot foto baru pada template ini"
                                    >
                                        <Plus class="w-3.5 h-3.5" />
                                        <span>Tambah Slot</span>
                                    </button>

                                    <span class="text-xs font-bold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">
                                        {{ activeSlots.length }} Slot
                                    </span>
                                </div>
                            </div>

                            <p class="text-xs text-slate-500">
                                Pilih slot untuk melihat posisinya, atau geser langsung di kanvas simulator sebelah kiri.
                            </p>

                            <!-- Slot List Cards -->
                            <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
                                <div
                                    v-for="(slot, idx) in activeSlots"
                                    :key="idx"
                                    @click="selectSlot(idx)"
                                    :class="[
                                        'p-3.5 rounded-2xl border transition-all space-y-2.5 cursor-pointer',
                                        selectedSlotIndex === idx
                                            ? 'border-teal-500 bg-teal-50/40 ring-1 ring-teal-500 shadow-xs'
                                            : 'border-slate-200 bg-slate-50 hover:border-slate-300'
                                    ]"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold text-slate-800">
                                        <span class="text-teal-700 flex items-center gap-1.5">
                                            <span class="w-2.5 h-2.5 rounded-full" :class="selectedSlotIndex === idx ? 'bg-teal-600 animate-pulse' : 'bg-teal-400'"></span>
                                            FOTO SLOT #{{ slot.slot_number }}
                                            <span v-if="selectedSlotIndex === idx" class="text-[10px] text-teal-700 font-semibold bg-teal-100 px-1.5 py-0.2 rounded">
                                                Aktif
                                            </span>
                                        </span>
                                        
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] text-slate-400 font-mono">
                                                {{ slot.width }}&times;{{ slot.height }} px
                                            </span>
                                            <button
                                                v-if="activeSlots.length > 1"
                                                type="button"
                                                @click.stop="removeSlot(idx)"
                                                class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                                                title="Hapus slot ini"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Quick Alignment & Aspect Ratio Helpers -->
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-slate-200/60">
                                        <button
                                            type="button"
                                            @click.stop="centerSlotHorizontally(idx)"
                                            class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-300 text-slate-600 hover:text-teal-700 text-[10px] font-bold transition flex items-center gap-1 shadow-2xs"
                                            title="Posisikan pas di tengah horizontal kanvas"
                                        >
                                            <AlignCenter class="w-3 h-3 text-teal-600" />
                                            <span>Rata Tengah H</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click.stop="centerSlotVertically(idx)"
                                            class="px-2 py-0.5 rounded-lg bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-300 text-slate-600 hover:text-teal-700 text-[10px] font-bold transition flex items-center gap-1 shadow-2xs"
                                            title="Posisikan pas di tengah vertikal kanvas"
                                        >
                                            <AlignCenter class="w-3 h-3 rotate-90 text-teal-600" />
                                            <span>Rata Tengah V</span>
                                        </button>

                                        <span class="text-slate-300 text-xs">|</span>

                                        <button
                                            type="button"
                                            @click.stop="applyAspectRatio(idx, 1, 1)"
                                            class="px-1.5 py-0.5 rounded-lg bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-300 text-slate-600 hover:text-teal-700 text-[10px] font-bold transition shadow-2xs"
                                        >
                                            1:1
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="applyAspectRatio(idx, 4, 3)"
                                            class="px-1.5 py-0.5 rounded-lg bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-300 text-slate-600 hover:text-teal-700 text-[10px] font-bold transition shadow-2xs"
                                        >
                                            4:3
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="applyAspectRatio(idx, 3, 4)"
                                            class="px-1.5 py-0.5 rounded-lg bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-300 text-slate-600 hover:text-teal-700 text-[10px] font-bold transition shadow-2xs"
                                        >
                                            3:4
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="applyAspectRatio(idx, 16, 9)"
                                            class="px-1.5 py-0.5 rounded-lg bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-300 text-slate-600 hover:text-teal-700 text-[10px] font-bold transition shadow-2xs"
                                        >
                                            16:9
                                        </button>
                                    </div>

                                    <!-- Numeric Inputs for Fine Precision -->
                                    <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Posisi X (px)</label>
                                            <input
                                                v-model.number="slot.x_position"
                                                @input="hasUnsavedChanges = true"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Posisi Y (px)</label>
                                            <input
                                                v-model.number="slot.y_position"
                                                @input="hasUnsavedChanges = true"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Lebar Slot (px)</label>
                                            <input
                                                v-model.number="slot.width"
                                                @input="hasUnsavedChanges = true"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Tinggi Slot (px)</label>
                                            <input
                                                v-model.number="slot.height"
                                                @input="hasUnsavedChanges = true"
                                                type="number"
                                                class="w-full py-1.5 px-2.5 rounded-xl border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions: Delete Template & Save Button -->
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
                                    :class="[
                                        'px-5 py-2.5 rounded-xl text-white text-xs font-extrabold shadow-sm transition flex items-center gap-2',
                                        hasUnsavedChanges
                                            ? 'bg-teal-600 hover:bg-teal-500 ring-2 ring-teal-400 ring-offset-1 animate-pulse'
                                            : 'bg-teal-600 hover:bg-teal-500'
                                    ]"
                                >
                                    <Save class="w-4 h-4" />
                                    <span>{{ isSavingSlots ? 'Menyimpan...' : 'Simpan Posisi (Ctrl+S)' }}</span>
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
                            <button @click="isAddingTemplate = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 text-xl font-bold">
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

                            <!-- Download Acuan Preset untuk Desainer -->
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                                <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5 mb-1">
                                    <Download class="w-3.5 h-3.5 text-teal-600" />
                                    <span>Download Template Acuan Desain (Canva / Photoshop):</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mb-2.5">
                                    Pilih format layout yang ingin Anda buat. Buka file acuan ini di Canva atau Photoshop agar letak kotak foto 100% presisi.
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="p in presets"
                                        :key="p.id"
                                        type="button"
                                        @click="downloadPresetGuide(p)"
                                        class="px-3 py-1.5 rounded-xl bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-300 text-slate-700 hover:text-teal-700 text-[11px] font-bold flex items-center gap-1.5 transition shadow-2xs"
                                    >
                                        <Download class="w-3 h-3 text-teal-600" />
                                        <span>{{ p.name.split('(')[0].trim() }}</span>
                                    </button>
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

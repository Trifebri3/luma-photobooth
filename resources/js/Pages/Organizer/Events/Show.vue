<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Calendar, Users, Camera, Smartphone, LayoutGrid, Settings, Plus, ExternalLink,
    Copy, Check, ShieldCheck, MessageSquare, ArrowLeft, Trash2, Key, Download, Eye,
    X, ChevronLeft, ChevronRight, Sparkles
} from 'lucide-vue-next';

const props = defineProps({
    event: Object,
    photosCount: Number,
    boothUrl: String,
});

const activeTab = ref('photos'); // 'photos', 'devices', 'frames', 'visitors', 'settings'
const isCopied = ref(false);

function copyBoothUrl() {
    navigator.clipboard.writeText(props.boothUrl);
    isCopied.value = true;
    setTimeout(() => { isCopied.value = false; }, 2000);
}

// Lightbox modal for photo preview
const isLightboxOpen = ref(false);
const activeSessionIndex = ref(0);
const copiedTokenId = ref(null);

const availableSessionsWithPhotos = () => {
    return (props.event.sessions || []).filter(s => s.photo);
};

function openLightbox(index) {
    activeSessionIndex.value = index;
    isLightboxOpen.value = true;
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    isLightboxOpen.value = false;
    document.body.style.overflow = '';
}

function prevPhoto() {
    if (activeSessionIndex.value > 0) {
        activeSessionIndex.value--;
    }
}

function nextPhoto() {
    const list = availableSessionsWithPhotos();
    if (activeSessionIndex.value < list.length - 1) {
        activeSessionIndex.value++;
    }
}

function copyPrivateLink(token) {
    if (!token) return;
    const url = `${window.location.origin}/p/${token}`;
    navigator.clipboard.writeText(url);
    copiedTokenId.value = token;
    setTimeout(() => { copiedTokenId.value = null; }, 2000);
}

function deletePhoto(photoId) {
    if (confirm('Apakah Anda yakin ingin menghapus foto ini? File akan dihapus permanen.')) {
        router.delete(route('organizer.photos.destroy', photoId), {
            onSuccess: () => {
                if (isLightboxOpen.value) {
                    closeLightbox();
                }
            },
        });
    }
}

// Add Device Form
const isAddingDevice = ref(false);
const deviceForm = useForm({
    device_name: '',
});
function submitDevice() {
    deviceForm.post(route('organizer.events.devices.store', props.event.id), {
        onSuccess: () => {
            isAddingDevice.value = false;
            deviceForm.reset();
        },
    });
}

// Settings Form
const settingsForm = useForm({
    name: props.event.name,
    description: props.event.description || '',
    location: props.event.location || '',
    start_date: props.event.start_date?.substring(0, 16) || '',
    end_date: props.event.end_date?.substring(0, 16) || '',
    status: props.event.status,
    max_sessions_per_visitor: props.event.settings?.max_sessions_per_visitor || 3,
    photos_per_session: props.event.settings?.photos_per_session || 3,
    countdown_seconds: props.event.settings?.countdown_seconds || 5,
    max_retakes: props.event.settings?.max_retakes || 2,
    is_unlimited_session: Boolean(props.event.settings?.is_unlimited_session),
    enable_whatsapp_delivery: Boolean(props.event.settings?.enable_whatsapp_delivery),
    token_expiry_days: props.event.settings?.token_expiry_days || 30,
});

function submitSettings() {
    settingsForm.put(route('organizer.events.update', props.event.id));
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 KB';
    return Math.round(bytes / 1024) + ' KB';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="`${event.name} - Kelola Event`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('organizer.events.index')"
                        class="p-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-extrabold text-slate-900 leading-tight">
                                {{ event.name }}
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-teal-50 text-teal-700 border border-teal-200">
                                {{ event.status }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ event.location || 'Lokasi belum diset' }} &bull; Slug: <span class="font-mono text-teal-700 font-bold">{{ event.slug }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="copyBoothUrl"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                    >
                        <Copy v-if="!isCopied" class="w-3.5 h-3.5 text-slate-400" />
                        <Check v-else class="w-3.5 h-3.5 text-teal-600" />
                        <span>{{ isCopied ? 'Tersalin!' : 'Salin Link Booth' }}</span>
                    </button>

                    <a
                        :href="boothUrl"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-extrabold shadow-md shadow-teal-600/20 transition hover:scale-102"
                    >
                        <span>Buka Layar Kiosk</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Navigation Tabs (Teal Accents) -->
                <div class="flex items-center gap-2 border-b border-slate-200 pb-3 text-xs font-bold overflow-x-auto scrollbar-none">
                    <button
                        @click="activeTab = 'photos'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap flex items-center gap-2 border',
                            activeTab === 'photos'
                                ? 'bg-teal-600 text-white border-teal-600 shadow-md shadow-teal-600/20'
                                : 'bg-white text-slate-600 border-slate-200 hover:border-teal-400 hover:bg-teal-50/50'
                        ]"
                    >
                        <Camera class="w-3.5 h-3.5" />
                        <span>Galeri Foto Event ({{ photosCount ?? event.sessions?.length ?? 0 }})</span>
                    </button>

                    <button
                        @click="activeTab = 'devices'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap flex items-center gap-2 border',
                            activeTab === 'devices'
                                ? 'bg-teal-600 text-white border-teal-600 shadow-md shadow-teal-600/20'
                                : 'bg-white text-slate-600 border-slate-200 hover:border-teal-400 hover:bg-teal-50/50'
                        ]"
                    >
                        <Smartphone class="w-3.5 h-3.5" />
                        <span>Perangkat Booth ({{ event.devices?.length || 0 }})</span>
                    </button>

                    <button
                        @click="activeTab = 'frames'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap flex items-center gap-2 border',
                            activeTab === 'frames'
                                ? 'bg-teal-600 text-white border-teal-600 shadow-md shadow-teal-600/20'
                                : 'bg-white text-slate-600 border-slate-200 hover:border-teal-400 hover:bg-teal-50/50'
                        ]"
                    >
                        <LayoutGrid class="w-3.5 h-3.5" />
                        <span>Template Frame ({{ event.frame_templates?.length || 0 }})</span>
                    </button>

                    <button
                        @click="activeTab = 'visitors'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap flex items-center gap-2 border',
                            activeTab === 'visitors'
                                ? 'bg-teal-600 text-white border-teal-600 shadow-md shadow-teal-600/20'
                                : 'bg-white text-slate-600 border-slate-200 hover:border-teal-400 hover:bg-teal-50/50'
                        ]"
                    >
                        <Users class="w-3.5 h-3.5" />
                        <span>Data Pengunjung ({{ event.visitors?.length || 0 }})</span>
                    </button>

                    <button
                        @click="activeTab = 'settings'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap flex items-center gap-2 border',
                            activeTab === 'settings'
                                ? 'bg-teal-600 text-white border-teal-600 shadow-md shadow-teal-600/20'
                                : 'bg-white text-slate-600 border-slate-200 hover:border-teal-400 hover:bg-teal-50/50'
                        ]"
                    >
                        <Settings class="w-3.5 h-3.5" />
                        <span>Pengaturan Event</span>
                    </button>
                </div>

                <!-- TAB 1: GALERI FOTO EVENT -->
                <div v-if="activeTab === 'photos'" class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-3xl bg-white border border-slate-200 shadow-sm">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-extrabold text-base text-slate-900">Foto Pengunjung Event Ini</h3>
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                    {{ photosCount ?? event.sessions?.length ?? 0 }} Foto
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Seluruh foto hasil komposit frame yang telah diambil pengunjung di booth
                            </p>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <a
                                :href="route('organizer.events.photos.zip', event.id)"
                                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-slate-50 text-teal-700 text-xs font-bold rounded-xl border border-teal-200 shadow-2xs transition hover:border-teal-400"
                                title="Download semua foto event ini dalam satu file ZIP"
                            >
                                <Download class="w-4 h-4 text-teal-600" />
                                <span>Download ZIP Event</span>
                            </a>

                            <Link
                                :href="route('organizer.photos.index', { event_id: event.id })"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold rounded-xl shadow-sm transition"
                            >
                                <span>Buka di Galeri Penuh</span>
                                <ExternalLink class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>

                    <!-- Photo Grid -->
                    <div v-if="availableSessionsWithPhotos().length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <div
                            v-for="(session, sIdx) in availableSessionsWithPhotos()"
                            :key="session.id"
                            class="group relative rounded-2xl bg-white border border-slate-200 shadow-xs hover:shadow-xl hover:border-teal-400 transition-all duration-200 overflow-hidden flex flex-col"
                        >
                            <!-- Thumbnail -->
                            <div
                                @click="openLightbox(sIdx)"
                                class="relative aspect-[3/4] bg-slate-100 cursor-pointer overflow-hidden flex items-center justify-center"
                                :style="{
                                    backgroundImage: `radial-gradient(#CBD5E1 0.75px, transparent 0.75px), radial-gradient(#CBD5E1 0.75px, #F8FAFC 0.75px)`,
                                    backgroundSize: '12px 12px',
                                    backgroundPosition: '0 0, 6px 6px',
                                }"
                            >
                                <img
                                    :src="route('organizer.photos.image', session.photo.id)"
                                    :alt="session.visitor?.name || 'Photo'"
                                    loading="lazy"
                                    class="w-full h-full object-contain p-1.5 transition-transform duration-300 group-hover:scale-103"
                                />

                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                    <span class="p-2 rounded-xl bg-white/90 text-slate-800 shadow-md">
                                        <Eye class="w-4 h-4 text-teal-600" />
                                    </span>
                                </div>

                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-mono font-bold shadow-xs">
                                    #{{ session.session_number }}
                                </span>
                            </div>

                            <!-- Footer -->
                            <div class="p-3 flex-1 flex flex-col justify-between border-t border-slate-100 text-xs">
                                <div>
                                    <div class="font-extrabold text-slate-900 truncate" :title="session.visitor?.name">
                                        {{ session.visitor?.name || 'Pengunjung Anonim' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        {{ formatDate(session.created_at) }}
                                    </div>
                                </div>

                                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        {{ formatBytes(session.photo.file_size_bytes) }}
                                    </span>

                                    <div class="flex items-center gap-1">
                                        <a
                                            :href="route('organizer.photos.download', session.photo.id)"
                                            class="p-1 rounded-lg text-slate-400 hover:text-teal-600 hover:bg-teal-50 transition"
                                            title="Download Foto Asli"
                                        >
                                            <Download class="w-3.5 h-3.5" />
                                        </a>

                                        <button
                                            v-if="session.photo.accessTokens?.[0]?.token"
                                            @click="copyPrivateLink(session.photo.accessTokens[0].token)"
                                            type="button"
                                            class="p-1 rounded-lg text-slate-400 hover:text-teal-600 hover:bg-teal-50 transition"
                                            title="Salin Link Galeri Pengunjung"
                                        >
                                            <Check v-if="copiedTokenId === session.photo.accessTokens[0].token" class="w-3.5 h-3.5 text-teal-600" />
                                            <Copy v-else class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="p-12 rounded-3xl bg-white border border-slate-200 shadow-sm text-center max-w-md mx-auto">
                        <div class="w-16 h-16 rounded-3xl bg-teal-50 text-teal-600 flex items-center justify-center mx-auto mb-4 border border-teal-100">
                            <Camera class="w-8 h-8" />
                        </div>
                        <h4 class="font-extrabold text-sm text-slate-900 mb-1">Belum Ada Foto Terambil</h4>
                        <p class="text-xs text-slate-500 mb-5">
                            Buka layar booth di station Anda dan coba berfoto untuk melihat hasil komposit langsung di sini.
                        </p>
                        <a
                            :href="boothUrl"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold rounded-xl transition shadow-sm"
                        >
                            <span>Buka Layar Kiosk Booth</span>
                            <ExternalLink class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>

                <!-- TAB 2: PERANGKAT BOOTH (DEVICES) -->
                <div v-if="activeTab === 'devices'" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-extrabold text-base text-slate-900">Perangkat Photobooth di Lokasi</h3>
                            <p class="text-xs text-slate-500">Hubungkan PC, Touchscreen, atau Tablet dengan kode aktivasi</p>
                        </div>

                        <button
                            @click="isAddingDevice = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold rounded-xl shadow-sm transition"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Tambah Perangkat</span>
                        </button>
                    </div>

                    <!-- Modal Add Device -->
                    <div v-if="isAddingDevice" class="p-6 rounded-2xl bg-teal-50/60 border border-teal-200">
                        <h4 class="font-bold text-sm text-teal-900 mb-2">Tambah Perangkat Booth Baru</h4>
                        <div class="flex gap-3">
                            <input
                                v-model="deviceForm.device_name"
                                type="text"
                                placeholder="Nama perangkat (misal: Booth Pintu Masuk #02)"
                                class="flex-1 py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                            />
                            <button
                                @click="submitDevice"
                                :disabled="deviceForm.processing || !deviceForm.device_name"
                                class="px-4 py-2 rounded-xl bg-teal-600 text-white text-xs font-bold hover:bg-teal-500 disabled:opacity-50"
                            >
                                Simpan
                            </button>
                            <button
                                @click="isAddingDevice = false"
                                class="px-3 py-2 rounded-xl text-xs text-slate-500 hover:text-slate-800"
                            >
                                Batal
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="dev in event.devices"
                            :key="dev.id"
                            class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-bold text-sm text-slate-900">{{ dev.device_name }}</span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                        dev.is_active ? 'bg-teal-50 text-teal-700 border border-teal-200' : 'bg-slate-100 text-slate-600'
                                    ]"
                                >
                                    {{ dev.is_active ? 'Terhubung' : 'Belum Pairing' }}
                                </span>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 my-3 text-center">
                                <div class="text-[11px] text-slate-400">Kode Aktivasi Booth</div>
                                <div class="text-2xl font-mono font-extrabold tracking-widest text-teal-700">
                                    {{ dev.activation_code }}
                                </div>
                            </div>

                            <div class="text-[11px] text-slate-400 space-y-1">
                                <div>Heartbeat: {{ dev.last_heartbeat_at ? new Date(dev.last_heartbeat_at).toLocaleTimeString('id-ID') : 'Belum ada sinyal' }}</div>
                                <div>IP Address: {{ dev.ip_address || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: FRAME TEMPLATES -->
                <div v-if="activeTab === 'frames'" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-extrabold text-base text-slate-900">Desain Frame & Slot Foto</h3>
                            <p class="text-xs text-slate-500">Template transparan yang dipilih pengunjung saat berfoto di booth</p>
                        </div>

                        <Link
                            :href="route('organizer.events.studio', event.id)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold rounded-xl shadow-sm transition"
                        >
                            <LayoutGrid class="w-3.5 h-3.5" />
                            <span>Buka Visual Frame Studio (Drag & Drop)</span>
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            v-for="tmpl in event.frame_templates"
                            :key="tmpl.id"
                            class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs"
                        >
                            <div class="aspect-[3/4] rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center mb-4 overflow-hidden relative p-3">
                                <img
                                    v-if="tmpl.overlay_image_path"
                                    :src="tmpl.overlay_image_path.startsWith('/') ? tmpl.overlay_image_path : '/' + tmpl.overlay_image_path"
                                    class="max-h-full max-w-full object-contain"
                                />
                                <div v-else class="text-center">
                                    <div class="font-bold text-teal-700 text-sm">{{ tmpl.slots?.length }} Slot Foto</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ tmpl.orientation }} &bull; {{ tmpl.canvas_width }}x{{ tmpl.canvas_height }}px</div>
                                </div>
                            </div>

                            <div class="font-bold text-sm text-slate-900">{{ tmpl.name }}</div>
                            <div class="text-xs text-slate-500 mt-1">Status: {{ tmpl.is_active ? 'Aktif di Booth' : 'Nonaktif' }}</div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: DATA PENGUNJUNG -->
                <div v-if="activeTab === 'visitors'" class="space-y-6">
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-extrabold text-sm text-slate-900">Aktivitas Pengunjung</h3>
                                <p class="text-xs text-slate-500">Daftar pengunjung yang terdaftar dan sesi yang digunakan</p>
                            </div>
                            <span class="text-xs font-bold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">
                                {{ event.visitors?.length || 0 }} Pengunjung
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 border-b border-slate-100">
                                    <tr>
                                        <th class="p-3.5">Nama Pengunjung</th>
                                        <th class="p-3.5">WhatsApp</th>
                                        <th class="p-3.5">Sesi Digunakan</th>
                                        <th class="p-3.5">Waktu Daftar</th>
                                        <th class="p-3.5 text-right">Status Galeri</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="vis in event.visitors" :key="vis.id">
                                        <td class="p-3.5 font-bold text-slate-900">{{ vis.name }}</td>
                                        <td class="p-3.5 font-mono text-slate-600">{{ vis.phone_number }}</td>
                                        <td class="p-3.5 font-semibold">{{ vis.sessions_used }} Sesi</td>
                                        <td class="p-3.5 text-slate-400">{{ formatDate(vis.created_at) }}</td>
                                        <td class="p-3.5 text-right">
                                            <span class="text-teal-700 font-bold text-xs bg-teal-50 px-2 py-0.5 rounded-full border border-teal-200">
                                                Terkirim via WA
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!event.visitors?.length">
                                        <td colspan="5" class="p-8 text-center text-slate-400">
                                            Belum ada pengunjung yang mendaftar di event ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: SETTINGS FORM -->
                <div v-if="activeTab === 'settings'" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 max-w-3xl">
                    <form @submit.prevent="submitSettings" class="space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Maks. Sesi per Pengunjung</label>
                                <input v-model.number="settingsForm.max_sessions_per_visitor" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500 font-bold" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Countdown Kamera (Detik)</label>
                                <input v-model.number="settingsForm.countdown_seconds" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500 font-bold" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Maks. Retake per Foto</label>
                                <input v-model.number="settingsForm.max_retakes" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500 font-bold" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Masa Berlaku Link (Hari)</label>
                                <input v-model.number="settingsForm.token_expiry_days" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500 font-bold" />
                            </div>
                        </div>

                        <div class="space-y-2 pt-3 border-t border-slate-100">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="settingsForm.enable_whatsapp_delivery" type="checkbox" class="rounded text-teal-600 focus:ring-teal-500" />
                                <span>Kirim link galeri otomatis ke WhatsApp</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="settingsForm.is_unlimited_session" type="checkbox" class="rounded text-teal-600 focus:ring-teal-500" />
                                <span>Mode Unlimited Sesi (Bebas Foto Tanpa Batas Kuota)</span>
                            </label>
                        </div>

                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="settingsForm.processing"
                                class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-xs shadow-md shadow-teal-600/20 transition"
                            >
                                {{ settingsForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Footer Branding -->
                <div class="text-center text-xs text-slate-400 py-4 font-mono">
                    Luma Photobooth • by YOTA INOVASI NUSANTARA
                </div>

            </div>
        </div>

        <!-- Lightbox Modal in Show Event -->
        <div
            v-if="isLightboxOpen && availableSessionsWithPhotos()[activeSessionIndex]"
            class="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
            @click.self="closeLightbox"
        >
            <button
                @click="closeLightbox"
                class="absolute top-4 right-4 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white transition z-50"
            >
                <X class="w-5 h-5" />
            </button>

            <button
                v-if="activeSessionIndex > 0"
                @click="prevPhoto"
                class="absolute left-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition z-50"
            >
                <ChevronLeft class="w-6 h-6" />
            </button>

            <button
                v-if="activeSessionIndex < availableSessionsWithPhotos().length - 1"
                @click="nextPhoto"
                class="absolute right-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition z-50"
            >
                <ChevronRight class="w-6 h-6" />
            </button>

            <div class="relative w-full max-w-4xl max-h-[92vh] bg-white rounded-3xl overflow-hidden shadow-2xl flex flex-col md:flex-row">
                <div
                    class="flex-1 bg-slate-900 flex items-center justify-center p-4 min-h-[350px] md:min-h-[500px]"
                    :style="{
                        backgroundImage: `radial-gradient(#334155 0.75px, transparent 0.75px), radial-gradient(#334155 0.75px, #0F172A 0.75px)`,
                        backgroundSize: '16px 16px',
                        backgroundPosition: '0 0, 8px 8px',
                    }"
                >
                    <img
                        :src="route('organizer.photos.image', availableSessionsWithPhotos()[activeSessionIndex].photo.id)"
                        class="max-h-[80vh] w-auto max-w-full object-contain rounded-lg shadow-2xl"
                    />
                </div>

                <div class="w-full md:w-80 bg-white p-6 flex flex-col justify-between border-t md:border-t-0 md:border-l border-slate-200 text-xs">
                    <div class="space-y-4">
                        <div>
                            <span class="text-[10px] font-bold tracking-wider uppercase text-teal-700 bg-teal-50 px-2 py-0.5 rounded-full border border-teal-200">
                                Sesi #{{ availableSessionsWithPhotos()[activeSessionIndex].session_number }}
                            </span>
                            <h3 class="text-lg font-black text-slate-900 mt-1">
                                {{ availableSessionsWithPhotos()[activeSessionIndex].visitor?.name || 'Pengunjung Anonim' }}
                            </h3>
                            <div class="text-[11px] text-slate-400 font-mono">
                                {{ formatDate(availableSessionsWithPhotos()[activeSessionIndex].created_at) }}
                            </div>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <div class="flex justify-between">
                                <span class="text-slate-400">WhatsApp:</span>
                                <span class="font-mono font-bold text-slate-800">
                                    {{ availableSessionsWithPhotos()[activeSessionIndex].visitor?.phone_number || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Ukuran:</span>
                                <span class="font-mono text-slate-800">
                                    {{ formatBytes(availableSessionsWithPhotos()[activeSessionIndex].photo.file_size_bytes) }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="availableSessionsWithPhotos()[activeSessionIndex].photo.accessTokens?.[0]?.token"
                            class="p-3 rounded-2xl bg-teal-50 border border-teal-200 space-y-1.5"
                        >
                            <span class="text-[11px] font-bold text-teal-900 flex items-center gap-1">
                                <Sparkles class="w-3.5 h-3.5 text-teal-600" />
                                <span>Galeri Privat Pengunjung</span>
                            </span>
                            <div class="flex items-center gap-1.5 pt-1">
                                <button
                                    type="button"
                                    @click="copyPrivateLink(availableSessionsWithPhotos()[activeSessionIndex].photo.accessTokens[0].token)"
                                    class="flex-1 py-1 px-2 rounded-lg bg-white border border-teal-300 text-teal-800 text-[11px] font-bold flex items-center justify-center gap-1"
                                >
                                    <Check v-if="copiedTokenId === availableSessionsWithPhotos()[activeSessionIndex].photo.accessTokens[0].token" class="w-3 h-3 text-teal-600" />
                                    <Copy v-else class="w-3 h-3" />
                                    <span>{{ copiedTokenId === availableSessionsWithPhotos()[activeSessionIndex].photo.accessTokens[0].token ? 'Tersalin' : 'Salin Link' }}</span>
                                </button>
                                <a
                                    :href="`/p/${availableSessionsWithPhotos()[activeSessionIndex].photo.accessTokens[0].token}`"
                                    target="_blank"
                                    class="p-1 rounded-lg bg-white border border-teal-300 text-teal-700"
                                >
                                    <ExternalLink class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 space-y-2">
                        <a
                            :href="route('organizer.photos.download', availableSessionsWithPhotos()[activeSessionIndex].photo.id)"
                            class="w-full py-2.5 px-4 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-xs shadow-md shadow-teal-600/20 transition flex items-center justify-center gap-2"
                        >
                            <Download class="w-4 h-4" />
                            <span>Download Foto Asli</span>
                        </a>

                        <button
                            type="button"
                            @click="deletePhoto(availableSessionsWithPhotos()[activeSessionIndex].photo.id)"
                            class="w-full py-1.5 text-rose-600 hover:bg-rose-50 rounded-xl font-bold transition flex items-center justify-center gap-1"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Hapus Foto</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

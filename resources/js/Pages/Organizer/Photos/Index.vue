<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Camera, Search, Filter, Calendar, Download, ExternalLink, Trash2, Eye,
    Check, Copy, ChevronLeft, ChevronRight, X, Sparkles, MessageSquare, Smartphone
} from 'lucide-vue-next';

const props = defineProps({
    photos: Object,
    eventsList: Array,
    filters: Object,
    totalPhotosCount: Number,
});

// Filters state
const selectedEvent = ref(props.filters.event_id || '');
const searchQuery = ref(props.filters.search || '');
const selectedDate = ref(props.filters.date || '');

let searchDebounceTimer = null;
function handleSearchInput() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 400);
}

function applyFilters() {
    router.get(
        route('organizer.photos.index'),
        {
            event_id: selectedEvent.value || undefined,
            search: searchQuery.value || undefined,
            date: selectedDate.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function resetFilters() {
    selectedEvent.value = '';
    searchQuery.value = '';
    selectedDate.value = '';
    applyFilters();
}

// Lightbox Modal state
const isModalOpen = ref(false);
const activePhotoIndex = ref(0);
const copiedTokenId = ref(null);

function openLightbox(index) {
    activePhotoIndex.value = index;
    isModalOpen.value = true;
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    isModalOpen.value = false;
    document.body.style.overflow = '';
}

function prevPhoto() {
    if (activePhotoIndex.value > 0) {
        activePhotoIndex.value--;
    }
}

function nextPhoto() {
    if (activePhotoIndex.value < props.photos.data.length - 1) {
        activePhotoIndex.value++;
    }
}

function copyPrivateLink(token) {
    if (!token) return;
    const url = `${window.location.origin}/p/${token}`;
    navigator.clipboard.writeText(url);
    copiedTokenId.value = token;
    setTimeout(() => { copiedTokenId.value = null; }, 2000);
}

function deletePhoto(photo) {
    if (confirm('Apakah Anda yakin ingin menghapus foto ini? File akan dihapus permanen.')) {
        router.delete(route('organizer.photos.destroy', photo.id), {
            onSuccess: () => {
                if (isModalOpen.value) {
                    closeLightbox();
                }
            },
        });
    }
}

// Keyboard shortcuts for lightbox
function handleKeyDown(e) {
    if (!isModalOpen.value) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') prevPhoto();
    if (e.key === 'ArrowRight') nextPhoto();
}

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeyDown);
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
    <Head title="Galeri Semua Foto - Luma Photobooth" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-extrabold text-slate-900 leading-tight">
                            Galeri Semua Foto
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-50 text-teal-700 border border-teal-200">
                            {{ totalPhotosCount }} Total Foto
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Lihat, pratinjau resolusi tinggi, dan unduh seluruh foto yang telah diambil dari semua event
                    </p>
                </div>

                <div v-if="selectedEvent" class="flex items-center gap-2">
                    <a
                        :href="route('organizer.events.photos.zip', selectedEvent)"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-extrabold rounded-xl shadow-md shadow-teal-600/20 transition hover:scale-102"
                        title="Unduh seluruh foto event ini dalam satu file .ZIP"
                    >
                        <Download class="w-4 h-4" />
                        <span>Download ZIP Event Ini</span>
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Filter Bar -->
                <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                        <!-- Search Box -->
                        <div class="sm:col-span-5 relative">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input
                                v-model="searchQuery"
                                @input="handleSearchInput"
                                type="text"
                                placeholder="Cari nama pengunjung atau no. WhatsApp..."
                                class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-slate-300 bg-white placeholder-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none font-medium"
                            />
                        </div>

                        <!-- Event Dropdown Filter -->
                        <div class="sm:col-span-4">
                            <select
                                v-model="selectedEvent"
                                @change="applyFilters"
                                class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none font-medium"
                            >
                                <option value="">Semua Event ({{ eventsList.length }})</option>
                                <option v-for="ev in eventsList" :key="ev.id" :value="ev.id">
                                    {{ ev.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Date Filter -->
                        <div class="sm:col-span-2">
                            <input
                                v-model="selectedDate"
                                @change="applyFilters"
                                type="date"
                                class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none font-medium"
                            />
                        </div>

                        <!-- Reset Filter Button -->
                        <div class="sm:col-span-1 flex items-center justify-end">
                            <button
                                v-if="selectedEvent || searchQuery || selectedDate"
                                @click="resetFilters"
                                type="button"
                                class="w-full py-2 px-2 text-xs rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold transition text-center"
                                title="Reset Filter"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Photos Grid -->
                <div v-if="photos.data && photos.data.length > 0" class="space-y-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <div
                            v-for="(photo, index) in photos.data"
                            :key="photo.id"
                            class="group relative rounded-2xl bg-white border border-slate-200 shadow-xs hover:shadow-xl hover:border-teal-400 transition-all duration-200 overflow-hidden flex flex-col"
                        >
                            <!-- Thumbnail with Checkerboard background -->
                            <div
                                @click="openLightbox(index)"
                                class="relative aspect-[3/4] bg-slate-100 cursor-pointer overflow-hidden flex items-center justify-center"
                                :style="{
                                    backgroundImage: `radial-gradient(#CBD5E1 0.75px, transparent 0.75px), radial-gradient(#CBD5E1 0.75px, #F8FAFC 0.75px)`,
                                    backgroundSize: '12px 12px',
                                    backgroundPosition: '0 0, 6px 6px',
                                }"
                            >
                                <img
                                    :src="route('organizer.photos.image', photo.id)"
                                    :alt="photo.session?.visitor?.name || 'Photo'"
                                    loading="lazy"
                                    class="w-full h-full object-contain p-1.5 transition-transform duration-300 group-hover:scale-103"
                                />

                                <!-- Hover Quick View Overlay -->
                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                    <span class="p-2 rounded-xl bg-white/90 text-slate-800 shadow-md">
                                        <Eye class="w-4 h-4 text-teal-600" />
                                    </span>
                                </div>

                                <!-- Session Badge -->
                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-mono font-bold shadow-xs">
                                    #{{ photo.session?.session_number || photo.id }}
                                </span>
                            </div>

                            <!-- Photo Card Footer -->
                            <div class="p-3 flex-1 flex flex-col justify-between border-t border-slate-100 text-xs">
                                <div>
                                    <div class="font-extrabold text-slate-900 truncate" :title="photo.session?.visitor?.name">
                                        {{ photo.session?.visitor?.name || 'Pengunjung Anonim' }}
                                    </div>
                                    <div class="text-[11px] text-teal-700 font-semibold truncate mt-0.5">
                                        {{ photo.session?.event?.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        {{ formatDate(photo.created_at) }}
                                    </div>
                                </div>

                                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        {{ formatBytes(photo.file_size_bytes) }}
                                    </span>

                                    <div class="flex items-center gap-1">
                                        <a
                                            :href="route('organizer.photos.download', photo.id)"
                                            class="p-1 rounded-lg text-slate-400 hover:text-teal-600 hover:bg-teal-50 transition"
                                            title="Download Foto Asli"
                                        >
                                            <Download class="w-3.5 h-3.5" />
                                        </a>

                                        <button
                                            v-if="photo.accessTokens?.[0]?.token"
                                            @click="copyPrivateLink(photo.accessTokens[0].token)"
                                            type="button"
                                            class="p-1 rounded-lg text-slate-400 hover:text-teal-600 hover:bg-teal-50 transition"
                                            title="Salin Link Galeri Pengunjung"
                                        >
                                            <Check v-if="copiedTokenId === photo.accessTokens[0].token" class="w-3.5 h-3.5 text-teal-600" />
                                            <Copy v-else class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Links -->
                    <div v-if="photos.links && photos.links.length > 3" class="flex items-center justify-center gap-1 py-4">
                        <Link
                            v-for="(link, i) in photos.links"
                            :key="i"
                            :href="link.url || '#'"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-bold transition',
                                link.active
                                    ? 'bg-teal-600 text-white shadow-xs'
                                    : link.url
                                        ? 'bg-white text-slate-600 hover:bg-teal-50 hover:text-teal-700 border border-slate-200'
                                        : 'text-slate-300 pointer-events-none'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="p-12 rounded-3xl bg-white border border-slate-200 shadow-sm text-center max-w-lg mx-auto">
                    <div class="w-16 h-16 rounded-3xl bg-teal-50 text-teal-600 flex items-center justify-center mx-auto mb-4 border border-teal-100">
                        <Camera class="w-8 h-8" />
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900 mb-1">
                        Belum Ada Foto Ditemukan
                    </h3>
                    <p class="text-xs text-slate-500 leading-relaxed mb-6">
                        {{ selectedEvent || searchQuery || selectedDate ? 'Tidak ada foto yang cocok dengan filter pencarian Anda. Coba ubah atau reset filter.' : 'Belum ada sesi foto yang selesai di layar kiosk booth. Mulai foto pertama dari layar booth!' }}
                    </p>
                    <div class="flex items-center justify-center gap-3">
                        <button
                            v-if="selectedEvent || searchQuery || selectedDate"
                            @click="resetFilters"
                            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
                        >
                            Reset Filter
                        </button>
                        <a
                            href="/booth/festival-kreativitas-2026"
                            target="_blank"
                            class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5"
                        >
                            <span>Buka Layar Booth Demo</span>
                            <ExternalLink class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>

                <!-- Footer Branding -->
                <div class="text-center text-xs text-slate-400 py-4 font-mono">
                    Luma Photobooth • by YOTA INOVASI NUSANTARA
                </div>

            </div>
        </div>

        <!-- Lightbox Fullscreen Preview Modal -->
        <div
            v-if="isModalOpen && photos.data[activePhotoIndex]"
            class="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
            @click.self="closeLightbox"
        >
            <!-- Close Button -->
            <button
                @click="closeLightbox"
                class="absolute top-4 right-4 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white transition z-50"
                title="Tutup (Esc)"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Navigation Buttons -->
            <button
                v-if="activePhotoIndex > 0"
                @click="prevPhoto"
                class="absolute left-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition z-50 shadow-lg"
                title="Foto Sebelumnya (Panah Kiri)"
            >
                <ChevronLeft class="w-6 h-6" />
            </button>

            <button
                v-if="activePhotoIndex < photos.data.length - 1"
                @click="nextPhoto"
                class="absolute right-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition z-50 shadow-lg"
                title="Foto Berikutnya (Panah Kanan)"
            >
                <ChevronRight class="w-6 h-6" />
            </button>

            <!-- Modal Content Card -->
            <div class="relative w-full max-w-5xl max-h-[92vh] bg-white rounded-3xl overflow-hidden shadow-2xl flex flex-col md:flex-row">
                
                <!-- Left: Big Image View -->
                <div
                    class="flex-1 bg-slate-900 flex items-center justify-center p-4 overflow-hidden relative min-h-[350px] md:min-h-[550px]"
                    :style="{
                        backgroundImage: `radial-gradient(#334155 0.75px, transparent 0.75px), radial-gradient(#334155 0.75px, #0F172A 0.75px)`,
                        backgroundSize: '16px 16px',
                        backgroundPosition: '0 0, 8px 8px',
                    }"
                >
                    <img
                        :src="route('organizer.photos.image', photos.data[activePhotoIndex].id)"
                        :alt="photos.data[activePhotoIndex].session?.visitor?.name"
                        class="max-h-[82vh] w-auto max-w-full object-contain rounded-lg shadow-2xl transition-all"
                    />
                </div>

                <!-- Right: Details Sidebar -->
                <div class="w-full md:w-80 bg-white p-6 flex flex-col justify-between border-t md:border-t-0 md:border-l border-slate-200">
                    <div class="space-y-4">
                        <div>
                            <span class="text-[10px] font-bold tracking-wider uppercase text-teal-700 bg-teal-50 px-2 py-0.5 rounded-full border border-teal-200">
                                Sesi Foto #{{ photos.data[activePhotoIndex].session?.session_number || photos.data[activePhotoIndex].id }}
                            </span>
                            <h3 class="text-lg font-black text-slate-900 mt-1.5">
                                {{ photos.data[activePhotoIndex].session?.visitor?.name || 'Pengunjung Anonim' }}
                            </h3>
                            <div class="text-xs text-slate-500 font-medium">
                                {{ photos.data[activePhotoIndex].session?.event?.name }}
                            </div>
                        </div>

                        <div class="space-y-2.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">WhatsApp:</span>
                                <span class="font-mono font-bold text-slate-800">
                                    {{ photos.data[activePhotoIndex].session?.visitor?.phone_number || '-' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Template Frame:</span>
                                <span class="font-semibold text-slate-800">
                                    {{ photos.data[activePhotoIndex].session?.frameTemplate?.name || 'Standard' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Perangkat Booth:</span>
                                <span class="font-semibold text-slate-800">
                                    {{ photos.data[activePhotoIndex].session?.device?.device_name || 'Station #01' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Waktu Foto:</span>
                                <span class="font-mono text-slate-800">
                                    {{ formatDate(photos.data[activePhotoIndex].created_at) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Ukuran File:</span>
                                <span class="font-mono text-slate-800">
                                    {{ formatBytes(photos.data[activePhotoIndex].file_size_bytes) }}
                                </span>
                            </div>
                        </div>

                        <!-- Private Gallery Link -->
                        <div
                            v-if="photos.data[activePhotoIndex].accessTokens?.[0]?.token"
                            class="p-3 rounded-2xl bg-teal-50/70 border border-teal-200 space-y-1.5"
                        >
                            <span class="text-[11px] font-bold text-teal-900 flex items-center gap-1">
                                <Sparkles class="w-3.5 h-3.5 text-teal-600" />
                                <span>Galeri Privat Pengunjung</span>
                            </span>
                            <div class="flex items-center gap-1.5 pt-1">
                                <button
                                    type="button"
                                    @click="copyPrivateLink(photos.data[activePhotoIndex].accessTokens[0].token)"
                                    class="flex-1 py-1 px-2 rounded-lg bg-white border border-teal-300 text-teal-800 hover:bg-teal-100 text-[11px] font-bold transition flex items-center justify-center gap-1"
                                >
                                    <Check v-if="copiedTokenId === photos.data[activePhotoIndex].accessTokens[0].token" class="w-3 h-3 text-teal-600" />
                                    <Copy v-else class="w-3 h-3" />
                                    <span>{{ copiedTokenId === photos.data[activePhotoIndex].accessTokens[0].token ? 'Tersalin!' : 'Salin Link WA' }}</span>
                                </button>
                                <a
                                    :href="`/p/${photos.data[activePhotoIndex].accessTokens[0].token}`"
                                    target="_blank"
                                    class="p-1 rounded-lg bg-white border border-teal-300 text-teal-700 hover:bg-teal-100 transition"
                                    title="Buka Halaman Galeri Pengunjung"
                                >
                                    <ExternalLink class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-4 border-t border-slate-100 space-y-2">
                        <a
                            :href="route('organizer.photos.download', photos.data[activePhotoIndex].id)"
                            class="w-full py-2.5 px-4 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-xs shadow-md shadow-teal-600/20 transition flex items-center justify-center gap-2"
                        >
                            <Download class="w-4 h-4" />
                            <span>Download Foto Asli</span>
                        </a>

                        <button
                            type="button"
                            @click="deletePhoto(photos.data[activePhotoIndex])"
                            class="w-full py-2 px-4 rounded-xl text-rose-600 hover:bg-rose-50 font-bold text-xs transition flex items-center justify-center gap-1.5"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Hapus Foto Ini</span>
                        </button>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>


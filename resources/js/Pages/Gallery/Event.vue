<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import {
    Camera, Image as ImageIcon, Download, Share2, Search, Filter,
    Check, Copy, X, ChevronLeft, ChevronRight, Eye, Calendar, MapPin,
    Sparkles, ArrowDownToLine, Layers, QrCode, ExternalLink
} from 'lucide-vue-next';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({
            booth_count: 0,
            raw_count: 0,
            total_count: 0,
            visitors_count: 0,
        }),
    },
    items: {
        type: Array,
        default: () => [],
    },
    downloadZipUrls: {
        type: Object,
        default: () => ({}),
    },
});

// Filter & Search state
const activeTab = ref('all'); // 'all', 'booth', 'raw'
const searchQuery = ref('');

const filteredItems = computed(() => {
    let result = props.items;

    // Filter by type
    if (activeTab.value === 'booth') {
        result = result.filter(item => item.type === 'booth');
    } else if (activeTab.value === 'raw') {
        result = result.filter(item => item.type === 'raw');
    }

    // Filter by search
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        result = result.filter(item => {
            const visitorMatch = (item.visitor_name || '').toLowerCase().includes(query);
            const sessionMatch = `session #${item.session_number}`.toLowerCase().includes(query) ||
                `#${item.session_number}`.includes(query);
            return visitorMatch || sessionMatch;
        });
    }

    return result;
});

// Lightbox state
const isLightboxOpen = ref(false);
const activeIndex = ref(0);

function openLightbox(index) {
    activeIndex.value = index;
    isLightboxOpen.value = true;
    window.addEventListener('keydown', handleKeydown);
}

function closeLightbox() {
    isLightboxOpen.value = false;
    window.removeEventListener('keydown', handleKeydown);
}

function nextPhoto() {
    if (filteredItems.value.length === 0) return;
    activeIndex.value = (activeIndex.value + 1) % filteredItems.value.length;
}

function prevPhoto() {
    if (filteredItems.value.length === 0) return;
    activeIndex.value = (activeIndex.value - 1 + filteredItems.value.length) % filteredItems.value.length;
}

function handleKeydown(e) {
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowRight') nextPhoto();
    if (e.key === 'ArrowLeft') prevPhoto();
}

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

// Share Modal State
const isShareModalOpen = ref(false);
const qrCodeDataUrl = ref('');
const isCopied = ref(false);

async function openShareModal() {
    isShareModalOpen.value = true;
    try {
        qrCodeDataUrl.value = await QRCode.toDataURL(props.event.share_url, {
            width: 320,
            margin: 2,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch (e) {
        console.error('Failed to generate QR code:', e);
    }
}

async function copyShareUrl() {
    try {
        await navigator.clipboard.writeText(props.event.share_url);
        isCopied.value = true;
        setTimeout(() => { isCopied.value = false; }, 2000);
    } catch (e) {
        // Fallback
    }
}

const whatsappShareUrl = computed(() => {
    const text = `Halo! Yuk lihat dan download seluruh foto seru di *${props.event.name}* (Foto Booth & Foto Mentah): ${props.event.share_url}`;
    return `https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`;
});

// Download ZIP Dropdown
const isZipMenuOpen = ref(false);

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
</script>

<template>
    <Head :title="`Galeri Foto - ${event.name}`" />

    <div class="min-h-screen bg-slate-50 text-slate-900 font-sans selection:bg-teal-500 selection:text-white flex flex-col justify-between">
        <!-- Top App Bar -->
        <header class="border-b border-slate-200/80 bg-white/95 backdrop-blur-md sticky top-0 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <img src="/logo.png" alt="Luma Photobooth" class="h-8 sm:h-9 w-auto object-contain" />
                    <div class="border-l border-slate-200 pl-3">
                        <div class="text-[10px] sm:text-[11px] text-teal-600 font-extrabold tracking-wider uppercase">
                            Galeri Bersama Event
                        </div>
                        <h1 class="text-xs sm:text-sm font-extrabold text-slate-900 truncate max-w-[180px] sm:max-w-md">
                            {{ event.name }}
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Share Button -->
                    <button
                        @click="openShareModal"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-2 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 text-xs font-bold transition shadow-2xs hover:border-teal-300"
                    >
                        <Share2 class="w-3.5 h-3.5 text-teal-600" />
                        <span class="hidden sm:inline">Bagikan Galeri</span>
                        <span class="sm:hidden">Share</span>
                    </button>

                    <!-- Download ZIP Dropdown -->
                    <div class="relative">
                        <button
                            @click="isZipMenuOpen = !isZipMenuOpen"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-extrabold transition shadow-md shadow-teal-600/20"
                        >
                            <Download class="w-3.5 h-3.5" />
                            <span>Download ZIP</span>
                        </button>

                        <div
                            v-if="isZipMenuOpen"
                            @click="isZipMenuOpen = false"
                            class="fixed inset-0 z-20"
                        ></div>

                        <div
                            v-if="isZipMenuOpen"
                            class="absolute right-0 mt-2 w-64 rounded-2xl bg-white border border-slate-200 shadow-xl z-30 p-2 space-y-1 text-left"
                        >
                            <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                Pilihan Download Arsip
                            </div>

                            <a
                                :href="downloadZipUrls.all"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-teal-50 text-slate-700 hover:text-teal-900 text-xs font-bold transition"
                            >
                                <ArrowDownToLine class="w-4 h-4 text-teal-600 shrink-0" />
                                <div>
                                    <div>Semua Foto (.ZIP)</div>
                                    <div class="text-[10px] text-slate-400 font-normal">Foto Booth + Foto Mentah</div>
                                </div>
                            </a>

                            <a
                                :href="downloadZipUrls.booth"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-teal-50 text-slate-700 hover:text-teal-900 text-xs font-bold transition"
                            >
                                <Camera class="w-4 h-4 text-teal-600 shrink-0" />
                                <div>
                                    <div>Hanya Foto Booth (.ZIP)</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ stats.booth_count }} foto berbingkai</div>
                                </div>
                            </a>

                            <a
                                :href="downloadZipUrls.raw"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-amber-50 text-slate-700 hover:text-amber-900 text-xs font-bold transition"
                            >
                                <Layers class="w-4 h-4 text-amber-600 shrink-0" />
                                <div>
                                    <div>Hanya Foto Mentah (.ZIP)</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ stats.raw_count }} pose jepretan kamera</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Gallery Container -->
        <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6 space-y-6">

            <!-- Hero Event Banner -->
            <div class="rounded-3xl bg-gradient-to-br from-teal-800 via-teal-700 to-teal-900 text-white p-6 sm:p-8 shadow-xl relative overflow-hidden">
                <!-- Decorative Circles -->
                <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>
                <div class="absolute right-1/4 -bottom-12 w-48 h-48 rounded-full bg-teal-400/10 blur-xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-xs text-teal-200 text-xs font-bold border border-white/15">
                            <Sparkles class="w-3.5 h-3.5 text-teal-300" />
                            <span>Koleksi Foto & Jepretan Mentah Lengkap</span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                            {{ event.name }}
                        </h2>
                        <p v-if="event.description" class="text-xs sm:text-sm text-teal-100/90 line-clamp-2">
                            {{ event.description }}
                        </p>

                        <div class="flex flex-wrap items-center gap-4 pt-1 text-xs text-teal-200 font-medium">
                            <span v-if="event.start_date" class="flex items-center gap-1.5">
                                <Calendar class="w-3.5 h-3.5 text-teal-300" />
                                <span>{{ event.start_date }}</span>
                            </span>
                            <span v-if="event.location" class="flex items-center gap-1.5">
                                <MapPin class="w-3.5 h-3.5 text-teal-300" />
                                <span>{{ event.location }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Statistics Pills -->
                    <div class="grid grid-cols-3 gap-3 bg-white/10 backdrop-blur-md p-3.5 rounded-2xl border border-white/15 text-center shrink-0">
                        <div class="px-2">
                            <div class="text-lg sm:text-2xl font-black">{{ stats.total_count }}</div>
                            <div class="text-[10px] sm:text-xs text-teal-200 uppercase tracking-wider font-bold">Semua Foto</div>
                        </div>
                        <div class="px-2 border-x border-white/15">
                            <div class="text-lg sm:text-2xl font-black text-amber-300">{{ stats.booth_count }}</div>
                            <div class="text-[10px] sm:text-xs text-teal-200 uppercase tracking-wider font-bold">Foto Booth</div>
                        </div>
                        <div class="px-2">
                            <div class="text-lg sm:text-2xl font-black text-teal-200">{{ stats.raw_count }}</div>
                            <div class="text-[10px] sm:text-xs text-teal-200 uppercase tracking-wider font-bold">Foto Mentah</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-3xl bg-white border border-slate-200 shadow-sm">
                <!-- Type Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                    <button
                        @click="activeTab = 'all'"
                        type="button"
                        :class="[
                            'px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap flex items-center gap-1.5',
                            activeTab === 'all'
                                ? 'bg-teal-600 text-white shadow-md shadow-teal-600/20'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        <span>Semua Foto</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'all' ? 'bg-teal-700 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ stats.total_count }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'booth'"
                        type="button"
                        :class="[
                            'px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap flex items-center gap-1.5',
                            activeTab === 'booth'
                                ? 'bg-teal-600 text-white shadow-md shadow-teal-600/20'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        <Camera class="w-3.5 h-3.5" />
                        <span>Foto Booth (Frame)</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'booth' ? 'bg-teal-700 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ stats.booth_count }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'raw'"
                        type="button"
                        :class="[
                            'px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap flex items-center gap-1.5',
                            activeTab === 'raw'
                                ? 'bg-teal-600 text-white shadow-md shadow-teal-600/20'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        <Layers class="w-3.5 h-3.5" />
                        <span>Foto Mentah (Raw)</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'raw' ? 'bg-teal-700 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ stats.raw_count }}
                        </span>
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative w-full sm:w-72">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama atau session #..."
                        class="w-full pl-10 pr-8 py-2 text-xs rounded-xl border border-slate-300 bg-white placeholder-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none font-medium"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- Photo Grid -->
            <div v-if="filteredItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                <div
                    v-for="(item, idx) in filteredItems"
                    :key="`${item.type}-${item.id}`"
                    class="group relative rounded-2xl bg-white border border-slate-200 shadow-xs hover:shadow-xl hover:border-teal-400 transition-all duration-200 overflow-hidden flex flex-col"
                >
                    <!-- Thumbnail Box -->
                    <div
                        @click="openLightbox(idx)"
                        class="relative aspect-[3/4] bg-slate-100 cursor-pointer overflow-hidden flex items-center justify-center"
                        :style="{
                            backgroundImage: `radial-gradient(#CBD5E1 0.75px, transparent 0.75px), radial-gradient(#CBD5E1 0.75px, #F8FAFC 0.75px)`,
                            backgroundSize: '12px 12px',
                            backgroundPosition: '0 0, 6px 6px',
                        }"
                    >
                        <img
                            :src="item.imageUrl"
                            :alt="item.visitor_name"
                            loading="lazy"
                            class="w-full h-full p-1.5 transition-transform duration-300 group-hover:scale-103"
                            :class="item.type === 'booth' ? 'object-contain' : 'object-cover rounded-xl'"
                        />

                        <!-- Overlay on Hover -->
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <span class="p-2 rounded-xl bg-white/90 text-slate-800 shadow-md">
                                <Eye class="w-4 h-4 text-teal-600" />
                            </span>
                        </div>

                        <!-- Top Badges -->
                        <div class="absolute top-2 left-2 flex flex-col gap-1 items-start z-10">
                            <!-- Type Badge -->
                            <span
                                v-if="item.type === 'booth'"
                                class="px-2 py-0.5 rounded-md bg-teal-600/90 text-white text-[9px] font-extrabold uppercase tracking-wider shadow-xs backdrop-blur-xs flex items-center gap-1"
                            >
                                <Camera class="w-2.5 h-2.5" />
                                <span>Booth</span>
                            </span>
                            <span
                                v-else
                                class="px-2 py-0.5 rounded-md bg-amber-500/90 text-white text-[9px] font-extrabold uppercase tracking-wider shadow-xs backdrop-blur-xs flex items-center gap-1"
                            >
                                <Layers class="w-2.5 h-2.5" />
                                <span>Mentah #{{ item.slot_number }}</span>
                            </span>
                        </div>

                        <!-- Session Number -->
                        <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-slate-900/70 text-white text-[9px] font-mono font-bold shadow-xs backdrop-blur-xs">
                            #{{ item.session_number }}
                        </span>
                    </div>

                    <!-- Footer Info -->
                    <div class="p-3 flex-1 flex flex-col justify-between border-t border-slate-100 text-xs bg-white">
                        <div>
                            <div class="font-extrabold text-slate-900 truncate" :title="item.visitor_name">
                                {{ item.visitor_name }}
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                {{ item.created_at }}
                            </div>
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span v-if="item.type === 'booth' && item.file_size_bytes" class="text-[10px] text-slate-400 font-mono">
                                {{ formatBytes(item.file_size_bytes) }}
                            </span>
                            <span v-else class="text-[10px] text-amber-600 font-bold uppercase tracking-wider">
                                Pose {{ item.slot_number }}
                            </span>

                            <a
                                :href="item.downloadUrl"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 text-[11px] font-bold transition"
                                title="Download Foto Asli"
                            >
                                <Download class="w-3 h-3" />
                                <span>Simpan</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="p-12 rounded-3xl bg-white border border-slate-200 shadow-sm text-center max-w-md mx-auto">
                <div class="w-16 h-16 rounded-3xl bg-teal-50 text-teal-600 flex items-center justify-center mx-auto mb-4 border border-teal-100">
                    <Camera class="w-8 h-8" />
                </div>
                <h4 class="font-extrabold text-sm text-slate-900 mb-1">Tidak Ada Foto yang Ditemukan</h4>
                <p class="text-xs text-slate-500 mb-4">
                    {{ searchQuery ? 'Tidak ada hasil foto yang cocok dengan pencarian Anda.' : 'Belum ada foto yang diambil pada event ini.' }}
                </p>
                <button
                    v-if="searchQuery"
                    @click="searchQuery = ''"
                    type="button"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition"
                >
                    Reset Pencarian
                </button>
            </div>
        </main>

        <!-- Lightbox Modal -->
        <div
            v-if="isLightboxOpen && filteredItems[activeIndex]"
            class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex flex-col justify-between p-4 select-none"
            @click.self="closeLightbox"
        >
            <!-- Lightbox Header -->
            <div class="flex items-center justify-between text-white max-w-5xl mx-auto w-full pt-2">
                <div class="flex items-center gap-2">
                    <span
                        v-if="filteredItems[activeIndex].type === 'booth'"
                        class="px-2.5 py-1 rounded-lg bg-teal-600 text-white text-xs font-bold"
                    >
                        Foto Booth Berbingkai
                    </span>
                    <span
                        v-else
                        class="px-2.5 py-1 rounded-lg bg-amber-500 text-white text-xs font-bold"
                    >
                        Foto Mentah • Pose #{{ filteredItems[activeIndex].slot_number }}
                    </span>
                    <span class="text-xs text-slate-300">
                        Pengunjung: <strong>{{ filteredItems[activeIndex].visitor_name }}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        :href="filteredItems[activeIndex].downloadUrl"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold transition shadow-sm"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>Download HD</span>
                    </a>
                    <button
                        @click="closeLightbox"
                        type="button"
                        class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Lightbox Content & Navigation -->
            <div class="flex-1 flex items-center justify-between max-w-6xl mx-auto w-full my-4 relative">
                <!-- Prev Button -->
                <button
                    @click="prevPhoto"
                    type="button"
                    class="p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition backdrop-blur-xs"
                    title="Foto Sebelumnya (Panah Kiri)"
                >
                    <ChevronLeft class="w-6 h-6" />
                </button>

                <!-- Photo Display -->
                <div class="max-h-[75vh] max-w-4xl flex items-center justify-center p-2">
                    <img
                        :src="filteredItems[activeIndex].imageUrl"
                        :alt="filteredItems[activeIndex].visitor_name"
                        class="max-h-[72vh] max-w-full rounded-2xl shadow-2xl object-contain"
                    />
                </div>

                <!-- Next Button -->
                <button
                    @click="nextPhoto"
                    type="button"
                    class="p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition backdrop-blur-xs"
                    title="Foto Selanjutnya (Panah Kanan)"
                >
                    <ChevronRight class="w-6 h-6" />
                </button>
            </div>

            <!-- Lightbox Footer -->
            <div class="text-center text-xs text-slate-400 pb-2">
                Foto {{ activeIndex + 1 }} dari {{ filteredItems.length }} &bull; Diambil pada {{ filteredItems[activeIndex].created_at }}
            </div>
        </div>

        <!-- Share Modal -->
        <div
            v-if="isShareModalOpen"
            class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4"
            @click.self="isShareModalOpen = false"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-200 text-center space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <Share2 class="w-4 h-4 text-teal-600" />
                        <h3 class="font-extrabold text-base text-slate-900">Bagikan Galeri Event</h3>
                    </div>
                    <button
                        @click="isShareModalOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 text-lg font-bold"
                    >
                        &times;
                    </button>
                </div>

                <p class="text-xs text-slate-500">
                    Siapapun dengan link ini dapat melihat dan mengunduh seluruh foto booth & foto mentah dari event <strong>{{ event.name }}</strong>.
                </p>

                <!-- QR Code Box -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 inline-block mx-auto shadow-inner">
                    <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="QR Code Galeri Event" class="w-48 h-48 mx-auto rounded-lg" />
                    <div v-else class="w-48 h-48 flex items-center justify-center text-xs text-slate-400">Membuat QR Code...</div>
                    <div class="text-[11px] text-slate-500 font-bold mt-2">Scan untuk Buka di Smartphone</div>
                </div>

                <!-- Copy Link Input -->
                <div class="flex items-center gap-2">
                    <input
                        :value="event.share_url"
                        readonly
                        class="flex-1 py-2 px-3 text-xs rounded-xl border border-slate-300 bg-slate-50 font-mono text-slate-700 outline-none select-all"
                    />
                    <button
                        @click="copyShareUrl"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold transition flex items-center gap-1.5 shrink-0"
                    >
                        <Check v-if="isCopied" class="w-3.5 h-3.5" />
                        <Copy v-else class="w-3.5 h-3.5" />
                        <span>{{ isCopied ? 'Tersalin!' : 'Salin' }}</span>
                    </button>
                </div>

                <!-- WhatsApp Share Button -->
                <div>
                    <a
                        :href="whatsappShareUrl"
                        target="_blank"
                        rel="noopener"
                        class="w-full py-2.5 px-4 rounded-xl bg-[#25D366] hover:bg-[#20bd5a] text-white text-xs font-extrabold transition flex items-center justify-center gap-2 shadow-sm"
                    >
                        <span>Bagikan ke WhatsApp</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-8 border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-400">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <img src="/logo.png" alt="Luma" class="h-6 w-auto opacity-70" />
                    <span class="font-bold text-slate-600">Luma Photobooth</span>
                    <span>&bull; by YOTA INOVASI NUSANTARA</span>
                </div>

                <div class="text-[11px]">
                    Self-Service Photobooth SaaS &bull; All Rights Reserved
                </div>
            </div>
        </footer>
    </div>
</template>

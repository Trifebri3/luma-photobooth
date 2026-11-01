<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Camera, Smartphone, ArrowRight, ShieldCheck, Sparkles,
    Maximize2, Minimize2, CheckCircle2, AlertCircle, Delete, RefreshCw
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    lastPairedEvent: Object,
});

const activationCode = ref('');
const isLoading = ref(false);
const errorMessage = ref('');
const successInfo = ref(null);
const isFullscreen = ref(false);
const showTouchKeypad = ref(false);

const keypadChars = [
    '1', '2', '3', '4', '5', '6', '7', '8', '9', '0',
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
    'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
    'U', 'V', 'W', 'X', 'Y', 'Z'
];

function typeKey(char) {
    if (activationCode.value.length < 10) {
        activationCode.value += char;
        errorMessage.value = '';
    }
}

function backspaceKey() {
    activationCode.value = activationCode.value.slice(0, -1);
    errorMessage.value = '';
}

function clearKey() {
    activationCode.value = '';
    errorMessage.value = '';
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
            isFullscreen.value = true;
        }).catch(err => console.log(err));
    } else {
        document.exitFullscreen().then(() => {
            isFullscreen.value = false;
        }).catch(err => console.log(err));
    }
}

async function handlePairDevice() {
    const code = activationCode.value.trim().toUpperCase();
    if (!code) {
        errorMessage.value = 'Silakan masukkan kode aktivasi perangkat.';
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';
    successInfo.value = null;

    try {
        const res = await axios.post('/booth/pair', {
            activation_code: code,
        });

        if (res.data.success) {
            successInfo.value = res.data;
            setTimeout(() => {
                window.location.href = res.data.redirect_url;
            }, 800);
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Kode aktivasi tidak valid atau tidak ditemukan.';
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <Head title="Aktivasi Layar Kiosk Photobooth - Luma" />

    <div class="min-h-screen bg-slate-50 flex flex-col justify-between font-sans selection:bg-teal-500 selection:text-white relative overflow-hidden">
        
        <!-- Ambient Glow -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-teal-400/10 rounded-full blur-3xl"></div>
            <div class="absolute top-1/3 -right-40 w-96 h-96 bg-emerald-400/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-20 left-1/2 -translate-x-1/2 w-[600px] h-96 bg-teal-300/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Header -->
        <header class="relative z-10 border-b border-slate-200/80 backdrop-blur-md bg-white/90 px-6 py-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <Link :href="route('home')" class="flex items-center space-x-3">
                    <img src="/logo.png" alt="Luma Photobooth" class="h-9 w-auto object-contain" />
                    <div>
                        <div class="font-extrabold text-lg tracking-tight text-slate-900 leading-none">
                            Luma <span class="text-teal-600">Photobooth</span>
                        </div>
                        <div class="text-[9px] text-teal-700 font-bold uppercase tracking-wider mt-0.5">
                            by YOTA INOVASI NUSANTARA
                        </div>
                    </div>
                </Link>

                <div class="flex items-center gap-2">
                    <button
                        @click="showTouchKeypad = !showTouchKeypad"
                        type="button"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-2xs"
                    >
                        <span>{{ showTouchKeypad ? 'Sembunyikan Keyboard' : 'Keyboard Layar Sentuh' }}</span>
                    </button>

                    <button
                        @click="toggleFullscreen"
                        class="p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition shadow-2xs"
                        title="Mode Layar Penuh"
                    >
                        <Maximize2 v-if="!isFullscreen" class="w-4 h-4 text-slate-600" />
                        <Minimize2 v-else class="w-4 h-4 text-slate-600" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="relative z-10 flex-1 flex items-center justify-center p-4 sm:p-6 my-auto">
            <div class="w-full max-w-lg bg-white rounded-3xl border border-slate-200 shadow-2xl p-8 sm:p-10 text-center space-y-6">
                
                <!-- Icon & Titles -->
                <div>
                    <div class="w-16 h-16 mx-auto rounded-3xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-600 mb-4 shadow-sm">
                        <Smartphone class="w-8 h-8" />
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-50 border border-teal-200 text-teal-700 text-xs font-bold mb-2">
                        <Sparkles class="w-3.5 h-3.5 text-teal-600" />
                        <span>Kiosk Photobooth Activation</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Aktivasi Layar Booth
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-sm mx-auto leading-relaxed">
                        Masukkan kode aktivasi 6-karakter dari event Anda untuk menghubungkan stasiun booth ini secara otomatis.
                    </p>
                </div>

                <!-- Last Paired Event Banner (if available) -->
                <div
                    v-if="lastPairedEvent"
                    class="p-4 rounded-2xl bg-teal-50/70 border border-teal-200 text-left flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                >
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold text-teal-700 uppercase tracking-wider">
                            Terakhir Dihubungkan:
                        </div>
                        <div class="text-xs font-black text-slate-900 truncate">
                            {{ lastPairedEvent.name }}
                        </div>
                        <div v-if="lastPairedEvent.device_name" class="text-[11px] text-slate-500">
                            {{ lastPairedEvent.device_name }}
                        </div>
                    </div>

                    <a
                        :href="`/booth/${lastPairedEvent.slug}`"
                        class="shrink-0 px-3.5 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5"
                    >
                        <span>Lanjutkan</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </a>
                </div>

                <!-- Activation Form -->
                <form @submit.prevent="handlePairDevice" class="space-y-4">
                    <div>
                        <input
                            v-model="activationCode"
                            type="text"
                            maxlength="10"
                            placeholder="Contoh: LUMA26"
                            autofocus
                            class="w-full text-center tracking-widest text-2xl sm:text-3xl font-mono uppercase font-black py-4 px-4 rounded-2xl bg-slate-50 border-2 border-slate-300 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/15 outline-none text-slate-900 placeholder:text-slate-300 placeholder:font-normal placeholder:text-xl transition-all"
                        />
                    </div>

                    <!-- Error Alert -->
                    <div v-if="errorMessage" class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center justify-center gap-2">
                        <AlertCircle class="w-4 h-4 shrink-0 text-rose-600" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <!-- Success Alert -->
                    <div v-if="successInfo" class="p-4 rounded-2xl bg-teal-50 border border-teal-200 text-teal-900 text-xs font-bold flex flex-col items-center gap-1.5 animate-pulse">
                        <div class="flex items-center gap-2">
                            <CheckCircle2 class="w-4 h-4 text-teal-600" />
                            <span>{{ successInfo.message }}</span>
                        </div>
                        <span class="text-[11px] text-teal-700 font-normal">Mengarahkan ke layar foto...</span>
                    </div>

                    <button
                        type="submit"
                        :disabled="isLoading || !activationCode.trim() || !!successInfo"
                        class="w-full py-4 px-6 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-base transition-all disabled:opacity-50 shadow-xl shadow-teal-600/30 flex items-center justify-center gap-2 hover:scale-102 active:scale-98"
                    >
                        <RefreshCw v-if="isLoading" class="w-5 h-5 animate-spin" />
                        <span>{{ isLoading ? 'Memverifikasi Kode...' : 'Hubungkan & Buka Layar Booth' }}</span>
                        <ArrowRight v-if="!isLoading" class="w-5 h-5" />
                    </button>
                </form>

                <!-- Touchscreen On-Screen Keypad -->
                <div v-if="showTouchKeypad" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 animate-fadeIn">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider text-left px-1">
                        Keyboard Sentuh:
                    </div>
                    <div class="grid grid-cols-6 sm:grid-cols-10 gap-1.5 text-xs font-bold">
                        <button
                            v-for="char in keypadChars"
                            :key="char"
                            type="button"
                            @click="typeKey(char)"
                            class="py-2.5 rounded-lg bg-white hover:bg-teal-50 hover:text-teal-700 border border-slate-200 shadow-2xs font-mono font-extrabold active:scale-95 transition"
                        >
                            {{ char }}
                        </button>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button
                            type="button"
                            @click="clearKey"
                            class="flex-1 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition"
                        >
                            Hapus Semua
                        </button>
                        <button
                            type="button"
                            @click="backspaceKey"
                            class="flex-1 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs flex items-center justify-center gap-1 transition"
                        >
                            <Delete class="w-3.5 h-3.5" />
                            <span>Backspace</span>
                        </button>
                    </div>
                </div>

                <!-- Info Helper Box -->
                <div class="pt-2 text-left">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-500 leading-relaxed">
                        <strong class="text-slate-700 font-bold">💡 Di mana menemukan Kode Aktivasi?</strong>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            Masuk ke <strong>Portal Organizer</strong> &rarr; Buka <strong>Detail Event</strong> &rarr; Lihat tab <strong>Perangkat Booth</strong>.
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <!-- Footer -->
        <footer class="relative z-10 text-center text-xs text-slate-400 py-4 font-mono">
            Luma Photobooth • Developed by <strong class="text-slate-600">YOTA INOVASI NUSANTARA</strong>
        </footer>

    </div>
</template>


<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isInstallable = ref(false);
const isInstalled = ref(false);
const showIosModal = ref(false);
const isIos = ref(false);

const checkStandalone = () => {
    if (typeof window === 'undefined') return false;
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true ||
        document.referrer.includes('android-app://')
    );
};

const handlePromptReady = () => {
    if (!checkStandalone()) {
        isInstallable.value = true;
    }
};

const handleInstalled = () => {
    isInstalled.value = true;
    isInstallable.value = false;
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        isInstalled.value = checkStandalone();

        // Check if iOS
        const userAgent = window.navigator.userAgent.toLowerCase();
        isIos.value = /iphone|ipad|ipod/.test(userAgent) && !window.MSStream;

        if (window.deferredPwaPrompt && !isInstalled.value) {
            isInstallable.value = true;
        }

        window.addEventListener('pwa-prompt-ready', handlePromptReady);
        window.addEventListener('pwa-installed', handleInstalled);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('pwa-prompt-ready', handlePromptReady);
        window.removeEventListener('pwa-installed', handleInstalled);
    }
});

const triggerInstall = async () => {
    if (window.deferredPwaPrompt) {
        window.deferredPwaPrompt.prompt();
        const choice = await window.deferredPwaPrompt.userChoice;
        if (choice.outcome === 'accepted') {
            isInstalled.value = true;
            isInstallable.value = false;
        }
        window.deferredPwaPrompt = null;
    } else if (isIos.value && !isInstalled.value) {
        showIosModal.value = true;
    } else {
        alert('Untuk memasang aplikasi ini: Klik menu browser (titik tiga atau icon share), lalu pilih "Install App" atau "Tambahkan ke Layar Utama".');
    }
};
</script>

<template>
    <div v-if="!isInstalled">
        <button
            @click="triggerInstall"
            type="button"
            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-teal-50 border border-teal-200 text-teal-700 hover:bg-teal-100 hover:border-teal-300 transition-all shadow-sm active:scale-95"
            title="Pasang Luma Photobooth di perangkat (Kiosk Standalone)"
        >
            <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Pasang Aplikasi (PWA)</span>
        </button>

        <!-- Modal Panduan iOS -->
        <div v-if="showIosModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center shadow-xl border border-teal-100">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Pasang di iPhone / iPad</h3>
                <p class="text-xs text-slate-600 leading-relaxed mb-4">
                    Tekan tombol <span class="font-semibold text-teal-600">Share / Bagikan</span> (ikon kotak panah ke atas) di Safari, lalu pilih <span class="font-semibold text-teal-600">"Add to Home Screen"</span> untuk menjalankan photobooth dalam mode layar penuh (Kiosk).
                </p>
                <button
                    @click="showIosModal = false"
                    type="button"
                    class="w-full py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-xs rounded-xl shadow-md transition"
                >
                    Mengerti
                </button>
            </div>
        </div>
    </div>
</template>


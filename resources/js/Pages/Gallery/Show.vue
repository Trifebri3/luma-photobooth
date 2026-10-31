<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Download, Share2, ShieldCheck, Calendar, MapPin, Sparkles, Check } from 'lucide-vue-next';

const props = defineProps({
    token: String,
    visitor: Object,
    event: Object,
    photo: Object,
    imageUrl: String,
    downloadUrl: String,
});

const isCopied = ref(false);

async function handleShare() {
    if (navigator.share) {
        try {
            await navigator.share({
                title: `Foto ${props.visitor.name} di ${props.event.name}`,
                text: `Lihat foto saya di ${props.event.name}! 🎉`,
                url: window.location.href,
            });
        } catch (e) {
            // share cancelled
        }
    } else {
        await navigator.clipboard.writeText(window.location.href);
        isCopied.value = true;
        setTimeout(() => { isCopied.value = false; }, 2000);
    }
}
</script>

<template>
    <Head :title="`Galeri Foto - ${visitor.name}`" />

    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col justify-between selection:bg-teal-500 selection:text-white font-sans">
        <!-- Top App Bar -->
        <header class="border-b border-slate-200/80 backdrop-blur-md bg-white/90 sticky top-0 z-30 shadow-xs">
            <div class="max-w-3xl mx-auto px-4 py-3.5 flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <img src="/logo.png" alt="Luma Photobooth" class="h-8 w-auto object-contain" />
                    <div>
                        <div class="text-[11px] text-teal-600 font-extrabold tracking-wide uppercase">Galeri Foto Privat</div>
                        <h1 class="text-sm font-bold text-slate-900 truncate max-w-[200px] sm:max-w-xs">{{ event.name }}</h1>
                    </div>
                </div>

                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-50 border border-teal-200 text-teal-700 text-xs font-bold">
                    <ShieldCheck class="w-3.5 h-3.5 text-teal-600" />
                    <span>Anti-Bocor</span>
                </div>
            </div>
        </header>

        <!-- Main Photo Content -->
        <main class="flex-1 max-w-xl mx-auto w-full px-4 py-8 flex flex-col items-center">
            <!-- Greeting Banner -->
            <div class="w-full text-center mb-6">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-50 text-teal-700 text-xs font-bold mb-2 border border-teal-200">
                    <Sparkles class="w-3.5 h-3.5 text-teal-600" />
                    <span>Hai, {{ visitor.name }}!</span>
                </div>
                <h2 class="text-2xl font-extrabold text-slate-900">Kenangan Manismu Telah Siap</h2>
                <p class="text-xs text-slate-500 mt-1">Diambil pada {{ photo.created_at }}</p>
            </div>

            <!-- Photo Card Container (White with clean shadow) -->
            <div class="w-full rounded-3xl overflow-hidden bg-white border border-slate-200 shadow-xl relative group p-2">
                <img
                    :src="imageUrl"
                    :alt="`Foto ${visitor.name}`"
                    class="w-full h-auto object-contain mx-auto rounded-2xl"
                    loading="eager"
                />
            </div>

            <!-- Action Buttons -->
            <div class="w-full mt-6 space-y-3">
                <a
                    :href="downloadUrl"
                    class="w-full py-4 px-6 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-base flex items-center justify-center gap-2.5 shadow-lg shadow-teal-600/30 transition-all hover:scale-[1.02] active:scale-[0.98]"
                >
                    <Download class="w-5 h-5" />
                    <span>Unduh Foto Resolusi Tinggi</span>
                </a>

                <button
                    @click="handleShare"
                    class="w-full py-3.5 px-6 rounded-2xl bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 font-bold text-sm flex items-center justify-center gap-2 transition-all shadow-xs"
                >
                    <Share2 v-if="!isCopied" class="w-4 h-4 text-teal-600" />
                    <Check v-else class="w-4 h-4 text-emerald-600" />
                    <span>{{ isCopied ? 'Tautan Disalin ke Clipboard!' : 'Bagikan ke Teman / Media Sosial' }}</span>
                </button>
            </div>

            <!-- Event Details Card -->
            <div class="w-full mt-8 p-5 rounded-2xl bg-white border border-slate-200 text-xs text-slate-600 space-y-2.5 shadow-xs">
                <div class="font-extrabold text-slate-900 text-sm pb-2 border-b border-slate-100 flex items-center justify-between">
                    <span>Informasi Acara</span>
                    <span class="text-teal-600 font-semibold">Ukuran: {{ photo.file_size }}</span>
                </div>

                <div v-if="event.location" class="flex items-center gap-2">
                    <MapPin class="w-4 h-4 text-teal-600 shrink-0" />
                    <span>{{ event.location }}</span>
                </div>

                <div v-if="event.start_date" class="flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-teal-600 shrink-0" />
                    <span>{{ event.start_date }}</span>
                </div>

                <div v-if="event.description" class="text-slate-500 pt-1 leading-relaxed">
                    {{ event.description }}
                </div>
            </div>

            <!-- Privacy Note -->
            <div class="mt-8 text-center text-slate-500 text-[11px] leading-relaxed max-w-sm">
                <ShieldCheck class="w-4 h-4 mx-auto text-teal-600 mb-1" />
                Foto ini diakses menggunakan token enkripsi privat dan tidak dibagikan secara publik.
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
            Powered by <strong class="text-teal-600">Luma Photobooth</strong> &bull; <strong class="text-slate-700">by YOTA INOVASI NUSANTARA</strong>
        </footer>
    </div>
</template>

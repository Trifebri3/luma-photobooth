<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Camera, Sparkles, ShieldCheck, MessageSquare, Monitor, LayoutGrid, ArrowRight, CheckCircle2, Lock } from 'lucide-vue-next';
import PwaInstallButton from '@/Components/PwaInstallButton.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    demoSlug: {
        type: String,
        default: 'festival-kreativitas-2026',
    },
});
</script>

<template>
    <Head title="Luma Photobooth - Platform Photobooth SaaS by YOTA INOVASI NUSANTARA" />

    <div class="min-h-screen bg-slate-50 text-slate-800 font-sans selection:bg-teal-500 selection:text-white">
        <!-- Subtle Ambient Background Glow -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-teal-400/10 rounded-full blur-3xl"></div>
            <div class="absolute top-1/3 -right-40 w-96 h-96 bg-emerald-400/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-20 left-1/2 -translate-x-1/2 w-[600px] h-96 bg-teal-300/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Navigation Bar -->
        <header class="relative z-10 border-b border-slate-200/80 backdrop-blur-md bg-white/90 sticky top-0 shadow-xs">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <img src="/logo.png" alt="Luma Photobooth" class="h-10 w-auto object-contain" />
                    <div>
                        <div class="font-extrabold text-xl tracking-tight text-slate-900 leading-none">
                            Luma <span class="text-teal-600">Photobooth</span>
                        </div>
                        <div class="text-[10px] text-teal-700 font-bold uppercase tracking-wider mt-0.5">
                            by YOTA INOVASI NUSANTARA
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <PwaInstallButton />

                    <Link
                        :href="route('booth.show', demoSlug)"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 transition-all hover:scale-105"
                    >
                        <Camera class="w-4 h-4 text-teal-600" />
                        <span>Kiosk Booth</span>
                    </Link>

                    <Link
                        :href="route('admin.login')"
                        class="hidden md:inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                    >
                        <Lock class="w-3.5 h-3.5 text-teal-600" />
                        <span>Login Admin</span>
                    </Link>

                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="route('organizer.dashboard')"
                            class="px-5 py-2 text-sm font-bold rounded-xl bg-teal-600 hover:bg-teal-500 text-white shadow-md shadow-teal-600/20 transition-all"
                        >
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="text-xs font-bold text-slate-600 hover:text-teal-600 transition-colors"
                        >
                            Masuk
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="px-4 py-2 text-xs font-bold rounded-xl bg-teal-600 hover:bg-teal-500 text-white shadow-md shadow-teal-600/20 transition-all hover:scale-105"
                        >
                            Daftar Organizer
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="relative z-10 max-w-7xl mx-auto px-6 pt-14 pb-24">
            <div class="text-center max-w-3xl mx-auto">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-teal-50 border border-teal-200 text-teal-700 text-xs font-bold mb-6">
                    <Sparkles class="w-3.5 h-3.5 text-teal-600" />
                    <span>Multi-Event Photobooth Platform &bull; by YOTA INOVASI NUSANTARA</span>
                </div>

                <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-slate-900 leading-tight">
                    Photobooth Digital <br />
                    <span class="bg-gradient-to-r from-teal-600 to-emerald-600 bg-clip-text text-transparent">
                        Self-Service & Anti-Bocor
                    </span>
                </h1>

                <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                    Pengunjung mengambil foto secara mandiri tanpa operator, memilih template frame acara Anda, dan menerima foto langsung ke WhatsApp melalui galeri privat terenkripsi.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        v-if="demoSlug"
                        :href="route('booth.show', demoSlug)"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-base shadow-xl shadow-teal-600/25 transition-all hover:scale-105"
                    >
                        <Camera class="w-5 h-5" />
                        <span>Buka Layar Kiosk Photobooth</span>
                        <ArrowRight class="w-4 h-4 ml-1" />
                    </Link>

                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('organizer.dashboard')"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-4 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-base transition-all shadow-xs"
                    >
                        <span>Ke Dashboard Organizer</span>
                        <ArrowRight class="w-4 h-4" />
                    </Link>
                    <a
                        v-else
                        :href="route('auth.google.redirect')"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-7 py-4 rounded-2xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 font-bold text-base transition-all shadow-xs"
                    >
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>Daftar / Masuk Google</span>
                    </a>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="mt-20 grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-8 rounded-3xl bg-white border border-slate-200 shadow-sm hover:border-teal-400 hover:shadow-md transition-all hover:-translate-y-1">
                    <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-600 mb-6">
                        <Monitor class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-extrabold text-slate-900 mb-2">Self-Service Kiosk Mode</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Tampilan photobooth terkunci penuh untuk pengunjung di lokasi. Dilengkapi countdown visual, flash effect, dan switch kamera webcam USB atau HP/Tablet.
                    </p>
                </div>

                <div class="p-8 rounded-3xl bg-white border border-slate-200 shadow-sm hover:border-teal-400 hover:shadow-md transition-all hover:-translate-y-1">
                    <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-600 mb-6">
                        <LayoutGrid class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-extrabold text-slate-900 mb-2">Visual Frame Studio</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Organizer dapat mengunggah overlay frame PNG transparan dan memetakan koordinat slot foto secara visual (1–4 slot foto strip atau polaroid) tanpa batasan.
                    </p>
                </div>

                <div class="p-8 rounded-3xl bg-white border border-slate-200 shadow-sm hover:border-teal-400 hover:shadow-md transition-all hover:-translate-y-1">
                    <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-600 mb-6">
                        <ShieldCheck class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-extrabold text-slate-900 mb-2">Anti-Bocor Private Gallery</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Foto disimpan di storage privat. Tautan unik acak 48-karakter dikirim via WhatsApp sehingga foto antar pengunjung terisolasi total dan aman dari akses publik.
                    </p>
                </div>
            </div>

            <!-- Workflow Visual -->
            <div class="mt-20 p-8 sm:p-12 rounded-3xl bg-white border border-slate-200 shadow-sm text-center">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Alur Mandiri Tanpa Operator</h2>
                <p class="text-slate-500 text-sm mt-2 max-w-lg mx-auto">
                    Buat Event &rarr; Aktifkan Booth di Lokasi &rarr; Pengunjung Foto &rarr; Otomatisasi Frame &rarr; Kirim ke WhatsApp.
                </p>

                <div class="mt-10 grid grid-cols-2 sm:grid-cols-4 gap-4 text-left">
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-xs font-bold text-teal-600 mb-1">LANGKAH 01</div>
                        <div class="font-bold text-slate-900 text-sm">Input Identitas</div>
                        <div class="text-slate-500 text-xs mt-1">Nama & WhatsApp di layar sentuh booth</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-xs font-bold text-teal-600 mb-1">LANGKAH 02</div>
                        <div class="font-bold text-slate-900 text-sm">Ambil Foto</div>
                        <div class="text-slate-500 text-xs mt-1">Countdown 5 detik + fitur retake foto</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-xs font-bold text-teal-600 mb-1">LANGKAH 03</div>
                        <div class="font-bold text-slate-900 text-sm">Pilih Template</div>
                        <div class="text-slate-500 text-xs mt-1">Komposisi otomatis dalam frame acara</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-xs font-bold text-teal-600 mb-1">LANGKAH 04</div>
                        <div class="font-bold text-slate-900 text-sm">Scan QR / WhatsApp</div>
                        <div class="text-slate-500 text-xs mt-1">Link galeri privat langsung terkirim</div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer with BY YOTA INOVASI NUSANTARA -->
        <footer class="border-t border-slate-200 bg-white py-8 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <img src="/logo.png" alt="Luma Photobooth" class="h-6 w-auto" />
                    <span>Luma Photobooth &bull; Developed by <strong class="text-slate-800">YOTA INOVASI NUSANTARA</strong></span>
                </div>
                <div class="flex items-center gap-4 text-xs font-semibold">
                    <Link :href="route('admin.login')" class="text-teal-600 hover:underline">Portal Super Admin</Link>
                    <span>&bull;</span>
                    <Link :href="route('login')" class="text-slate-600 hover:underline">Portal Organizer</Link>
                </div>
            </div>
        </footer>
    </div>
</template>

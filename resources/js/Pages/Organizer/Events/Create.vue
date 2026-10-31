<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles, Check } from 'lucide-vue-next';

const form = useForm({
    name: '',
    description: '',
    location: '',
    start_date: '',
    end_date: '',
    max_sessions_per_visitor: 3,
    photos_per_session: 3,
    countdown_seconds: 5,
    max_retakes: 2,
    is_unlimited_session: false,
    enable_whatsapp_delivery: true,
});

function submit() {
    form.post(route('organizer.events.store'));
}
</script>

<template>
    <Head title="Buat Event Baru - Luma Photobooth" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('organizer.events.index')"
                    class="p-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                >
                    <ArrowLeft class="w-4 h-4" />
                </Link>
                <div>
                    <h2 class="text-xl font-extrabold leading-tight text-slate-900">
                        Buat Event Baru
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Siapkan acara baru beserta aturan sesi photobooth dan pengiriman foto
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- General Event Information -->
                    <div class="p-8 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-5">
                        <h3 class="font-extrabold text-base text-slate-900 border-b border-slate-100 pb-3">
                            Informasi Acara
                        </h3>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Nama Event *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Contoh: Wedding Sarah & Kevin 2026"
                                class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none text-sm"
                            />
                            <span v-if="form.errors.name" class="text-xs text-rose-500 mt-1 block">{{ form.errors.name }}</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Deskripsi Singkat
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                placeholder="Jelaskan tema acara atau sambutan untuk pengunjung..."
                                class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none text-sm"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Lokasi</label>
                                <input
                                    v-model="form.location"
                                    type="text"
                                    placeholder="Contoh: Hotel Mulia Senayan"
                                    class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 outline-none text-sm"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Mulai Acara</label>
                                <input
                                    v-model="form.start_date"
                                    type="datetime-local"
                                    class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 outline-none text-sm"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Selesai Acara</label>
                                <input
                                    v-model="form.end_date"
                                    type="datetime-local"
                                    class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 outline-none text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Photobooth Kiosk Engine Rules -->
                    <div class="p-8 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-5">
                        <h3 class="font-extrabold text-base text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between">
                            <span>Konfigurasi Sesi & Kamera Kiosk</span>
                            <span class="text-xs text-teal-600 font-bold">Otomatisasi Mandiri</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Maks. Sesi per Pengunjung
                                </label>
                                <input
                                    v-model.number="form.max_sessions_per_visitor"
                                    type="number"
                                    min="1"
                                    max="10"
                                    class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 outline-none text-sm font-bold"
                                />
                                <span class="text-[11px] text-slate-400">Contoh: 3 sesi per nomor WA</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Jumlah Foto per Sesi
                                </label>
                                <input
                                    v-model.number="form.photos_per_session"
                                    type="number"
                                    min="1"
                                    max="6"
                                    class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 outline-none text-sm font-bold"
                                />
                                <span class="text-[11px] text-slate-400">Jumlah slot capture foto</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Timer Countdown (Detik)
                                </label>
                                <input
                                    v-model.number="form.countdown_seconds"
                                    type="number"
                                    min="3"
                                    max="10"
                                    class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 outline-none text-sm font-bold"
                                />
                                <span class="text-[11px] text-slate-400">Waktu persiapan sebelum jepret</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Maksimal Retake Foto
                                </label>
                                <input
                                    v-model.number="form.max_retakes"
                                    type="number"
                                    min="0"
                                    max="5"
                                    class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-teal-500 outline-none text-sm font-bold"
                                />
                                <span class="text-[11px] text-slate-400">Kesempatan ulang jika kurang pas</span>
                            </div>
                        </div>

                        <!-- Checkboxes -->
                        <div class="pt-4 border-t border-slate-100 space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    v-model="form.enable_whatsapp_delivery"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                />
                                <span class="text-xs font-semibold text-slate-700">
                                    Kirim tautan galeri privat secara otomatis ke nomor WhatsApp pengunjung
                                </span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    v-model="form.is_unlimited_session"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                />
                                <span class="text-xs font-semibold text-slate-700">
                                    Mode Bebas (Unlimited Sesi) — Pengunjung bebas berfoto tanpa batasan kuota sesi
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Link
                            :href="route('organizer.events.index')"
                            class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-bold hover:bg-slate-100 transition"
                        >
                            Batal
                        </Link>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-7 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-sm shadow-md shadow-teal-600/30 transition-all disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Buat Event & Template Awal' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

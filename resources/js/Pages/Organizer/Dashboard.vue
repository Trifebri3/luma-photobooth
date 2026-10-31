<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar, Users, Camera, Smartphone, MessageSquare, Plus, ArrowRight, CheckCircle2, Sparkles, ExternalLink
} from 'lucide-vue-next';

defineProps({
    stats: Object,
    recentEvents: Array,
});
</script>

<template>
    <Head title="Organizer Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold leading-tight text-slate-900">
                        Dashboard Organizer
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Ringkasan aktivitas photobooth, perangkat booth, dan pengiriman galeri WhatsApp
                    </p>
                </div>

                <Link
                    :href="route('organizer.events.create')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-sm font-bold shadow-md shadow-teal-600/20 transition-all hover:scale-105"
                >
                    <Plus class="w-4 h-4" />
                    <span>Buat Event Baru</span>
                </Link>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Metrics Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Event</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                                <Calendar class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">
                            {{ stats.total_events }}
                        </div>
                    </div>

                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Pengunjung</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <Users class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">
                            {{ stats.total_visitors }}
                        </div>
                    </div>

                    <Link
                        :href="route('organizer.photos.index')"
                        class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs hover:border-teal-400 hover:shadow-sm transition block group"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider group-hover:text-teal-700 transition">Foto Terambil</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center group-hover:bg-teal-600 group-hover:text-white transition">
                                <Camera class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-slate-900">{{ stats.total_photos }}</span>
                            <span class="text-[11px] font-bold text-teal-600 group-hover:translate-x-0.5 transition flex items-center gap-0.5">
                                Lihat Galeri &rarr;
                            </span>
                        </div>
                    </Link>

                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Perangkat Booth</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                                <Smartphone class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">
                            {{ stats.active_devices }} <span class="text-xs text-slate-400 font-normal">/ {{ stats.total_devices }} aktif</span>
                        </div>
                    </div>

                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">WhatsApp Terkirim</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <MessageSquare class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">
                            {{ stats.wa_delivered }}
                        </div>
                    </div>
                </div>

                <!-- Recent Events Table -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-extrabold text-base text-slate-900">Event Terbaru</h3>
                            <p class="text-xs text-slate-500">Daftar acara yang sedang Anda kelola</p>
                        </div>

                        <Link
                            :href="route('organizer.events.index')"
                            class="text-xs font-bold text-teal-600 hover:text-teal-700 flex items-center gap-1"
                        >
                            <span>Lihat Semua Event</span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="ev in recentEvents"
                            :key="ev.id"
                            class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50 transition"
                        >
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <span class="font-extrabold text-base text-slate-900">{{ ev.name }}</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase bg-teal-50 text-teal-700 border border-teal-200">
                                        {{ ev.status }}
                                    </span>
                                </div>
                                <div class="mt-1 flex items-center gap-4 text-xs text-slate-500">
                                    <span>{{ ev.location || 'Lokasi belum ditentukan' }}</span>
                                    <span>&bull;</span>
                                    <span>{{ ev.visitors_count }} Pengunjung</span>
                                    <span>&bull;</span>
                                    <span>{{ ev.devices_count }} Perangkat Booth</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <a
                                    :href="`/booth/${ev.slug}`"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                                >
                                    <span>Layar Booth</span>
                                    <ExternalLink class="w-3.5 h-3.5" />
                                </a>

                                <Link
                                    :href="route('organizer.events.studio', ev.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-teal-50 text-teal-700 hover:bg-teal-100 transition border border-teal-200"
                                >
                                    <span>Frame Studio</span>
                                </Link>

                                <Link
                                    :href="route('organizer.events.show', ev.id)"
                                    class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-xs font-bold bg-teal-600 hover:bg-teal-500 text-white transition shadow-xs"
                                >
                                    <span>Kelola Event</span>
                                </Link>
                            </div>
                        </div>

                        <div v-if="recentEvents.length === 0" class="p-8 text-center text-slate-400 text-sm">
                            Belum ada event. Klik "Buat Event Baru" untuk memulai!
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

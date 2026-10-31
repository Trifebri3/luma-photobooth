<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ShieldCheck, Users, Calendar, Camera, MessageSquare, Smartphone, ExternalLink,
    CheckCircle2, Radio, Server, Check
} from 'lucide-vue-next';

defineProps({
    stats: Object,
    organizers: Array,
    events: Array,
    recentWaLogs: Array,
    fonnteConfig: Object,
});
</script>

<template>
    <Head title="Super Admin Control Center - Luma Photobooth" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-extrabold text-slate-900 leading-tight">
                            Super Admin Platform Control
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-teal-50 text-teal-700 border border-teal-200">
                            Root Access
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Luma Photobooth SaaS Management &bull; <strong class="text-teal-700">by YOTA INOVASI NUSANTARA</strong>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('organizer.dashboard')"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
                    >
                        Tampilan Organizer &rarr;
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- WhatsApp Fonnte Gateway Active Status Card -->
                <div class="p-6 rounded-3xl bg-white border border-teal-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-600 shrink-0">
                            <Radio class="w-6 h-6 animate-pulse" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-sm text-slate-900">WhatsApp Gateway (Fonnte API)</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                    Terhubung & Siap
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-3">
                                <span>Nomor Pengirim: <strong class="font-mono text-slate-800">{{ fonnteConfig.sender }}</strong></span>
                                <span>&bull;</span>
                                <span>Token: <strong class="font-mono text-slate-800">{{ fonnteConfig.token_masked }}</strong></span>
                                <span>&bull;</span>
                                <span class="text-teal-700 font-semibold">Terkirim: {{ stats.wa_sent }} pesan</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-xs text-slate-400 font-medium">
                        Device Name: <strong>UJI COBA</strong>
                    </div>
                </div>

                <!-- Global Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase">Total Akun Organizer</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                                <Users class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">{{ stats.total_organizers }}</div>
                    </div>

                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase">Seluruh Event</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <Calendar class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">{{ stats.total_events }}</div>
                    </div>

                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase">Total Foto Diproses</span>
                            <div class="w-8 h-8 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center">
                                <Camera class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">{{ stats.total_photos }}</div>
                    </div>

                    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase">Pengunjung Photobooth</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                                <Users class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900">{{ stats.total_visitors }}</div>
                    </div>
                </div>

                <!-- Two Column Table: Organizers & All Events -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Organizers List -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="font-extrabold text-sm text-slate-900">Daftar Akun Organizer</h3>
                            <span class="text-xs font-bold text-teal-600">{{ organizers.length }} Terdaftar</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="org in organizers" :key="org.id" class="p-4 flex items-center justify-between hover:bg-slate-50 text-xs">
                                <div>
                                    <div class="font-bold text-slate-900">{{ org.name }}</div>
                                    <div class="font-mono text-slate-400">{{ org.email }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 font-bold text-slate-700">
                                        {{ org.events_count }} Event
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- All Events List -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="font-extrabold text-sm text-slate-900">Seluruh Event Platform</h3>
                            <span class="text-xs font-bold text-teal-600">{{ events.length }} Aktif</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="ev in events" :key="ev.id" class="p-4 flex items-center justify-between hover:bg-slate-50 text-xs">
                                <div>
                                    <div class="font-bold text-slate-900">{{ ev.name }}</div>
                                    <div class="text-slate-400">Oleh: {{ ev.user?.name }}</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-slate-500 font-medium">{{ ev.visitors_count }} Pengunjung</span>
                                    <a
                                        :href="`/booth/${ev.slug}`"
                                        target="_blank"
                                        class="p-1.5 rounded-lg bg-teal-50 text-teal-700 hover:bg-teal-100 transition"
                                        title="Buka Booth"
                                    >
                                        <ExternalLink class="w-3.5 h-3.5" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent WhatsApp Logs Table -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-900">Log Pengiriman WhatsApp Fonnte Terakhir</h3>
                            <p class="text-xs text-slate-400">Riwayat pengiriman link galeri foto ke nomor pengunjung</p>
                        </div>
                        <span class="text-xs font-bold text-teal-600">Gateway: Fonnte Cloud</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 border-b border-slate-100">
                                <tr>
                                    <th class="p-3.5">Nomor Tujuan</th>
                                    <th class="p-3.5">Event</th>
                                    <th class="p-3.5">Pesan</th>
                                    <th class="p-3.5">Status</th>
                                    <th class="p-3.5">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="log in recentWaLogs" :key="log.id">
                                    <td class="p-3.5 font-mono font-bold text-slate-900">{{ log.phone_number }}</td>
                                    <td class="p-3.5 font-medium text-slate-700">{{ log.session?.event?.name || '-' }}</td>
                                    <td class="p-3.5 text-slate-500 max-w-xs truncate">{{ log.message_content }}</td>
                                    <td class="p-3.5">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full font-bold uppercase text-[10px]',
                                                log.status === 'sent' || log.status === 'delivered'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-rose-100 text-rose-800'
                                            ]"
                                        >
                                            {{ log.status }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-slate-400">{{ new Date(log.created_at).toLocaleString('id-ID') }}</td>
                                </tr>
                                <tr v-if="!recentWaLogs?.length">
                                    <td colspan="5" class="p-6 text-center text-slate-400">
                                        Belum ada riwayat pengiriman WhatsApp.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>


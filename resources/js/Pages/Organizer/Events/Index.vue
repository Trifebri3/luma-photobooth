<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, Plus, ExternalLink, LayoutGrid } from 'lucide-vue-next';

defineProps({
    events: Object,
});
</script>

<template>
    <Head title="Daftar Event - Luma Photobooth" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold leading-tight text-slate-900">
                        Event Saya
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Kelola seluruh acara, template frame, dan perangkat photobooth
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
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="event in events.data"
                        :key="event.id"
                        class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between hover:shadow-md hover:border-teal-400 transition"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-teal-50 text-teal-700 border border-teal-200">
                                    {{ event.status }}
                                </span>
                                <span class="text-xs text-slate-400 font-medium">
                                    {{ event.created_at ? new Date(event.created_at).toLocaleDateString('id-ID') : '' }}
                                </span>
                            </div>

                            <h3 class="text-lg font-extrabold text-slate-900 leading-snug mb-1">
                                {{ event.name }}
                            </h3>
                            <p class="text-xs text-slate-500 line-clamp-2 mb-4">
                                {{ event.description || 'Tidak ada deskripsi.' }}
                            </p>

                            <div class="grid grid-cols-3 gap-2 p-3 rounded-2xl bg-slate-50 border border-slate-100 text-center text-xs mb-6">
                                <div>
                                    <div class="font-extrabold text-slate-900">{{ event.visitors_count }}</div>
                                    <div class="text-[11px] text-slate-400 font-medium">Pengunjung</div>
                                </div>
                                <div>
                                    <div class="font-extrabold text-slate-900">{{ event.devices_count }}</div>
                                    <div class="text-[11px] text-slate-400 font-medium">Booth</div>
                                </div>
                                <div>
                                    <div class="font-extrabold text-slate-900">{{ event.frame_templates_count }}</div>
                                    <div class="text-[11px] text-slate-400 font-medium">Frame</div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                            <a
                                :href="`/booth/${event.slug}`"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                            >
                                <span>Buka Booth</span>
                                <ExternalLink class="w-3.5 h-3.5 text-teal-600" />
                            </a>

                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('organizer.events.studio', event.id)"
                                    class="p-2 rounded-xl bg-teal-50 text-teal-700 hover:bg-teal-100 transition border border-teal-200"
                                    title="Frame Studio"
                                >
                                    <LayoutGrid class="w-4 h-4" />
                                </Link>

                                <Link
                                    :href="route('organizer.events.show', event.id)"
                                    class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold shadow-xs transition"
                                >
                                    Detail Event
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="events.data.length === 0" class="text-center py-16 bg-white rounded-3xl border border-slate-200 shadow-xs">
                    <Calendar class="w-12 h-12 text-slate-400 mx-auto mb-3" />
                    <h3 class="font-extrabold text-lg text-slate-900">Belum Ada Event</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Mulai buat photobooth untuk wedding, seminar, festival, atau pesta perusahaan Anda!
                    </p>
                    <Link
                        :href="route('organizer.events.create')"
                        class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-sm font-bold shadow-md transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Buat Event Pertama</span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

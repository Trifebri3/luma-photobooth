<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Calendar, Users, Camera, Smartphone, LayoutGrid, Settings, Plus, ExternalLink,
    Copy, Check, ShieldCheck, MessageSquare, ArrowLeft, Trash2, Key
} from 'lucide-vue-next';

const props = defineProps({
    event: Object,
    boothUrl: String,
});

const activeTab = ref('devices');
const isCopied = ref(false);

function copyBoothUrl() {
    navigator.clipboard.writeText(props.boothUrl);
    isCopied.value = true;
    setTimeout(() => { isCopied.value = false; }, 2000);
}

// Add Device Form
const isAddingDevice = ref(false);
const deviceForm = useForm({
    device_name: '',
});
function submitDevice() {
    deviceForm.post(route('organizer.events.devices.store', props.event.id), {
        onSuccess: () => {
            isAddingDevice.value = false;
            deviceForm.reset();
        },
    });
}

// Settings Form
const settingsForm = useForm({
    name: props.event.name,
    description: props.event.description || '',
    location: props.event.location || '',
    start_date: props.event.start_date?.substring(0, 16) || '',
    end_date: props.event.end_date?.substring(0, 16) || '',
    status: props.event.status,
    max_sessions_per_visitor: props.event.settings?.max_sessions_per_visitor || 3,
    photos_per_session: props.event.settings?.photos_per_session || 3,
    countdown_seconds: props.event.settings?.countdown_seconds || 5,
    max_retakes: props.event.settings?.max_retakes || 2,
    is_unlimited_session: Boolean(props.event.settings?.is_unlimited_session),
    enable_whatsapp_delivery: Boolean(props.event.settings?.enable_whatsapp_delivery),
    token_expiry_days: props.event.settings?.token_expiry_days || 30,
});

function submitSettings() {
    settingsForm.put(route('organizer.events.update', props.event.id));
}
</script>

<template>
    <Head :title="`${event.name} - Kelola Event`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('organizer.events.index')"
                        class="p-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                                {{ event.name }}
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                {{ event.status }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ event.location || 'Lokasi belum diset' }} &bull; Slug: <span class="font-mono text-indigo-400">{{ event.slug }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="copyBoothUrl"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition"
                    >
                        <Copy v-if="!isCopied" class="w-3.5 h-3.5 text-gray-400" />
                        <Check v-else class="w-3.5 h-3.5 text-emerald-500" />
                        <span>{{ isCopied ? 'Tersalin!' : 'Salin Link Booth' }}</span>
                    </button>

                    <a
                        :href="boothUrl"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 transition hover:scale-105"
                    >
                        <span>Buka Layar Kiosk</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Navigation Tabs -->
                <div class="flex items-center space-x-2 border-b border-gray-200 dark:border-gray-700 pb-3 text-sm font-semibold">
                    <button
                        @click="activeTab = 'devices'"
                        :class="[
                            'px-4 py-2 rounded-xl transition-all',
                            activeTab === 'devices'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        Perangkat Booth ({{ event.devices?.length || 0 }})
                    </button>

                    <button
                        @click="activeTab = 'frames'"
                        :class="[
                            'px-4 py-2 rounded-xl transition-all',
                            activeTab === 'frames'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        Template Frame ({{ event.frame_templates?.length || 0 }})
                    </button>

                    <button
                        @click="activeTab = 'visitors'"
                        :class="[
                            'px-4 py-2 rounded-xl transition-all',
                            activeTab === 'visitors'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        Pengunjung & Galeri ({{ event.visitors?.length || 0 }})
                    </button>

                    <button
                        @click="activeTab = 'settings'"
                        :class="[
                            'px-4 py-2 rounded-xl transition-all',
                            activeTab === 'settings'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        Pengaturan Event
                    </button>
                </div>

                <!-- TAB 1: PERANGKAT BOOTH (DEVICES) -->
                <div v-if="activeTab === 'devices'" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-base text-gray-900 dark:text-white">Perangkat Photobooth di Lokasi</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Hubungkan PC, Touchscreen, atau Tablet dengan kode aktivasi</p>
                        </div>

                        <button
                            @click="isAddingDevice = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Tambah Perangkat</span>
                        </button>
                    </div>

                    <!-- Modal Add Device -->
                    <div v-if="isAddingDevice" class="p-6 rounded-2xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50">
                        <h4 class="font-bold text-sm text-gray-900 dark:text-white mb-2">Tambah Perangkat Booth Baru</h4>
                        <div class="flex gap-3">
                            <input
                                v-model="deviceForm.device_name"
                                type="text"
                                placeholder="Nama perangkat (misal: Booth Pintu Masuk #02)"
                                class="flex-1 py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
                            />
                            <button
                                @click="submitDevice"
                                :disabled="deviceForm.processing || !deviceForm.device_name"
                                class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-500 disabled:opacity-50"
                            >
                                Simpan
                            </button>
                            <button
                                @click="isAddingDevice = false"
                                class="px-3 py-2 rounded-xl text-xs text-gray-500 hover:text-gray-800"
                            >
                                Batal
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="dev in event.devices"
                            :key="dev.id"
                            class="p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-bold text-sm text-gray-900 dark:text-white">{{ dev.device_name }}</span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                        dev.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                    ]"
                                >
                                    {{ dev.is_active ? 'Terhubung' : 'Belum Pairing' }}
                                </span>
                            </div>

                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-700/60 my-3 text-center">
                                <div class="text-[11px] text-gray-400">Kode Aktivasi Booth</div>
                                <div class="text-2xl font-mono font-extrabold tracking-widest text-indigo-600 dark:text-indigo-400">
                                    {{ dev.activation_code }}
                                </div>
                            </div>

                            <div class="text-[11px] text-gray-400 space-y-1">
                                <div>Heartbeat: {{ dev.last_heartbeat_at ? new Date(dev.last_heartbeat_at).toLocaleTimeString('id-ID') : 'Belum ada sinyal' }}</div>
                                <div>IP Address: {{ dev.ip_address || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: FRAME TEMPLATES -->
                <div v-if="activeTab === 'frames'" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-base text-gray-900 dark:text-white">Desain Frame & Slot Foto</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Template yang dipilih pengunjung saat berfoto di kiosk</p>
                        </div>

                        <Link
                            :href="route('organizer.events.studio', event.id)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                        >
                            <LayoutGrid class="w-3.5 h-3.5" />
                            <span>Buka Visual Frame Studio</span>
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            v-for="tmpl in event.frame_templates"
                            :key="tmpl.id"
                            class="p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm"
                        >
                            <div class="aspect-[3/4] rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center mb-4 overflow-hidden relative p-3">
                                <div class="text-center">
                                    <div class="font-bold text-indigo-500 text-sm">{{ tmpl.slots?.length }} Slot Foto</div>
                                    <div class="text-[11px] text-gray-400 font-mono">{{ tmpl.orientation }} &bull; {{ tmpl.canvas_width }}x{{ tmpl.canvas_height }}px</div>
                                </div>
                            </div>

                            <div class="font-bold text-sm text-gray-900 dark:text-white">{{ tmpl.name }}</div>
                            <div class="text-xs text-gray-500 mt-1">Status: {{ tmpl.is_active ? 'Aktif di Booth' : 'Nonaktif' }}</div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: VISITORS & GALLERY -->
                <div v-if="activeTab === 'visitors'" class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white">Aktivitas Pengunjung & Galeri Privat</h3>
                            <p class="text-xs text-gray-500">Foto terkirim ke WhatsApp pengunjung dengan token privat</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                    <tr>
                                        <th class="p-3.5">Nama Pengunjung</th>
                                        <th class="p-3.5">WhatsApp</th>
                                        <th class="p-3.5">Sesi Terpakai</th>
                                        <th class="p-3.5">Tanggal</th>
                                        <th class="p-3.5 text-right">Galeri Privat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="vis in event.visitors" :key="vis.id">
                                        <td class="p-3.5 font-semibold text-gray-900 dark:text-white">{{ vis.name }}</td>
                                        <td class="p-3.5 font-mono text-gray-500">{{ vis.phone_number }}</td>
                                        <td class="p-3.5">{{ vis.sessions_used }} Sesi</td>
                                        <td class="p-3.5 text-gray-400">{{ new Date(vis.created_at).toLocaleString('id-ID') }}</td>
                                        <td class="p-3.5 text-right">
                                            <span class="text-indigo-400 text-xs">Terkirim via WA</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!event.visitors?.length">
                                        <td colspan="5" class="p-8 text-center text-gray-400">
                                            Belum ada pengunjung yang mengambil foto di event ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: SETTINGS FORM -->
                <div v-if="activeTab === 'settings'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-8 max-w-3xl">
                    <form @submit.prevent="submitSettings" class="space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1">Maks. Sesi per Pengunjung</label>
                                <input v-model.number="settingsForm.max_sessions_per_visitor" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Countdown Kamera (Detik)</label>
                                <input v-model.number="settingsForm.countdown_seconds" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Maks. Retake per Foto</label>
                                <input v-model.number="settingsForm.max_retakes" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Masa Berlaku Link (Hari)</label>
                                <input v-model.number="settingsForm.token_expiry_days" type="number" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900" />
                            </div>
                        </div>

                        <div class="space-y-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <label class="flex items-center gap-2 text-xs">
                                <input v-model="settingsForm.enable_whatsapp_delivery" type="checkbox" class="rounded text-indigo-600" />
                                <span>Kirim link galeri otomatis ke WhatsApp</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs">
                                <input v-model="settingsForm.is_unlimited_session" type="checkbox" class="rounded text-indigo-600" />
                                <span>Mode Unlimited Sesi (Bebas Foto Tanpa Batas)</span>
                            </label>
                        </div>

                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="settingsForm.processing"
                                class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition"
                            >
                                {{ settingsForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ShieldCheck, Lock, Mail, ArrowRight, UserCheck } from 'lucide-vue-next';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('admin.login.submit'), {
        onFinish: () => form.reset('password'),
    });
}

function fillAdminCredentials() {
    form.email = 'admin@luma.test';
    form.password = 'password';
}
</script>

<template>
    <Head title="Super Admin Portal - Luma Photobooth" />

    <div class="min-h-screen bg-slate-50 flex flex-col justify-between py-12 sm:px-6 lg:px-8 font-sans selection:bg-teal-500 selection:text-white">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <Link :href="route('home')" class="inline-block">
                <img src="/logo.png" alt="Luma Photobooth" class="h-12 w-auto mx-auto object-contain" />
            </Link>

            <div class="mt-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-50 border border-teal-200 text-teal-700 text-xs font-bold">
                <ShieldCheck class="w-3.5 h-3.5 text-teal-600" />
                <span>Super Admin Portal</span>
            </div>

            <h2 class="mt-3 text-2xl font-extrabold text-slate-900 tracking-tight">
                Super Admin Login
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Luma Photobooth &bull; <strong class="text-slate-700">by YOTA INOVASI NUSANTARA</strong>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4">
            <div class="bg-white py-8 px-6 shadow-xl border border-slate-200 rounded-3xl sm:px-10 space-y-6">
                <!-- Status Notice -->
                <div v-if="status" class="p-3.5 rounded-xl bg-teal-50 border border-teal-200 text-teal-700 text-xs font-semibold">
                    {{ status }}
                </div>

                <!-- Error Notice -->
                <div v-if="form.errors.email" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-xs font-semibold">
                    {{ form.errors.email }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Super Admin</label>
                        <div class="relative">
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                placeholder="admin@luma.test"
                                class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 text-slate-900 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                placeholder="••••••••"
                                class="w-full py-2.5 px-3.5 rounded-xl border border-slate-300 text-slate-900 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 text-sm"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
                            <span class="text-slate-600">Ingat sesi saya</span>
                        </label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-sm shadow-md shadow-teal-600/30 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <span>{{ form.processing ? 'Memverifikasi...' : 'Masuk Super Admin' }}</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </form>

                <!-- Quick 1-Click Fill Credentials -->
                <div class="pt-4 border-t border-slate-100 text-center">
                    <button
                        type="button"
                        @click="fillAdminCredentials"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
                    >
                        <UserCheck class="w-3.5 h-3.5 text-teal-600" />
                        <span>Isi Akun Demo Admin (1-Klik)</span>
                    </button>
                </div>

                <!-- Back to Organizer Login -->
                <div class="text-center text-xs text-slate-500 pt-2">
                    Bukan Super Admin?
                    <Link :href="route('login')" class="text-teal-600 font-bold hover:underline">
                        Masuk Portal Organizer
                    </Link>
                </div>
            </div>
        </div>

        <footer class="text-center text-xs text-slate-400 py-4">
            Luma Photobooth &bull; <strong class="text-slate-600">by YOTA INOVASI NUSANTARA</strong>
        </footer>
    </div>
</template>


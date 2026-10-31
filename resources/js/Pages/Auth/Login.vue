<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ShieldCheck, UserCheck, ArrowRight } from 'lucide-vue-next';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk - Luma Photobooth" />

        <div class="mb-6 text-center">
            <h2 class="text-xl font-extrabold text-slate-900">Masuk ke Akun Anda</h2>
            <p class="text-xs text-slate-500 mt-1">Kelola event, frame photobooth, dan galeri acara</p>
        </div>

        <div v-if="status" class="mb-4 p-3 rounded-xl bg-teal-50 border border-teal-200 text-xs font-semibold text-teal-700">
            {{ status }}
        </div>

        <!-- Google OAuth Login Button -->
        <a
            :href="route('auth.google.redirect')"
            class="w-full py-3 px-4 rounded-xl border border-slate-300 hover:border-teal-500 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs flex items-center justify-center gap-3 transition shadow-xs hover:shadow-sm"
        >
            <svg class="w-4 h-4" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Masuk dengan Akun Google</span>
        </a>

        <!-- Divider -->
        <div class="relative my-5">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-slate-200"></div>
            </div>
            <div class="relative flex justify-center text-[11px] uppercase tracking-wider">
                <span class="bg-white px-2 text-slate-400 font-bold">atau dengan email</span>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Email Address" class="text-xs font-bold text-slate-700" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full text-sm"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="nama@email.com"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Password" class="text-xs font-bold text-slate-700" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs font-semibold text-teal-600 hover:underline"
                    >
                        Lupa password?
                    </Link>
                </div>
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full text-sm"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center">
                <label class="flex items-center cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" class="rounded text-teal-600 focus:ring-teal-500" />
                    <span class="ms-2 text-xs text-slate-600 font-medium">Ingat saya</span>
                </label>
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-3 text-sm bg-teal-600 hover:bg-teal-500 text-white font-extrabold rounded-xl shadow-md shadow-teal-600/30"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Masuk
                </PrimaryButton>
            </div>
        </form>

        <!-- Super Admin Link & Register Link -->
        <div class="mt-6 pt-4 border-t border-slate-100 flex flex-col items-center gap-2 text-xs">
            <Link :href="route('admin.login')" class="text-slate-600 hover:text-teal-600 font-bold flex items-center gap-1">
                <ShieldCheck class="w-4 h-4 text-teal-600" />
                <span>Portal Khusus Super Admin &rarr;</span>
            </Link>

            <div class="text-slate-500 mt-1">
                Belum punya akun?
                <Link :href="route('register')" class="text-teal-600 font-bold hover:underline">
                    Daftar Organizer Baru
                </Link>
            </div>
        </div>
    </GuestLayout>
</template>

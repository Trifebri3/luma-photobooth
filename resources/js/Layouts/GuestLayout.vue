<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-between bg-slate-50 py-8 px-4 font-sans selection:bg-teal-500 selection:text-white"
    >
        <div class="w-full max-w-md text-center pt-4">
            <Link href="/" class="inline-block">
                <ApplicationLogo class="h-12 w-auto mx-auto object-contain" />
            </Link>
            <div class="mt-2 text-xs font-semibold text-slate-500">
                Luma Photobooth &bull; <strong class="text-teal-700">by YOTA INOVASI NUSANTARA</strong>
            </div>
        </div>

        <div
            class="w-full max-w-md overflow-hidden bg-white p-8 shadow-xl border border-slate-200 rounded-3xl"
        >
            <slot />
        </div>

        <footer class="text-center text-xs text-slate-400 py-3">
            Luma Photobooth &copy; 2026 &bull; <strong class="text-slate-600">by YOTA INOVASI NUSANTARA</strong>
        </footer>
    </div>
</template>

import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0D9488',
    },
});

// PWA Service Worker Registration & Install Prompt Capture
if (typeof window !== 'undefined') {
    // Capture PWA beforeinstallprompt event globally
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.deferredPwaPrompt = e;
        window.dispatchEvent(new CustomEvent('pwa-prompt-ready', { detail: e }));
    });

    window.addEventListener('appinstalled', () => {
        window.deferredPwaPrompt = null;
        window.dispatchEvent(new CustomEvent('pwa-installed'));
        console.log('Luma Photobooth PWA successfully installed');
    });

    // Register Service Worker in production / supported environments
    if ('serviceWorker' in navigator && (window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then((registration) => {
                    console.log('Luma Photobooth SW registered with scope:', registration.scope);
                })
                .catch((error) => {
                    console.warn('Luma Photobooth SW registration failed:', error);
                });
        });
    }
}

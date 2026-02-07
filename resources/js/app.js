import '../css/app.css';
import './bootstrap';
import 'flowbite';
import 'flowbite-vue/index.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initPupils } from './pupils';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        const instance = vueApp.mount(el);

        if (typeof window !== 'undefined') {
            window.pupils = initPupils;
            initPupils();
            document.addEventListener('inertia:finish', initPupils);
        }

        return instance;
    },
    progress: {
        color: '#4B5563',
    },
});

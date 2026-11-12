import '../css/app.css';
import './bootstrap';
import './errorAlerts';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import SlowRequestIndicator from './Components/SlowRequestIndicator.vue';

// V-Calendar
import { setupCalendar } from 'v-calendar';
import 'v-calendar/style.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => [h(App, props), h(SlowRequestIndicator)] })
            .use(plugin)
            .use(ZiggyVue)
            .use(setupCalendar, {
                locale: 'es',
                masks: {
                    weekdays: 'WWW',
                }
            })
            .mount(el);
    },
    // Se reemplaza por SlowRequestIndicator (global, solo para peticiones lentas)
    progress: false,
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const isDocker = process.env.DOCKER_ENV === 'true';

// El polling solo se activa si lo pides explícitamente.
// En WSL (disco de Linux) normalmente no hace falta.
const usePolling = process.env.CHOKIDAR_USEPOLLING === 'true';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),

        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],

    // Pre-empaqueta estas dependencias al iniciar Vite, para que no se
    // descubran (y recarguen la página) al entrar por primera vez a una sección.
    optimizeDeps: {
        include: [
            'chart.js',
            'xlsx',
            'v-calendar',
            'sweetalert2',
            'axios',
            '@heroicons/vue/24/solid',
        ],
    },

    server: {
        host: isDocker ? '0.0.0.0' : 'localhost',
        port: 5173,
        strictPort: true,

        //detectar cambios en código y recompilar
        watch: {
            usePolling,
            interval: usePolling ? 1000 : undefined,
            ignored: ['**/vendor/**', '**/storage/**', '**/public/build/**'],
        },

        //Autorizar el cors de ambos origenes
        cors: {origin: isDocker
                ? 'http://localhost:8082'
                : 'http://plataformacpc.test',
        },

        ...(isDocker && {
            origin: 'http://localhost:5173',

            hmr: {
                host: 'localhost',
                port: 5173,
            },
        }),
    },
});
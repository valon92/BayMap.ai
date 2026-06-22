import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

const lanHost = process.env.VITE_HMR_HOST;

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Reflect the Laravel page origin (127.0.0.1, LAN IP, 0.0.0.0, …) — not just :5173.
        cors: true,
        origin: lanHost ? `http://${lanHost}:5173` : undefined,
        hmr: {
            host: lanHost || 'localhost',
            port: 5173,
        },
        proxy: {
            '/api': {
                target: process.env.VITE_DEV_SERVER_TARGET || 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
});

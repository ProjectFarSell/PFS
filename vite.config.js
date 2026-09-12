import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Bind to all interfaces so Docker can expose the dev server, but
        // tell the browser's HMR client to connect back via localhost
        // (the container's --host flag alone writes an invalid "[::]"
        // wildcard address into public/hot, which the browser can't use).
        hmr: {
            host: 'localhost',
        },
    },
});

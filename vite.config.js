import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: '/build/',
    build: {
        manifest: 'manifest.json',
        outDir: 'public/build',
    },
    optimizeDeps: {
        include: ['react', 'react-dom', '@excalidraw/excalidraw'],
    },
    define: {
        'process.env.IS_PREACT': JSON.stringify(false),
    },
    esbuild: {
        jsx: 'automatic',
        jsxImportSource: 'react',
    },
    // Used only in development (npm run dev); has no effect on production build output.
    server: {
        hmr: { host: 'localhost' },
    },
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
});
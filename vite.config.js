import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode, command }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = process.env.APP_URL || env.APP_URL || env.VITE_APP_URL || 'http://localhost';

    let buildBase = '/build/';
    try {
        const pathname = new URL(appUrl).pathname.replace(/\/$/, '') || '';
        buildBase = `${pathname}/build/`;
    } catch {
        // keep default
    }

    return {
        // Production assets live under public/build; dev server must use "/" so @vite URLs resolve.
        base: command === 'build' ? buildBase : '/',
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
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
        server: {
            // Match APP_URL (127.0.0.1) so Laravel's hot-file check and the browser use the same host.
            host: '127.0.0.1',
            hmr: { host: '127.0.0.1' },
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
    };
});

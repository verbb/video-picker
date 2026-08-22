import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import CompressionPlugin from 'vite-plugin-compression';

const cpRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    root: cpRoot,
    base: '',

    build: {
        outDir: path.resolve(cpRoot, './dist'),
        emptyOutDir: true,
        manifest: 'manifest.json',
        sourcemap: true,
        rollupOptions: {
            input: {
                'video-picker-cp': path.resolve(cpRoot, './src/video-picker-cp.js'),
            },
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },

    server: {
        origin: 'http://localhost:4036',
        host: 'localhost',
        port: 4036,
        strictPort: true,
        cors: true,
        hmr: {
            protocol: 'ws',
        },
    },

    plugins: [
        CompressionPlugin({ filter: /\.(js|mjs|json|css|map)$/i }),
    ],
});

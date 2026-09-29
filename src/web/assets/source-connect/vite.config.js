import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import CompressionPlugin from 'vite-plugin-compression';

const sourceConnectRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    root: sourceConnectRoot,
    base: '',

    build: {
        outDir: path.resolve(sourceConnectRoot, './dist'),
        emptyOutDir: true,
        manifest: 'manifest.json',
        sourcemap: true,
        rollupOptions: {
            input: {
                'video-picker-source-connect': path.resolve(sourceConnectRoot, './src/video-picker-source-connect.js'),
            },
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },

    server: {
        origin: 'http://localhost:4037',
        host: 'localhost',
        port: 4037,
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

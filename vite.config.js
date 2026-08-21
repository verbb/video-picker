import path from 'path';
import AnalyzePlugin from 'rollup-plugin-analyzer';
import CompressionPlugin from 'vite-plugin-compression';

// Web-components field bundle (Plugin Kit v2), modelled on Hyper.
// Two entries: `pluginKit` registers the `pk-*` custom elements first, then the
// `video-picker` app entry mounts field UI once those elements are defined.
export default {
    root: './src/web/assets',
    base: '',

    build: {
        outDir: 'field/dist',
        emptyOutDir: true,
        manifest: 'manifest.json',
        sourcemap: true,
        rollupOptions: {
            input: {
                pluginKit: '/field/src/js/plugin-kit-register.ts',
                'video-picker': '/field/src/js/video-picker.ts',
            },
        },
    },

    server: {
        origin: 'http://localhost:4035',
        hmr: { protocol: 'ws' },
    },

    plugins: [
        AnalyzePlugin({ summaryOnly: true, limit: 10 }),
        CompressionPlugin({ filter: /\.(js|mjs|json|css|map)$/i }),
    ],

    resolve: {
        alias: { '@': path.resolve('./src/web/assets/field/src') },
        preserveSymlinks: false,
    },

    optimizeDeps: { include: ['lodash-es'] },
};

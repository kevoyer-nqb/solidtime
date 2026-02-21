import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        setupFiles: ['resources/js/test-setup.ts'],
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            '@solidtime/ui': resolve(__dirname, 'resources/js/packages/ui/src/index.ts'),
        },
    },
});

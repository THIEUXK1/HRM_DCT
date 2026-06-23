import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: {
        // Tránh Laravel ghi public/hot dạng [::1]:5173 — trình duyệt Windows hay refused IPv6.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/hcm/main.js',
            ],
            // Chỉ full-reload khi Blade/PHP/route thay đổi.
            // Vue/JS/CSS files được xử lý bởi Vite HMR (không reload trang, giữ nguyên state).
            refresh: [
                'resources/views/**',
                'routes/**',
                'app/**/*.php',
                'config/**/*.php',
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js/hcm',
        },
    },
});

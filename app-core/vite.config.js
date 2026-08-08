import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,

            // Direktori publiknya ada di luar app-core: berkas yang dilayani web
            // tinggal di root repo, sementara aplikasinya di sini. Tanpa baris
            // ini Vite menulis ke app-core/public/build — di dalam direktori
            // yang justru ditolak Apache, jadi halamannya terbit tanpa CSS.
            publicDirectory: '..',
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

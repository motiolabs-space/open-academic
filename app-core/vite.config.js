import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    /*
     * URL aset di dalam CSS ditulis RELATIF terhadap berkas CSS-nya.
     *
     * Bawaan Vite menulisnya absolut dari akar domain — `/build/assets/x.woff2`.
     * Itu benar hanya bila aplikasinya dilayani di akar. Open Academic lazim
     * dipasang di subfolder hosting bersama (`/open-academic/`, `/siakad/`), dan
     * di sana peramban meminta `localhost/build/...` lalu mendapat 404 —
     * sementara CSS-nya sendiri termuat baik, karena `@vite` membangun URL-nya
     * lewat `asset()`.
     *
     * Tidak pernah terlihat sebelumnya karena CSS-nya memang belum pernah
     * merujuk aset apa pun: hurufnya dulu diambil dari Google. Begitu font
     * di-host sendiri, ke-30 berkas woff2 gagal dimuat dan halamannya diam-diam
     * memakai huruf sistem — persis gejala yang membawa ke baris ini.
     */
    base: './',

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

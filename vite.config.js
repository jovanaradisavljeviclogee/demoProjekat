import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    // Prazan prefiks: `loadEnv` inače vraća samo `VITE_*`, a nama treba `APP_URL`.
    // Time dev server prati `WEB_HOST_PORT` iz `.env` i ne mora da se dira kad se
    // port promeni — inače bi ovde stajao zakucan 8080 koji tiho prestane da važi.
    const { APP_URL: appUrl = 'http://localhost:8080' } = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/main.jsx'],
                refresh: true,
            }),
            react(),
            tailwindcss(),
        ],
        server: {
            // Adresa VEZIVANJA. Mora biti 0.0.0.0, ne podrazumevani 127.0.0.1: Docker
            // izlaže Apache na 0.0.0.0:8080 i zato je dostupan iz Windows pregledača,
            // dok Vite vezan samo za WSL-ov 127.0.0.1 tom pregledaču nije (ADR-19).
            host: '0.0.0.0',
            port: 5173,
            // Bez ovoga Vite na zauzet port tiho pređe na 5174 i tamo upiše `public/hot`,
            // pa stranica gađa server za koji niko ne zna. Bolje pad sa jasnom porukom.
            strictPort: true,
            // Adresa PRISTUPA — ovo završi u `public/hot` i u `<script src>`. Bez nje
            // plugin upiše adresu vezivanja, a `http://[::]:5173` je IPv6 wildcard koji
            // pregledač odbija sa ERR_ADDRESS_INVALID pre nego što pošalje ijedan paket.
            origin: 'http://localhost:5173',
            // Stranicu servira Apache na 8080, a module Vite na 5173 — to su dva
            // različita porekla, pa je svako učitavanje modula CORS zahtev. Vite 7 ima
            // pooštrenu podrazumevanu politiku i bez ovoga vraća `Access-Control-Allow-Origin`
            // sa vrednošću `origin` odozgo, dakle 5173, što se ne poklapa sa 8080 i
            // pregledač odbija skriptu iako je odgovor 200.
            cors: {
                origin: appUrl,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
